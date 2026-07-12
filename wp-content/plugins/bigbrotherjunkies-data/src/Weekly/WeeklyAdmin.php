<?php

namespace BigBrotherJunkies\Data\Weekly;

/**
 * Admin write path for the weekly tables (bbj_weeks / bbj_weeks_players /
 * bbj_week_comps). The pure planner mirrors WeeklyImporter's row semantics:
 * a veto-saved nominee keeps nom=1, voted_for = who they voted to EVICT,
 * saved_by_player_id = the veto winner.
 */
class WeeklyAdmin
{
    /**
     * Pure: convert an admin week-editor payload into normalized row arrays.
     *
     * Payload (all player refs are WP post IDs):
     *   active: int[]                  players in the house this week
     *   hoh: ?int, pov: ?int
     *   noms: int[]                    ALL nominees this week (incl. replacement)
     *   veto_used_on: ?int             nominee saved by the veto (must be in noms)
     *   evicted: int[]                 evictees (supports double evictions/expulsions)
     *   havenot: int[]                 Have-Nots for the week
     *   veto_played: int[]             players who competed in the veto comp
     *   votes: array<int|string,int>   voter_id => voted-to-evict id
     *   misc_comps: array<array{player_id:int, comp_type_id:int, notes?:string}>
     *
     * @return array{players: array<int,array>, comps: array<int,array>, errors: string[]}
     */
    public static function planWeekRows(array $payload, array $rosterIds): array
    {
        $roster = array_flip(array_map('intval', $rosterIds));
        $inRoster = static fn ($id): bool => isset($roster[(int) $id]);
        $errors = [];

        $hoh        = (int) ($payload['hoh'] ?? 0) ?: null;
        $pov        = (int) ($payload['pov'] ?? 0) ?: null;
        $vetoUsedOn = (int) ($payload['veto_used_on'] ?? 0) ?: null;
        $noms    = array_values(array_unique(array_map('intval', (array) ($payload['noms'] ?? []))));
        $evicted = array_values(array_unique(array_map('intval', (array) ($payload['evicted'] ?? []))));
        $havenot = array_values(array_unique(array_map('intval', (array) ($payload['havenot'] ?? []))));
        $vetoPlayed = array_values(array_unique(array_map('intval', (array) ($payload['veto_played'] ?? []))));
        $active  = array_values(array_unique(array_map('intval', (array) ($payload['active'] ?? []))));

        $votes = [];
        foreach ((array) ($payload['votes'] ?? []) as $voter => $for) {
            if ((int) $voter && (int) $for) {
                $votes[(int) $voter] = (int) $for;
            }
        }

        foreach ([['hoh', $hoh], ['pov', $pov], ['veto_used_on', $vetoUsedOn]] as [$label, $id]) {
            if ($id !== null && !$inRoster($id)) {
                $errors[] = "{$label}: player {$id} is not on this season's roster";
            }
        }
        foreach (['noms' => $noms, 'evicted' => $evicted, 'havenot' => $havenot, 'veto_played' => $vetoPlayed, 'active' => $active] as $label => $ids) {
            foreach ($ids as $id) {
                if (!$inRoster($id)) {
                    $errors[] = "{$label}: player {$id} is not on this season's roster";
                }
            }
        }
        foreach ($votes as $voter => $for) {
            if (!$inRoster($voter)) $errors[] = "votes: voter {$voter} is not on this season's roster";
            if (!$inRoster($for))   $errors[] = "votes: target {$for} is not on this season's roster";
        }

        if ($vetoUsedOn !== null && $pov === null) {
            $errors[] = 'veto_used_on requires a pov winner';
        }
        if ($vetoUsedOn !== null && !in_array($vetoUsedOn, $noms, true)) {
            $errors[] = 'veto_used_on must be one of the nominees';
        }

        $comps = [];
        if ($hoh !== null) $comps[] = ['player_id' => $hoh, 'slug' => 'hoh', 'comp_type_id' => null, 'notes' => null];
        if ($pov !== null) $comps[] = ['player_id' => $pov, 'slug' => 'pov', 'comp_type_id' => null, 'notes' => null];
        foreach ((array) ($payload['misc_comps'] ?? []) as $mc) {
            $pid  = (int) ($mc['player_id'] ?? 0);
            $ctid = (int) ($mc['comp_type_id'] ?? 0);
            if (!$pid || !$ctid) {
                continue; // blank UI row
            }
            if (!$inRoster($pid)) {
                $errors[] = "misc_comps: player {$pid} is not on this season's roster";
                continue;
            }
            $notes = trim((string) ($mc['notes'] ?? ''));
            $comps[] = [
                'player_id' => $pid,
                'slug' => null,
                'comp_type_id' => $ctid,
                'notes' => $notes === '' ? null : substr($notes, 0, 140),
            ];
        }

        if ($errors) {
            return ['players' => [], 'comps' => [], 'errors' => array_values(array_unique($errors))];
        }

        // Row set: every active player, plus anyone referenced (safety net for a
        // short active list — an evictee must always land a row).
        $rowIds = $active;
        $referenced = array_merge([$hoh, $pov, $vetoUsedOn], $noms, $evicted, $havenot, $vetoPlayed, array_keys($votes));
        foreach ($referenced as $id) {
            if ($id !== null && $id !== 0 && !in_array($id, $rowIds, true)) {
                $rowIds[] = $id;
            }
        }

        $players = [];
        foreach ($rowIds as $pid) {
            $players[] = [
                'player_id'   => $pid,
                'nom'         => in_array($pid, $noms, true) ? 1 : 0,
                'evicted'     => in_array($pid, $evicted, true) ? 1 : 0,
                'havenot'     => in_array($pid, $havenot, true) ? 1 : 0,
                'veto_played' => in_array($pid, $vetoPlayed, true) ? 1 : 0,
                'voted_for'   => $votes[$pid] ?? 0,
                'saved_by'    => ($vetoUsedOn !== null && $pid === $vetoUsedOn) ? $pov : null,
                'active'      => 1,
            ];
        }

        return ['players' => $players, 'comps' => $comps, 'errors' => []];
    }

