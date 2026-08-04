<?php
namespace BigBrotherJunkies\Data\BrandSafety;

use BigBrotherJunkies\Data\Social\SocialSchema;

/**
 * One-time/legacy-content sweep: read-only dry-run report + batched apply.
 * Shares the live save_post/comment/quote censoring code paths (Scanner,
 * Hooks::scanAndCensorPost) so backfilled content is masked identically to
 * new content.
 */
class Backfill
{
    public const TARGETS = ['posts', 'comments', 'quotes'];

    private static function reportPath(string $target): string
    {
        return WP_CONTENT_DIR . "/bbj-quarantine/reports/dry-run-{$target}.json";
    }

    /**
     * Read-only scan, time-sliced and resumable: a single call processes
     * objects for up to ~$sliceSeconds of wall time (always at least one
     * object, even with $sliceSeconds=0) and persists partial progress
     * (offset + rows-with-hits so far) to the report file with `done:false`.
     * The next call for the same target picks up from that offset via
     * iterate()'s starting-offset param — no rescanning consumed objects.
     *
     * A report already `done:true` (or missing/corrupt) is treated as
     * finished/stale: this call deletes it and starts a fresh scan from
     * offset 0, since the object set/order may have changed since the last
     * completed run. Only a report explicitly `done:false` is resumed.
     *
     * The apply cursor is reset to 0 only once the scan actually finishes
     * (`done:true`) — never on intermediate slices.
     *
     * @return array{done:bool, scanned_so_far:int, hits_objects_so_far:int}
     */
    public static function dryRun(string $target, int $sliceSeconds = 20): array
    {
        $file = self::reportPath($target);
        $existing = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        $resuming = is_array($existing) && ($existing['done'] ?? true) === false;

        if ($resuming) {
            $offset = (int) ($existing['offset'] ?? 0);
            $rows = is_array($existing['rows'] ?? null) ? $existing['rows'] : [];
            $scanned = (int) ($existing['scanned'] ?? 0);
            $applied = (int) ($existing['applied'] ?? 0);
        } else {
            if (is_readable($file)) {
                @unlink($file); // stale/completed report from a prior run — start fresh
            }
            $offset = 0;
            $rows = [];
            $scanned = 0;
            $applied = 0;
        }

        $deadline = microtime(true) + max(0, $sliceSeconds);
        $done = true;
        $processedThisSlice = 0;

        foreach (self::iterate($target, $offset) as $obj) {
            // Always process at least one object per call (so sliceSeconds=0
            // still makes progress instead of spinning forever), then check
            // the budget before taking on the next one.
            if ($processedThisSlice > 0 && microtime(true) >= $deadline) {
                $done = false;
                break;
            }

            $scanned++;
            $offset++;
            $processedThisSlice++;

            $hits = Scanner::scan($obj['text']);
            if ($hits) {
                $rows[] = ['id' => $obj['id'], 'label' => $obj['label'], 'url' => $obj['url'], 'terms' => $hits];
            }
        }

        self::writeReport($file, [
            'done' => $done,
            'offset' => $offset,
            'scanned' => $scanned,
            'rows' => $rows,
            // A scan that actually finishes invalidates any in-progress apply
            // cursor: the object set/order may have changed since the last
            // completed run, so apply() must restart from the top of this
            // fresh row list. Never reset on an intermediate (done:false) slice.
            'applied' => $done ? 0 : $applied,
        ]);

        return ['done' => $done, 'scanned_so_far' => $scanned, 'hits_objects_so_far' => count($rows)];
    }

