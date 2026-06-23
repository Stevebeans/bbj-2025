<?php

namespace BigBrotherJunkies\Data\Weekly;

/**
 * WP-CLI: import a season's weekly voting-history CSV into the weekly tables.
 * Registered as `wp bbj weekly-import` when WP-CLI is present.
 */
class WeeklyImportCommand
{
    /**
     * ## OPTIONS
     * --season=<number>   Season number (e.g. 27).
     * --file=<path>       Path to the scraper CSV.
     * [--commit]          Persist changes (default: dry run, no writes).
     * [--trial]           Run all writes inside a transaction, then ROLL BACK (tests SQL, persists nothing).
     * [--no-revalidate]   Skip Next.js revalidation after a commit.
     *
     * ## EXAMPLES
     *   wp bbj weekly-import --season=27 --file=bb27-weekly.csv --skip-themes
     *   wp bbj weekly-import --season=27 --file=bb27-weekly.csv --commit --skip-themes
     */
    public function __invoke($args, $assoc)
    {
        $season = $assoc['season'] ?? '';
        $file = $assoc['file'] ?? '';
        if ($season === '' || $file === '') {
            \WP_CLI::error('Both --season and --file are required.');
        }
        $mode = isset($assoc['commit']) ? 'commit' : (isset($assoc['trial']) ? 'trial' : 'dry');
        $revalidate = !isset($assoc['no-revalidate']);

        try {
            $report = (new WeeklyImporter())->run((string) $season, (string) $file, [
                'mode' => $mode,
                'revalidate' => $revalidate,
            ]);
        } catch (\Throwable $e) {
            \WP_CLI::error($e->getMessage());
            return;
        }

        foreach (explode("\n", self::format($report)) as $line) {
            \WP_CLI::log($line);
        }

        if (!empty($report['unmatched_houseguests'])) {
            \WP_CLI::warning('Unmatched houseguest names — a commit will be refused until these resolve.');
        }
        if ($mode === 'commit' && $report['applied'] === null) {
            \WP_CLI::error('Write aborted: ' . ($report['aborted'] ?? 'unknown reason'));
        }
    }

    /** Human-readable report, reused by the local php harness. */
    public static function format(array $r): string
    {
        $lines = [];
        $lines[] = '';
        $lines[] = "Season {$r['season_number']}  (id {$r['season_id']}, slug {$r['season_slug']})  roster={$r['roster_size']}";
        $lines[] = "Mode: {$r['mode']}";
        $p = $r['plan'];
        $lines[] = "Plan: {$p['weeks']} weeks, {$p['players']} player-rows, {$p['comps']} comp wins, {$p['jurors']} jury votes";
        $lines[] = "Comp types: " . implode(', ', $r['comp_types']);
        $lines[] = "Finale: winner={$r['finale']['winner']} runner-up={$r['finale']['runner_up']}";

        if (!empty($r['unmatched_houseguests'])) {
            $lines[] = "UNMATCHED houseguests (" . count($r['unmatched_houseguests']) . "): " . implode('; ', $r['unmatched_houseguests']);
        } else {
            $lines[] = "All houseguest names resolved.";
        }
        if (!empty($r['unmatched_refs'])) {
            $lines[] = "Unmatched refs (votes/saves): " . implode('; ', $r['unmatched_refs']);
        }

        if (!empty($r['aborted'])) {
            $lines[] = "ABORTED: {$r['aborted']}";
        }
        if (!empty($r['applied'])) {
            $a = $r['applied'];
            $verb = $a['committed'] ? 'COMMITTED' : 'TRIAL (rolled back)';
            $lines[] = "{$verb}: deleted " . json_encode($a['deleted'])
                . ", inserted " . json_encode($a['inserted'])
                . ", totals_updated={$a['totals_updated']}";
        }
        if (!empty($r['revalidated'])) {
            $lines[] = "Revalidated {$r['revalidated']['calls']} paths (season + roster).";
        }
        return implode("\n", $lines);
    }
}
