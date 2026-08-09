<?php

namespace BigBrotherJunkies\Data\Cron;

/**
 * Bounded retention for post revisions (2026-08-08 spec).
 *
 * The custom editor auto-saves every 5s and each save is a WP revision, so
 * revisions are the crash-recovery net — but unbounded (one post hit 196).
 * Tiered policy, run daily via Action Scheduler:
 *   <24h   : untouched (fine-grained safety net during active writing)
 *   24h-30d: collapse to <=5 spaced snapshots (one per 10-min burst; the
 *            LONGEST copy in a burst wins, so a wipe never outlives the
 *            good copy it overwrote)
 *   >30d   : deleted
 */
class RevisionRetention
{
    public const HOOK = 'bbjd_prune_revisions';
    public const AS_GROUP = 'bbjd-revisions';
    public const KEEP_SNAPSHOTS = 5;
    public const BUCKET_SECONDS = 600;
    public const FRESH_HOURS = 24;
    public const MAX_AGE_DAYS = 30;
    public const DELETE_BUDGET = 500;

    public function init(): void
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [self::class, 'ensureScheduled']);
    }

    /** Idempotent init-time guard — mirrors BeanBotService::ensureScheduled(). */
    public static function ensureScheduled(): void
    {
        if (!function_exists('as_schedule_recurring_action') || !function_exists('as_next_scheduled_action')) {
            return;
        }
        if (as_next_scheduled_action(self::HOOK) === false) {
            // $unique=true so a duplicate recurring job can never be queued.
            as_schedule_recurring_action(time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, [], self::AS_GROUP, true);
        }
    }

    public function run(): void
    {
        global $wpdb;

        $budget = self::DELETE_BUDGET;
        $freshCutoff = gmdate('Y-m-d H:i:s', time() - self::FRESH_HOURS * HOUR_IN_SECONDS);

        // Parents with at least one revision old enough to be prunable.
        $parents = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_parent FROM {$wpdb->posts}
             WHERE post_type = 'revision' AND post_date_gmt < %s",
            $freshCutoff
        ));

        $totalDeleted = 0;
        foreach ($parents as $parentId) {
            if ($budget <= 0) {
                break; // first runs chew through the backlog over a few days
            }
            try {
                $deleted = $this->prunePost((int) $parentId, $budget);
                $budget -= $deleted;
                $totalDeleted += $deleted;
            } catch (\Throwable $e) {
                error_log("[bbjd] revision prune failed for post {$parentId}: " . $e->getMessage());
            }
        }

        if ($totalDeleted > 0) {
            error_log("[bbjd] revision retention deleted {$totalDeleted} revisions");
        }
    }

    /** Prune one post's revisions per the tiered policy. Returns deletions. */
    public function prunePost(int $parentId, int $budget = PHP_INT_MAX): int
    {
        global $wpdb;

        // Lengths computed in SQL so we never load revision bodies into PHP.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, UNIX_TIMESTAMP(post_date_gmt) AS ts, CHAR_LENGTH(post_content) AS len
             FROM {$wpdb->posts}
             WHERE post_parent = %d AND post_type = 'revision'
             ORDER BY post_date_gmt DESC",
            $parentId
        ));

        $now = time();
        $delete = [];
        $buckets = []; // bucket index => winning row (longest; ties -> newest)

        foreach ($rows as $r) {
            $age = $now - (int) $r->ts;
            if ($age < self::FRESH_HOURS * HOUR_IN_SECONDS) {
                continue; // never touch the fresh window
            }
            if ($age > self::MAX_AGE_DAYS * DAY_IN_SECONDS) {
                $delete[] = (int) $r->ID;
                continue;
            }
            $bucket = intdiv((int) $r->ts, self::BUCKET_SECONDS);
            if (!isset($buckets[$bucket])) {
                $buckets[$bucket] = $r; // rows are DESC, so first seen = newest
                continue;
            }
            if ((int) $r->len > (int) $buckets[$bucket]->len) {
                $delete[] = (int) $buckets[$bucket]->ID;
                $buckets[$bucket] = $r;
            } else {
                $delete[] = (int) $r->ID;
            }
        }

        // Keep only the KEEP_SNAPSHOTS most recent bucket winners.
        krsort($buckets);
        foreach (array_slice(array_values($buckets), self::KEEP_SNAPSHOTS) as $loser) {
            $delete[] = (int) $loser->ID;
        }

        $deleted = 0;
        foreach ($delete as $revId) {
            if ($deleted >= $budget) {
                break;
            }
            if (wp_delete_post_revision($revId)) {
                $deleted++;
            }
        }
        return $deleted;
    }
}