    /** Full persisted dry-run report for a target, or null if none exists/unreadable. */
    public static function readReport(string $target): ?array
    {
        $file = self::reportPath($target);
        if (!is_readable($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Trickle delay between purge calls (cost guardrail — do not remove or
     * shrink). scanAndCensorPost() writes via direct $wpdb->update, so it
     * never fires transition_post_status and the purge-on-edit webhook never
     * runs; without an explicit purge, applied posts would keep serving
     * stale CF/ISR-cached HTML (uncensored content, missing ads_unsafe) for
     * up to the edge TTL.
     */
    private const PURGE_THROTTLE_MICROSECONDS = 250000;

    /**
     * Censor one batch of objects-with-hits from the stored report; the
     * cursor (`applied`) lives IN that same report file, not in the
     * `bbjd_brand_safety` option. The option is read via Cloudways' Redis
     * object cache, which has served stale `alloptions` reads under rapid
     * sequential REST requests — every apply batch re-read a stale cursor
     * and reprocessed the same first batch forever. The filesystem has no
     * such cache layer, and this loop is single-writer, so the report file
     * is the reliable place for this state. Never fatals: a missing/unreadable
     * report is reported as done with an error message, not thrown.
     *
     * $purge is honored only for the posts target (comments are client-fetched,
     * quotes ride API responses — neither needs a CF/ISR purge). Defaults to
     * false: callers must opt in explicitly, since a purge hits the real
     * revalidate endpoint (prod by default when NEXT_PUBLIC_SITE_URL isn't set).
     *
     * @return array{processed:int, remaining:int, done:bool, purged:int, error?:string}
     */
    public static function apply(string $target, int $batch, bool $purge = false): array
    {
        $file = self::reportPath($target);
        if (!is_readable($file)) {
            return [
                'processed' => 0,
                'remaining' => 0,
                'done' => true,
                'purged' => 0,
                'error' => 'no dry-run report found for this target; run dry-run first',
            ];
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            $data = [];
        }
        $report = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        $applied = (int) ($data['applied'] ?? 0);
        $slice = array_slice($report, $applied, max(1, $batch));

        $purged = 0;
        foreach ($slice as $row) {
            $id = (int) $row['id'];
            self::applyOne($target, $id);
            if ($purge && $target === 'posts') {
                if (self::purgePost($id)) {
                    $purged++;
                }
                usleep(self::PURGE_THROTTLE_MICROSECONDS);
            }
        }

        $applied += count($slice);
        $data['applied'] = $applied;
        self::writeReport($file, $data);

        $remaining = max(0, count($report) - $applied);
        return ['processed' => count($slice), 'remaining' => $remaining, 'done' => $remaining === 0, 'purged' => $purged];
    }

    /**
     * Persist a report atomically: write to a temp file in the same
     * directory, then rename over the original. Both dryRun() and apply()
     * read-modify-write this file as the sole cursor store, so a crash
     * mid-write must never leave a half-written/corrupt report behind.
     */
    private static function writeReport(string $file, array $data): void
    {
        QuarantineGuard::ensureSubDir('reports');
        $tmp = $file . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, wp_json_encode($data));
        rename($tmp, $file);
    }

    /** Targeted CF/ISR revalidation for one just-applied post, over-purge-tolerant. */
    private static function purgePost(int $id): bool
    {
        $post = get_post($id);
        if (!$post) {
            return false;
        }
        if ($post->post_type === 'live-feed-updates') {
            return \BigBrotherJunkies\Data\Utils\Revalidation::revalidateFeedUpdate($post->post_name);
        }
        return \BigBrotherJunkies\Data\Utils\Revalidation::revalidatePost($post->post_name);
    }

    private static function applyOne(string $target, int $id): void
    {
        global $wpdb;

        if ($target === 'posts') {
            Hooks::scanAndCensorPost($id);
            return;
        }

        if ($target === 'comments') {
            $c = get_comment($id);
            if (!$c) {
                return;
            }
            // Unmaskable (in-tag) hits are NOT rejected here — this is legacy
            // content, not a live submission, so we can't bounce it back to
            // an author. Scanner::censor still masks every maskable hit; the
            // per-hit 'action' (flagged vs censored) tells the admin UI which
            // rows still need a human look.
            $r = Scanner::censor($c->comment_content);
            if ($r['html'] !== $c->comment_content) {
                if (get_comment_meta($id, '_bbjd_bs_original', true) === '') {
                    add_comment_meta($id, '_bbjd_bs_original', $c->comment_content, true);
                }
                $wpdb->update($wpdb->comments, ['comment_content' => $r['html']], ['comment_ID' => $id], ['%s'], ['%d']);
                clean_comment_cache($id);
            }
            Log::record('comment', $id, $r['hits']);
            return;
        }

        if ($target === 'quotes') {
            $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
            $q = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
            if (!$q) {
                return;
            }
            $r = Scanner::censor($q['quote_text']);
            if ($r['html'] !== $q['quote_text']) {
                $wpdb->update($table, ['quote_text' => $r['html']], ['id' => $id], ['%s'], ['%d']);
            }
            Log::record('quote', $id, $r['hits']);
        }
    }

    /**
     * Generator over target objects: ['id', 'text', 'label', 'url']. Batched
     * queries (500/page). $startOffset resumes mid-table without rescanning
     * already-consumed objects — it seeks to the containing page, then skips
     * forward within just that first page.
     */
    private static function iterate(string $target, int $startOffset = 0): \Generator
    {
        global $wpdb;
        $pageSize = 500;
        $firstPage = intdiv($startOffset, $pageSize);
        $skipInFirstPage = $startOffset % $pageSize;
        $page = $firstPage;

        while (true) {
            $offset = $page * $pageSize;
            $items = [];
            if ($target === 'posts') {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_content, post_name, post_type FROM {$wpdb->posts}
                     WHERE post_type IN ('post','page','live-feed-updates') AND post_status = 'publish'
                     ORDER BY ID LIMIT 500 OFFSET %d",
                    $offset
                ));
                foreach ($rows as $r) {
                    $url = $r->post_type === 'live-feed-updates' ? "/live-feed-updates/{$r->post_name}" : "/{$r->post_name}";
                    $items[] = ['id' => (int) $r->ID, 'text' => $r->post_title . "\n" . $r->post_content, 'label' => $r->post_title, 'url' => $url];
                }
            } elseif ($target === 'comments') {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT comment_ID, comment_content, comment_post_ID FROM {$wpdb->comments}
                     WHERE comment_approved = '1' ORDER BY comment_ID LIMIT 500 OFFSET %d",
                    $offset
                ));
                // Batch-resolve host posts (slug + type) for real, working links —
                // '?p=<id>' resolved relative to the admin page, not the site root.
                $postMap = [];
                if ($rows) {
                    $postIds = array_values(array_unique(array_map(static fn($r) => (int) $r->comment_post_ID, $rows)));
                    $placeholders = implode(',', array_fill(0, count($postIds), '%d'));
                    $postRows = $wpdb->get_results($wpdb->prepare(
                        "SELECT ID, post_name, post_type FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
                        ...$postIds
                    ));
                    foreach ($postRows as $p) {
                        $postMap[(int) $p->ID] = $p;
                    }
                }
                foreach ($rows as $r) {
                    $post = $postMap[(int) $r->comment_post_ID] ?? null;
                    $base = $post ? ($post->post_type === 'live-feed-updates' ? "/live-feed-updates/{$post->post_name}" : "/{$post->post_name}") : '';
                    $items[] = [
                        'id' => (int) $r->comment_ID,
                        'text' => $r->comment_content,
                        'label' => 'comment #' . $r->comment_ID,
                        'url' => $base . '#comment-' . $r->comment_ID,
                    ];
                }
            } else { // quotes
                $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id LIMIT 500 OFFSET %d", $offset), ARRAY_A);
                foreach ($rows as $r) {
                    $items[] = ['id' => (int) $r['id'], 'text' => $r['quote_text'], 'label' => 'quote #' . $r['id'], 'url' => ''];
                }
            }

            foreach ($items as $i => $item) {
                if ($page === $firstPage && $i < $skipInFirstPage) {
                    continue;
                }
                yield $item;
            }

            if (count($rows) < $pageSize) {
                return;
            }
            $page++;
        }
    }
}
