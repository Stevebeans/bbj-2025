<?php

namespace BigBrotherJunkies\Data\Weekly;

use BigBrotherJunkies\Data\Utils\Revalidation;

/**
 * Imports a season's weekly voting-history CSV (produced by the wiki scraper) into the
 * weekly-tracking tables. Writes the junction model only:
 *   bbj_weeks, bbj_week_comps (+ bbj_comp_types), bbj_weeks_players (status fields).
 * The legacy hoh/pov booleans on bbj_weeks_players are deprecated and never written.
 *
 * Name resolution is scoped to the season's own roster (player_season), so it works on any
 * server's own ID space and disambiguates returning players (e.g. Rachel Reilly).
 *
 * Modes (via run()): dry-run (plan only, no writes), trial (write inside a rolled-back
 * transaction), commit (write + recompute totals + revalidate).
 */
class WeeklyImporter
{
    private \wpdb $db;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    private function t(string $name): string
    {
        return $this->db->prefix . $name;
    }

    // -----------------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------------

    /** Season post ID (= bbj_seasons.id) for a season number, or null. */
    public function resolveSeasonId(string $seasonNumber): ?int
    {
        $id = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->t('bbj_seasons')} WHERE season_number = %s",
            $seasonNumber
        ));
        return $id ? (int) $id : null;
    }

    public function seasonSlug(int $seasonId): string
    {
        return (string) $this->db->get_var($this->db->prepare(
            "SELECT post_name FROM {$this->db->posts} WHERE ID = %d",
            $seasonId
        ));
    }

    /** Roster for a season: [{ id, title, slug }] from player_season. */
    public function loadRoster(int $seasonId): array
    {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT p.ID AS id, p.post_title AS title, p.post_name AS slug
               FROM {$this->t('bbj_v2_player_season')} ps
               JOIN {$this->db->posts} p ON p.ID = ps.bbj_player
              WHERE ps.bbj_season = %d",
            $seasonId
        ), ARRAY_A) ?: [];
        return array_map(static fn ($r) => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'slug' => $r['slug'],
        ], $rows);
    }

    // -----------------------------------------------------------------------
    // CSV
    // -----------------------------------------------------------------------

    /** Parse the scraper CSV into a list of assoc rows keyed by header. */
    public static function parseCsv(string $path): array
    {
        if (!is_readable($path)) {
            throw new \RuntimeException("CSV not readable: {$path}");
        }
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if (!$header) {
            throw new \RuntimeException("CSV is empty: {$path}");
        }
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }
        fclose($fh);
        return $rows;
    }

    /**
     * Optional aliases live in a sibling file `<csv-without-ext>-aliases.csv`, one
     * "csvName,rosterName" per line — for names the wiki abbreviates that don't overlap
     * the roster title (e.g. "Will,Cliffton Williams"). Returns [csvName => rosterName].
     */
    public static function loadAliases(string $csvPath): array
    {
        $path = preg_replace('/\.csv$/i', '', $csvPath) . '-aliases.csv';
        if (!is_readable($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $parts = str_getcsv($line);
            if (count($parts) >= 2 && trim($parts[0]) !== '') {
                $out[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // Name resolution (scoped to roster)
    // -----------------------------------------------------------------------

    private static function norm(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[\x{2018}\x{2019}\x{201C}\x{201D}]/u', "'", $s); // curly quotes
        return preg_replace('/\s+/', ' ', $s);
    }

    /** Lowercase word tokens with punctuation stripped — "Isaiah “Zae” Frederich" → [isaiah, zae, frederich]. */
    private static function tokenize(string $s): array
    {
        $words = preg_split('/\s+/', self::norm($s));
        $out = [];
        foreach ($words as $w) {
            $w = preg_replace('/[^a-z0-9]/', '', $w);
            if ($w !== '') $out[] = $w;
        }
        return $out;
    }

    /**
     * Build a resolver from roster. Returns a callable(name): array{id:?int,status:string}.
     * Matches by exact full title, else when every word of the query is present in a single
     * roster title's words (handles first names "Reilly"→"Reilly Smedley", multi-word
     * "Bowie Jane"→"Bowie Jane Ball", and nicknames "Will"→"Cliffton Will Williams").
     */
    private function makeResolver(array $roster, array $aliases = []): callable
    {
        $byFull = [];
        $tokens = []; // id => [word,...]
        foreach ($roster as $r) {
            $t = self::tokenize($r['title']);
            $byFull[implode(' ', $t)][] = $r['id'];
            $tokens[$r['id']] = $t;
        }
        // Aliases: normalized csv name => roster name to resolve instead.
        $aliasMap = [];
        foreach ($aliases as $from => $to) {
            $aliasMap[self::norm((string) $from)] = (string) $to;
        }
        $cache = [];
        return function (string $name) use ($byFull, $tokens, $aliasMap, &$cache): array {
            $key = self::norm($name);
            if ($key === '') {
                return ['id' => null, 'status' => 'empty'];
            }
            if (isset($cache[$key])) {
                return $cache[$key];
            }
            if (isset($aliasMap[$key])) {
                $name = $aliasMap[$key];
            }
            $uniq = static function (array $ids): array {
                $ids = array_values(array_unique($ids));
                if (count($ids) === 1) return ['id' => $ids[0], 'status' => 'matched'];
                if (count($ids) > 1) return ['id' => null, 'status' => 'ambiguous'];
                return ['id' => null, 'status' => 'unmatched'];
            };
            $queryWords = self::tokenize($name);
            $full = implode(' ', $queryWords);
            if (!empty($byFull[$full])) {
                return $cache[$key] = $uniq($byFull[$full]);
            }
            // Subset match: all query words present in one roster title's words.
            $hits = [];
            foreach ($tokens as $id => $words) {
                $all = true;
                foreach ($queryWords as $w) {
                    if (!in_array($w, $words, true)) { $all = false; break; }
                }
                if ($all) $hits[] = $id;
            }
            return $cache[$key] = $uniq($hits);
        };
    }

    private static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    // -----------------------------------------------------------------------
    // Plan
    // -----------------------------------------------------------------------

    public function plan(int $seasonId, array $rows, array $roster, array $aliases = []): array
    {
        $resolve = $this->makeResolver($roster, $aliases);

        $weekNums = [];
        $players = [];
        $comps = [];
        $compSlugs = ['hoh' => 'HOH', 'pov' => 'Power of Veto'];
        $finale = ['winner_id' => null, 'runner_up_id' => null, 'winner_name' => '', 'runner_up_name' => '', 'jurors' => []];
        $resolution = [];
        $unmatchedHg = [];
        $unmatchedRefs = [];

        $resolveInto = function (string $name, string $context) use ($resolve, &$resolution, &$unmatchedRefs): ?int {
            $name = trim($name);
            if ($name === '') return null;
            $r = $resolve($name);
            $resolution[$name] = $r['status'];
            if ($r['status'] !== 'matched') {
                $unmatchedRefs[] = "{$name} [{$context}] ({$r['status']})";
            }
            return $r['id'];
        };

        foreach ($rows as $row) {
            $isFinale = isset($row['wiki_label']) && self::norm($row['wiki_label']) === 'finale';
            $hg = trim($row['houseguest'] ?? '');
            if ($hg === '') continue;

            $hgRes = $resolve($hg);
            $resolution[$hg] = $hgRes['status'];
            if ($hgRes['status'] !== 'matched') {
                if (!in_array("{$hg} ({$hgRes['status']})", $unmatchedHg, true)) {
                    $unmatchedHg[] = "{$hg} ({$hgRes['status']})";
                }
                // Still process refs for diagnostics, but skip rows we can't anchor.
                if (!$isFinale) continue;
            }
            $hgId = $hgRes['id'];

            if ($isFinale) {
                $result = self::norm($row['finale'] ?? '');
                if ($result === 'winner') {
                    $finale['winner_id'] = $hgId;
                    $finale['winner_name'] = $hg;
                } elseif ($result === 'runner-up' || $result === 'runnerup') {
                    $finale['runner_up_id'] = $hgId;
                    $finale['runner_up_name'] = $hg;
                } elseif ($result === 'juror') {
                    $voteForId = $resolveInto($row['vote_to_win'] ?? '', "vote_to_win:{$hg}");
                    $finale['jurors'][] = [
                        'player_id' => $hgId,
                        'vote_for_id' => $voteForId,
                        'vote_for_name' => trim($row['vote_to_win'] ?? ''),
                    ];
                }
                continue;
            }

            $week = (int) $row['week'];
            if ($week <= 0) continue;
            $weekNums[$week] = true;

            $nomInitial = (int) ($row['nom_initial'] ?? 0) === 1;
            $nomFinal = (int) ($row['nom_final'] ?? 0) === 1;
            $players[] = [
                'week_num' => $week,
                'player_id' => $hgId,
                'nom' => ($nomInitial || $nomFinal) ? 1 : 0,
                'evicted' => (int) ($row['evicted'] ?? 0) === 1 ? 1 : 0,
                'veto_played' => 0,
                'voted_for' => $resolveInto($row['voted_for'] ?? '', "voted_for:{$hg} W{$week}") ?? 0,
                'saved_by' => $resolveInto($row['saved_by'] ?? '', "saved_by:{$hg} W{$week}"),
                'active' => 1,
            ];

            $compMap = [
                'hoh' => (int) ($row['hoh'] ?? 0) === 1,
                'pov' => (int) ($row['veto'] ?? 0) === 1,
                'blockbuster' => (int) ($row['blockbuster'] ?? 0) === 1,
            ];
            foreach ($compMap as $slug => $won) {
                if (!$won) continue;
                if ($slug === 'blockbuster') $compSlugs['blockbuster'] = 'Block Buster';
                $comps[] = ['week_num' => $week, 'player_id' => $hgId, 'slug' => $slug];
            }
            if ((int) ($row['misc'] ?? 0) === 1) {
                $label = trim($row['misc_label'] ?? '') ?: 'Misc Comp';
                $slug = self::slugify($label) ?: 'misc';
                $compSlugs[$slug] = $label;
                $comps[] = ['week_num' => $week, 'player_id' => $hgId, 'slug' => $slug];
            }
        }

        ksort($weekNums);
        return [
            'season_id' => $seasonId,
            'week_nums' => array_keys($weekNums),
            'players' => $players,
            'comps' => $comps,
            'comp_slugs' => $compSlugs,
            'finale' => $finale,
            'resolution' => $resolution,
            'unmatched_houseguests' => $unmatchedHg,
            'unmatched_refs' => array_values(array_unique($unmatchedRefs)),
            'counts' => [
                'weeks' => count($weekNums),
                'players' => count($players),
                'comps' => count($comps),
                'jurors' => count($finale['jurors']),
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Apply
    // -----------------------------------------------------------------------

    private function ensureCompType(string $slug, string $name): int
    {
        $table = $this->t('bbj_comp_types');
        $id = $this->db->get_var($this->db->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($id) return (int) $id;
        $this->db->insert($table, ['slug' => $slug, 'name' => $name, 'sort_order' => 40]);
        return (int) $this->db->insert_id;
    }

    /**
     * Write the plan. $commit=false performs every write inside a transaction and rolls
     * back (trial mode) so the SQL path is exercised without persisting.
     */
    public function apply(int $seasonId, array $plan, bool $commit): array
    {
        $weeks = $this->t('bbj_weeks');
        $wplayers = $this->t('bbj_weeks_players');
        $wcomps = $this->t('bbj_week_comps');
        $pseason = $this->t('bbj_v2_player_season');
        $seasons = $this->t('bbj_seasons');

        $deleted = ['weeks' => 0, 'players' => 0, 'comps' => 0];
        $this->db->query('START TRANSACTION');
        try {
            // Idempotent: clear this season's existing weekly rows, then rebuild from the CSV.
            $existingWeekIds = $this->db->get_col($this->db->prepare(
                "SELECT id FROM {$weeks} WHERE season_id = %d",
                $seasonId
            ));
            if ($existingWeekIds) {
                $in = implode(',', array_map('intval', $existingWeekIds));
                $deleted['comps'] = (int) $this->db->query("DELETE FROM {$wcomps} WHERE week_id IN ({$in})");
            }
            $deleted['players'] = (int) $this->db->query($this->db->prepare("DELETE FROM {$wplayers} WHERE season_id = %d", $seasonId));
            $deleted['weeks'] = (int) $this->db->query($this->db->prepare("DELETE FROM {$weeks} WHERE season_id = %d", $seasonId));

            // Comp types.
            $typeId = [];
            foreach ($plan['comp_slugs'] as $slug => $name) {
                $typeId[$slug] = $this->ensureCompType($slug, $name);
            }

            // Weeks.
            $weekIdByNum = [];
            foreach ($plan['week_nums'] as $num) {
                $this->db->insert($weeks, ['season_id' => $seasonId, 'week_num' => $num]);
                $weekIdByNum[$num] = (int) $this->db->insert_id;
            }

            // Weeks players.
            $inserted = ['players' => 0, 'comps' => 0];
            foreach ($plan['players'] as $p) {
                $wid = $weekIdByNum[$p['week_num']] ?? null;
                if (!$wid || !$p['player_id']) continue;
                $this->db->insert($wplayers, [
                    'week_id' => $wid,
                    'player_id' => $p['player_id'],
                    'season_id' => $seasonId,
                    'nom' => $p['nom'],
                    'evicted' => $p['evicted'],
                    'veto_played' => $p['veto_played'],
                    'voted_for' => $p['voted_for'],
                    'saved_by_player_id' => $p['saved_by'],
                    'vote_to_win' => 0,
                    'active' => $p['active'],
                ]);
                $inserted['players']++;
            }

            // Week comps.
            foreach ($plan['comps'] as $c) {
                $wid = $weekIdByNum[$c['week_num']] ?? null;
                $ctid = $typeId[$c['slug']] ?? null;
                if (!$wid || !$ctid || !$c['player_id']) continue;
                $ok = $this->db->query($this->db->prepare(
                    "INSERT IGNORE INTO {$wcomps} (week_id, player_id, comp_type_id) VALUES (%d, %d, %d)",
                    $wid, $c['player_id'], $ctid
                ));
                $inserted['comps'] += (int) $ok;
            }

            // Finale: season winner/runner-up + finish_place + jury votes.
            $f = $plan['finale'];
            if ($f['winner_id']) {
                $this->db->update($seasons, ['season_winner' => $f['winner_id']], ['id' => $seasonId]);
                $this->db->update($pseason, ['finish_place' => 1], ['bbj_player' => $f['winner_id'], 'bbj_season' => $seasonId]);
            }
            if ($f['runner_up_id']) {
                $this->db->update($seasons, ['runner_up' => $f['runner_up_id']], ['id' => $seasonId]);
                $this->db->update($pseason, ['finish_place' => 2], ['bbj_player' => $f['runner_up_id'], 'bbj_season' => $seasonId]);
            }
            foreach ($f['jurors'] as $j) {
                if (!$j['player_id'] || !$j['vote_for_id']) continue;
                // The juror voted at the finale; record on their eviction-week row.
                $this->db->query($this->db->prepare(
                    "UPDATE {$wplayers} SET vote_to_win_for = %d, vote_to_win = %d
                      WHERE season_id = %d AND player_id = %d AND evicted = 1",
                    $j['vote_for_id'],
                    $j['vote_for_id'] === $f['winner_id'] ? 1 : 0,
                    $seasonId,
                    $j['player_id']
                ));
            }

            // Derived per-player totals for this season.
            $totalsUpdated = WeeklyData::recomputeSeasonTotals($seasonId);

            if ($commit) {
                $this->db->query('COMMIT');
            } else {
                $this->db->query('ROLLBACK');
            }

            return [
                'committed' => $commit,
                'deleted' => $deleted,
                'inserted' => $inserted,
                'comp_types' => $typeId,
                'totals_updated' => $totalsUpdated,
            ];
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Orchestration
    // -----------------------------------------------------------------------

    /**
     * @param array{mode?:string, revalidate?:bool} $opts  mode = dry|trial|commit
     */
    public function run(string $seasonNumber, string $csvPath, array $opts = []): array
    {
        $mode = $opts['mode'] ?? 'dry';
        $seasonId = $this->resolveSeasonId($seasonNumber);
        if (!$seasonId) {
            throw new \RuntimeException("No season found with season_number={$seasonNumber}");
        }
        $roster = $this->loadRoster($seasonId);
        $rows = self::parseCsv($csvPath);
        $aliases = self::loadAliases($csvPath);
        $plan = $this->plan($seasonId, $rows, $roster, $aliases);

        $report = [
            'season_number' => $seasonNumber,
            'season_id' => $seasonId,
            'season_slug' => $this->seasonSlug($seasonId),
            'roster_size' => count($roster),
            'mode' => $mode,
            'plan' => $plan['counts'],
            'finale' => [
                'winner' => $plan['finale']['winner_name'],
                'runner_up' => $plan['finale']['runner_up_name'],
                'jurors' => $plan['counts']['jurors'],
            ],
            'comp_types' => array_keys($plan['comp_slugs']),
            'unmatched_houseguests' => $plan['unmatched_houseguests'],
            'unmatched_refs' => $plan['unmatched_refs'],
            'applied' => null,
        ];

        if ($mode === 'dry') {
            return $report;
        }

        // Refuse to write if any houseguest name couldn't be resolved.
        if (!empty($plan['unmatched_houseguests'])) {
            $report['aborted'] = 'unmatched houseguests — fix names/roster before writing';
            return $report;
        }

        $report['applied'] = $this->apply($seasonId, $plan, $mode === 'commit');

        if ($mode === 'commit' && ($opts['revalidate'] ?? true)) {
            $report['revalidated'] = $this->revalidate($seasonId, $roster);
        }

        return $report;
    }

    private function revalidate(int $seasonId, array $roster): array
    {
        $seasonSlug = $this->seasonSlug($seasonId);
        $count = 0;
        if ($seasonSlug) {
            Revalidation::revalidateSeason($seasonSlug);
            $count++;
        }
        foreach ($roster as $r) {
            if (!empty($r['slug'])) {
                Revalidation::revalidatePlayer($r['slug']);
                $count++;
            }
        }
        return ['calls' => $count, 'season_slug' => $seasonSlug];
    }
}
