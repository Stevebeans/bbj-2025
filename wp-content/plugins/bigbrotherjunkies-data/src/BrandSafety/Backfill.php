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
     * Read-only full scan. Returns ['scanned' => int, 'rows' => [...]] where
     * rows are only the objects that had hits. Report (with scanned total)
     * is written to disk; a fresh dry-run resets that target's apply cursor
     * since the object set/order may have changed since the last run.
     */
    public static function dryRun(string $target): array
    {
        $rows = [];
        $scanned = 0;
        foreach (self::iterate($target) as $obj) {
            $scanned++;
            $hits = Scanner::scan($obj['text']);
            if ($hits) {
                $rows[] = ['id' => $obj['id'], 'label' => $obj['label'], 'url' => $obj['url'], 'terms' => $hits];
            }
        }

        $dir = WP_CONTENT_DIR . '/bbj-quarantine/reports';
        wp_mkdir_p($dir);
        file_put_contents(self::reportPath($target), wp_json_encode(['scanned' => $scanned, 'rows' => $rows]));

        $opt = get_option(Blocklist::OPTION, []);
        $opt["apply_cursor_{$target}"] = 0;
        update_option(Blocklist::OPTION, $opt);

        return ['scanned' => $scanned, 'rows' => $rows];
    }

    /**
     * Censor one batch of objects-with-hits from the stored report; cursor
     * lives in the brand-safety option. Never fatals: a missing/unreadable
     * report is reported as done with an error message, not thrown.
     *
     * @return array{processed:int, remaining:int, done:bool, error?:string}
     */
    public static function apply(string $target, int $batch): array
    {
        $file = self::reportPath($target);
        if (!is_readable($file)) {
            return [
                'processed' => 0,
                'remaining' => 0,
                'done' => true,
                'error' => 'no dry-run report found for this target; run dry-run first',
            ];
        }

        $data = json_decode((string) file_get_contents($file), true);
        $report = is_array($data) ? ($data['rows'] ?? []) : [];

        $opt = get_option(Blocklist::OPTION, []);
        $cursor = (int) ($opt["apply_cursor_{$target}"] ?? 0);
        $slice = array_slice($report, $cursor, max(1, $batch));

        foreach ($slice as $row) {
            self::applyOne($target, (int) $row['id']);
        }

        $cursor += count($slice);
        $opt["apply_cursor_{$target}"] = $cursor;
        update_option(Blocklist::OPTION, $opt);

        $remaining = max(0, count($report) - $cursor);
        return ['processed' => count($slice), 'remaining' => $remaining, 'done' => $remaining === 0];
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

    /** Generator over target objects: ['id', 'text', 'label', 'url']. Batched queries (500/page). */
    private static function iterate(string $target): \Generator
    {
        global $wpdb;
        $page = 0;
        while (true) {
            $offset = $page * 500;
            if ($target === 'posts') {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT ID, post_title, post_content, post_name, post_type FROM {$wpdb->posts}
                     WHERE post_type IN ('post','page','live-feed-updates') AND post_status = 'publish'
                     ORDER BY ID LIMIT 500 OFFSET %d",
                    $offset
                ));
                foreach ($rows as $r) {
                    $url = $r->post_type === 'live-feed-updates' ? "/live-feed-updates/{$r->post_name}" : "/{$r->post_name}";
                    yield ['id' => (int) $r->ID, 'text' => $r->post_title . "\n" . $r->post_content, 'label' => $r->post_title, 'url' => $url];
                }
            } elseif ($target === 'comments') {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT comment_ID, comment_content, comment_post_ID FROM {$wpdb->comments}
                     WHERE comment_approved = '1' ORDER BY comment_ID LIMIT 500 OFFSET %d",
                    $offset
                ));
                foreach ($rows as $r) {
                    yield ['id' => (int) $r->comment_ID, 'text' => $r->comment_content, 'label' => 'comment #' . $r->comment_ID, 'url' => '?p=' . $r->comment_post_ID];
                }
            } else { // quotes
                $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id LIMIT 500 OFFSET %d", $offset), ARRAY_A);
                foreach ($rows as $r) {
                    yield ['id' => (int) $r['id'], 'text' => $r['quote_text'], 'label' => 'quote #' . $r['id'], 'url' => ''];
                }
            }
            if (count($rows) < 500) {
                return;
            }
            $page++;
        }
    }
}