    // -----------------------------------------------------------------------
    // DB layer (thin wrappers around the pure planner)
    // -----------------------------------------------------------------------

    /**
     * Rollout switch: while '0' (default), admin week saves/deletes write the
     * weekly rows (timelines/faces grids update) but do NOT recompute the
     * bbj_v2_player_season stat totals — the manually-maintained totals stay
     * authoritative. Flip to '1' (wp option update bbj_weekly_recompute_enabled 1)
     * once totalsDiff() confirms weekly-derived totals match the live stats.
     */
    public const RECOMPUTE_OPTION = 'bbj_weekly_recompute_enabled';

    public static function recomputeEnabled(): bool
    {
        return (string) get_option(self::RECOMPUTE_OPTION, '0') === '1';
    }

    private static function t(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /** Roster player IDs for a season, from player_season. */
    private static function rosterIds(int $seasonId): array
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT bbj_player FROM {$wpdb->prefix}bbj_v2_player_season WHERE bbj_season = %d",
            $seasonId
        )) ?: []);
    }

    /**
     * Everything the admin weekly editor needs for one season, in one call.
     */
    public static function getAdminBundle(int $seasonId): array
    {
        global $wpdb;
        $weeks    = self::t(WeeklySchema::TABLE_WEEKS);
        $wplayers = self::t(WeeklySchema::TABLE_WEEKS_PLAYERS);
        $wcomps   = self::t(WeeklySchema::TABLE_WEEK_COMPS);
        $ctypes   = self::t(WeeklySchema::TABLE_COMP_TYPES);

        $rosterRows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID AS id, p.post_title AS name, p.post_name AS slug, bp.profile_picture
               FROM {$wpdb->prefix}bbj_v2_player_season ps
               INNER JOIN {$wpdb->posts} p ON p.ID = ps.bbj_player
               LEFT JOIN {$wpdb->prefix}bbj_players bp
                      ON (bp.post_id = p.ID OR (bp.id = p.ID AND bp.post_id = 0))
              WHERE ps.bbj_season = %d
              ORDER BY p.post_title ASC",
            $seasonId
        ), ARRAY_A) ?: [];
        $roster = array_map(static function ($r) {
            $pic = (int) ($r['profile_picture'] ?? 0);
            return [
                'id'    => (int) $r['id'],
                'name'  => (string) $r['name'],
                'slug'  => (string) $r['slug'],
                'photo' => $pic ? (wp_get_attachment_image_url($pic, 'thumbnail') ?: '') : '',
            ];
        }, $rosterRows);

        $compTypes = array_map(static fn ($r) => [
            'id' => (int) $r['id'], 'slug' => $r['slug'], 'name' => $r['name'],
        ], $wpdb->get_results(
            "SELECT id, slug, name FROM {$ctypes} WHERE is_archived = 0 ORDER BY sort_order ASC, name ASC",
            ARRAY_A
        ) ?: []);

        $weekRows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, week_num, start_date, end_date, summary
               FROM {$weeks} WHERE season_id = %d ORDER BY week_num ASC",
            $seasonId
        ), ARRAY_A) ?: [];

        $out = [];
        $byWeekId = [];
        foreach ($weekRows as $w) {
            $wid = (int) $w['id'];
            $out[$wid] = [
                'id'         => $wid,
                'week_num'   => (int) $w['week_num'],
                'start_date' => $w['start_date'],
                'end_date'   => $w['end_date'],
                'summary'    => (string) ($w['summary'] ?? ''),
                'players'    => [],
                'comps'      => [],
            ];
            $byWeekId[] = $wid;
        }

        if ($byWeekId) {
            $in = implode(',', $byWeekId);
            $playerRows = $wpdb->get_results(
                "SELECT week_id, player_id, nom, evicted, havenot, veto_played, voted_for,
                        saved_by_player_id, vote_to_win, vote_to_win_for, active
                   FROM {$wplayers} WHERE week_id IN ({$in})",
                ARRAY_A
            ) ?: [];
            foreach ($playerRows as $r) {
                $out[(int) $r['week_id']]['players'][] = [
                    'player_id'          => (int) $r['player_id'],
                    'nom'                => (int) $r['nom'],
                    'evicted'            => (int) $r['evicted'],
                    'havenot'            => (int) $r['havenot'],
                    'veto_played'        => (int) $r['veto_played'],
                    'voted_for'          => (int) $r['voted_for'],
                    'saved_by_player_id' => $r['saved_by_player_id'] !== null ? (int) $r['saved_by_player_id'] : null,
                    'vote_to_win'        => (int) $r['vote_to_win'],
                    'vote_to_win_for'    => $r['vote_to_win_for'] !== null ? (int) $r['vote_to_win_for'] : null,
                    'active'             => (int) $r['active'],
                ];
            }
            $compRows = $wpdb->get_results(
                "SELECT wc.week_id, wc.player_id, wc.comp_type_id, wc.notes, ct.slug
                   FROM {$wcomps} wc
                   INNER JOIN {$ctypes} ct ON ct.id = wc.comp_type_id
                  WHERE wc.week_id IN ({$in})",
                ARRAY_A
            ) ?: [];
            foreach ($compRows as $r) {
                $out[(int) $r['week_id']]['comps'][] = [
                    'player_id'    => (int) $r['player_id'],
                    'comp_type_id' => (int) $r['comp_type_id'],
                    'slug'         => (string) $r['slug'],
                    'notes'        => $r['notes'],
                ];
            }
        }

        return [
            'season_id'  => $seasonId,
            'roster'     => $roster,
            'comp_types' => $compTypes,
            'weeks'      => array_values($out),
        ];
    }

    public static function createWeek(int $seasonId, ?string $startDate, ?string $endDate): array
    {
        global $wpdb;
        $weeks = self::t(WeeklySchema::TABLE_WEEKS);
        $next = 1 + (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(week_num), 0) FROM {$weeks} WHERE season_id = %d",
            $seasonId
        ));
        // Legacy copies of this table have NOT NULL date columns (the canonical
        // schema allows NULL) — omit empty dates instead of writing NULL.
        $row = ['season_id' => $seasonId, 'week_num' => $next];
        if ($startDate) $row['start_date'] = $startDate;
        if ($endDate)   $row['end_date']   = $endDate;
        $wpdb->insert($weeks, $row);
        return ['id' => (int) $wpdb->insert_id, 'week_num' => $next];
    }

    private static function compTypeId(string $slug, string $name): int
    {
        global $wpdb;
        $table = self::t(WeeklySchema::TABLE_COMP_TYPES);
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($id) {
            return (int) $id;
        }
        $wpdb->insert($table, ['slug' => $slug, 'name' => $name, 'sort_order' => 40]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Replace one week's rows from an editor payload (delete+insert in a
     * transaction), recompute season totals, and report which players changed.
     * Optional payload keys besides the planner's: summary, start_date,
     * end_date, jury_votes (juror_id => vote_to_win_for id, finale only).
     */
    public static function saveWeek(int $weekId, array $payload): array
    {
        global $wpdb;
        $weeks    = self::t(WeeklySchema::TABLE_WEEKS);
        $wplayers = self::t(WeeklySchema::TABLE_WEEKS_PLAYERS);
        $wcomps   = self::t(WeeklySchema::TABLE_WEEK_COMPS);

        $week = $wpdb->get_row($wpdb->prepare(
            "SELECT id, season_id FROM {$weeks} WHERE id = %d", $weekId
        ), ARRAY_A);
        if (!$week) {
            return ['errors' => ['Week not found']];
        }
        $seasonId = (int) $week['season_id'];

        $plan = self::planWeekRows($payload, self::rosterIds($seasonId));
        if ($plan['errors']) {
            return ['errors' => $plan['errors']];
        }

        $oldIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT player_id FROM {$wplayers} WHERE week_id = %d
              UNION SELECT player_id FROM {$wcomps} WHERE week_id = %d",
            $weekId, $weekId
        )) ?: []);

        $juryVotes = [];
        foreach ((array) ($payload['jury_votes'] ?? []) as $juror => $for) {
            if ((int) $juror && (int) $for) {
                $juryVotes[(int) $juror] = (int) $for;
            }
        }

        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wcomps} WHERE week_id = %d", $weekId));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wplayers} WHERE week_id = %d", $weekId));

            foreach ($plan['players'] as $p) {
                $wpdb->insert($wplayers, [
                    'week_id'            => $weekId,
                    'player_id'          => $p['player_id'],
                    'season_id'          => $seasonId,
                    'nom'                => $p['nom'],
                    'evicted'            => $p['evicted'],
                    'havenot'            => $p['havenot'],
                    'veto_played'        => $p['veto_played'],
                    'voted_for'          => $p['voted_for'],
                    'saved_by_player_id' => $p['saved_by'],
                    'vote_to_win'        => 0,
                    'active'             => $p['active'],
                ]);
            }

            foreach ($plan['comps'] as $c) {
                $ctid = $c['comp_type_id']
                    ?? self::compTypeId($c['slug'], $c['slug'] === 'hoh' ? 'HOH' : 'Power of Veto');
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wcomps} (week_id, player_id, comp_type_id, notes)
                     VALUES (%d, %d, %d, %s)",
                    $weekId, $c['player_id'], $ctid, (string) ($c['notes'] ?? '')
                ));
            }

            $meta = [];
            if (array_key_exists('summary', $payload)) {
                $meta['summary'] = sanitize_textarea_field((string) $payload['summary']);
            }
            // Empty dates are skipped, not nulled — legacy tables have NOT NULL
            // date columns, so "clear a date" isn't supported (re-set it instead).
            if (!empty($payload['start_date'])) $meta['start_date'] = $payload['start_date'];
            if (!empty($payload['end_date']))   $meta['end_date']   = $payload['end_date'];
            if ($meta) {
                $wpdb->update($weeks, $meta, ['id' => $weekId]);
            }

            // Finale: jury votes live on each juror's own eviction-week row.
            if ($juryVotes) {
                $winnerId = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT season_winner FROM {$wpdb->prefix}bbj_seasons WHERE id = %d",
                    $seasonId
                ));
                foreach ($juryVotes as $juror => $for) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wplayers} SET vote_to_win_for = %d, vote_to_win = %d
                          WHERE season_id = %d AND player_id = %d AND evicted = 1",
                        $for, ($winnerId && $for === $winnerId) ? 1 : 0, $seasonId, $juror
                    ));
                }
            }

            if (self::recomputeEnabled()) {
                WeeklyData::recomputeSeasonTotals($seasonId);
                self::recomputeHavenotTotals($seasonId);
                self::recomputeVetoPlayedTotals($seasonId);
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return ['errors' => ['Save failed: ' . $e->getMessage()]];
        }

        if (self::recomputeEnabled()) {
            self::bustSeasonStatCaches($seasonId);
        }

        $newIds = array_map(static fn ($p) => $p['player_id'], $plan['players']);
        $changed = array_values(array_unique(array_merge($oldIds, $newIds, array_keys($juryVotes))));

        return ['season_id' => $seasonId, 'changed_player_ids' => $changed];
    }

    /** Delete a week and its rows; recompute totals. Null if week not found. */
    public static function deleteWeek(int $weekId): ?array
    {
        global $wpdb;
        $weeks    = self::t(WeeklySchema::TABLE_WEEKS);
        $wplayers = self::t(WeeklySchema::TABLE_WEEKS_PLAYERS);
        $wcomps   = self::t(WeeklySchema::TABLE_WEEK_COMPS);

        $week = $wpdb->get_row($wpdb->prepare(
            "SELECT id, season_id FROM {$weeks} WHERE id = %d", $weekId
        ), ARRAY_A);
        if (!$week) {
            return null;
        }
        $seasonId = (int) $week['season_id'];

        $oldIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT player_id FROM {$wplayers} WHERE week_id = %d
              UNION SELECT player_id FROM {$wcomps} WHERE week_id = %d",
            $weekId, $weekId
        )) ?: []);

        $wpdb->query('START TRANSACTION');
        $wpdb->query($wpdb->prepare("DELETE FROM {$wcomps} WHERE week_id = %d", $weekId));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wplayers} WHERE week_id = %d", $weekId));
        $wpdb->query($wpdb->prepare("DELETE FROM {$weeks} WHERE id = %d", $weekId));
        if (self::recomputeEnabled()) {
            WeeklyData::recomputeSeasonTotals($seasonId);
            self::recomputeHavenotTotals($seasonId);
            self::recomputeVetoPlayedTotals($seasonId);
        }
        $wpdb->query('COMMIT');
        if (self::recomputeEnabled()) {
            self::bustSeasonStatCaches($seasonId);
        }

        return ['season_id' => $seasonId, 'changed_player_ids' => $oldIds];
    }

    /**
     * The homepage/season stat blocks read player rows through the bbj_v2
     * object-cache group — recomputed totals stay invisible until it's busted.
     */
    private static function bustSeasonStatCaches(int $seasonId): void
    {
        if (function_exists('bbj_spoiler_bar_bust_cache')) {
            bbj_spoiler_bar_bust_cache($seasonId);
        }
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('bbj_v2');
        }
    }

    /**
     * Write a per-season stat total from a weekly boolean flag — but ONLY if
     * this season has any of that flag set at all. Historical seasons were
     * imported without havenot/veto-played tracking; recomputing them would
     * zero their manually maintained totals.
     */
    private static function recomputeFlagTotals(int $seasonId, string $flagColumn, string $totalColumn): int
    {
        global $wpdb;
        $wplayers = self::t(WeeklySchema::TABLE_WEEKS_PLAYERS);
        $pseason  = self::t('bbj_v2_player_season');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT player_id, SUM({$flagColumn}) AS flags
               FROM {$wplayers} WHERE season_id = %d GROUP BY player_id",
            $seasonId
        ), ARRAY_A) ?: [];

        $seasonHasData = false;
        foreach ($rows as $r) {
            if ((int) $r['flags'] > 0) {
                $seasonHasData = true;
                break;
            }
        }
        if (!$seasonHasData) {
            return 0;
        }

        $updated = 0;
        foreach ($rows as $r) {
            $updated += (int) $wpdb->update(
                $pseason,
                [$totalColumn => (int) $r['flags']],
                ['bbj_player' => (int) $r['player_id'], 'bbj_season' => $seasonId]
            );
        }
        return $updated;
    }

    public static function recomputeHavenotTotals(int $seasonId): int
    {
        return self::recomputeFlagTotals($seasonId, 'havenot', 'bbj_total_havenot');
    }

    public static function recomputeVetoPlayedTotals(int $seasonId): int
    {
        return self::recomputeFlagTotals($seasonId, 'veto_played', 'bbj_veto_played');
    }

    /**
     * Read-only comparison of weekly-derived totals vs the live
     * bbj_v2_player_season stat columns. Used to verify the weekly data
     * before flipping RECOMPUTE_OPTION on. Writes nothing.
     *
     * @return array{season_id:int, mismatches:int, players:array<int,array>}
     */
    public static function totalsDiff(int $seasonId): array
    {
        global $wpdb;
        $weeks    = self::t(WeeklySchema::TABLE_WEEKS);
        $wplayers = self::t(WeeklySchema::TABLE_WEEKS_PLAYERS);
        $wcomps   = self::t(WeeklySchema::TABLE_WEEK_COMPS);
        $ctypes   = self::t(WeeklySchema::TABLE_COMP_TYPES);
        $pseason  = self::t('bbj_v2_player_season');

        // Same sources as WeeklyData::recomputeSeasonTotals, without writing.
        $compRows = $wpdb->get_results($wpdb->prepare(
            "SELECT wc.player_id, ct.slug
               FROM {$wcomps} wc
               INNER JOIN {$weeks} w   ON w.id  = wc.week_id
               INNER JOIN {$ctypes} ct ON ct.id = wc.comp_type_id
              WHERE w.season_id = %d",
            $seasonId
        ), ARRAY_A) ?: [];
        $nomRows = $wpdb->get_results($wpdb->prepare(
            "SELECT wp.player_id, SUM(wp.nom) AS noms, SUM(wp.evicted) AS evicted
               FROM {$wplayers} wp WHERE wp.season_id = %d GROUP BY wp.player_id",
            $seasonId
        ), ARRAY_A) ?: [];
        $derived = WeeklyData::countTotalsFromRows($compRows, $nomRows);

        $flagSums = [];
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT player_id, SUM(havenot) AS havenots, SUM(veto_played) AS veto_played
               FROM {$wplayers} WHERE season_id = %d GROUP BY player_id",
            $seasonId
        ), ARRAY_A) ?: [] as $r) {
            $flagSums[(int) $r['player_id']] = [
                'havenot'     => (int) $r['havenots'],
                'veto_played' => (int) $r['veto_played'],
            ];
        }

        $current = $wpdb->get_results($wpdb->prepare(
            "SELECT ps.bbj_player, p.post_title AS name,
                    ps.bbj_total_hoh, ps.bbj_total_pov, ps.bbj_total_misc,
                    ps.bbj_total_nom, ps.bbj_total_havenot, ps.bbj_veto_played
               FROM {$pseason} ps
               INNER JOIN {$wpdb->posts} p ON p.ID = ps.bbj_player
              WHERE ps.bbj_season = %d
              ORDER BY p.post_title ASC",
            $seasonId
        ), ARRAY_A) ?: [];

        $players = [];
        $mismatches = 0;
        foreach ($current as $c) {
            $pid = (int) $c['bbj_player'];
            $d = $derived[$pid] ?? ['hoh' => 0, 'pov' => 0, 'misc' => 0, 'nom' => 0];
            $d['havenot']     = $flagSums[$pid]['havenot'] ?? 0;
            $d['veto_played'] = $flagSums[$pid]['veto_played'] ?? 0;
            $cur = [
                'hoh'         => (int) $c['bbj_total_hoh'],
                'pov'         => (int) $c['bbj_total_pov'],
                'misc'        => (int) $c['bbj_total_misc'],
                'nom'         => (int) $c['bbj_total_nom'],
                'havenot'     => (int) $c['bbj_total_havenot'],
                'veto_played' => (int) $c['bbj_veto_played'],
            ];
            $match = $d == $cur;
            if (!$match) {
                $mismatches++;
            }
            $players[] = [
                'player_id' => $pid,
                'name'      => $c['name'],
                'derived'   => $d,
                'current'   => $cur,
                'match'     => $match,
            ];
        }

        return ['season_id' => $seasonId, 'mismatches' => $mismatches, 'players' => $players];
    }
}
