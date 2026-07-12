<?php

namespace BigBrotherJunkies\Data\FanVotes;

/**
 * Data layer for fan-favorite ("AFP") voting: standing ranked ballots + heart
 * bonus votes, weighted points, daily lazy snapshots, mover notes.
 *
 * House rules honored here:
 *  - "today" is always current_time() (site tz, PT) — never date()/UTC.
 *  - Stored note text passes through wp_encode_emoji() (prod/staging wp_* is
 *    utf8mb3; raw emoji fails the INSERT).
 *  - No Revalidation / cache-tag / CF-purge calls — vote data is client-fetched.
 */
class FanVoteStore
{
    public const DB_VERSION_OPTION = 'bbj_fanvotes_db_version';
    public const DB_VERSION        = '1.1.0';

    public const CACHE_GROUP = 'bbj_fanvotes';
    public const MIN_VOTERS  = 10;

    /** Vote-weight multipliers by role slug; a user's weight = max across their roles. */
    public const ROLE_WEIGHTS = ['full_bean' => 3.0, 'supporter' => 2.0, 'lifetime' => 2.0];

    /** Tracker windows whose caches we invalidate after any write. */
    public const CACHE_DAYS = [7, 30, 120];

    // -----------------------------------------------------------------------
    // Migration
    // -----------------------------------------------------------------------

    public static function maybeMigrate(): void
    {
        if (version_compare(get_option(self::DB_VERSION_OPTION, '0'), self::DB_VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (FanVoteSchema::getAllSchemas() as $schema) {
            dbDelta($schema);
        }
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    // -----------------------------------------------------------------------
    // Writes
    // -----------------------------------------------------------------------

    /**
     * Cast (or move) the user's single vote for the current season.
     *
     * @return array{errors:string[]}|array{season_id:int,player_id:int}
     */
    public static function castVote(int $userId, int $playerId): array
    {
        global $wpdb;

        $seasonId = self::currentSeason();
        if (!$seasonId) {
            return ['errors' => ['No current season configured']];
        }
        if (!in_array($playerId, self::rosterIds($seasonId), true)) {
            return ['errors' => ["Player {$playerId} is not on the current season roster"]];
        }

        $votes = FanVoteSchema::table(FanVoteSchema::TABLE_VOTES);
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$votes} (user_id, season_id, player_id, updated_at)
             VALUES (%d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), updated_at = VALUES(updated_at)",
            $userId,
            $seasonId,
            $playerId,
            current_time('mysql')
        ));

        return ['season_id' => $seasonId, 'player_id' => $playerId];
    }

    /** The user's current-season vote (player_id) or null. */
    public static function myVote(int $userId): ?int
    {
        global $wpdb;

        $seasonId = self::currentSeason();
        if (!$seasonId) {
            return null;
        }
        $votes = FanVoteSchema::table(FanVoteSchema::TABLE_VOTES);
        $pid = $wpdb->get_var($wpdb->prepare(
            "SELECT player_id FROM {$votes} WHERE user_id = %d AND season_id = %d",
            $userId,
            $seasonId
        ));

        return $pid !== null ? (int) $pid : null;
    }

    // -----------------------------------------------------------------------
    // Ballots
    // -----------------------------------------------------------------------

    /**
     * Replace the user's standing ranked ballot for the current season.
     * Order = player_ids, rank 1 first. Only explicitly saved ballots score.
     *
     * @return array{errors:string[]}|array{season_id:int,count:int}
     */
    public static function saveBallot(int $userId, array $order): array
    {
        global $wpdb;

        $seasonId = self::currentSeason();
        if (!$seasonId) {
            return ['errors' => ['No current season configured']];
        }

        $order = array_values(array_unique(array_map('intval', $order)));
        if (!$order) {
            return ['errors' => ['order must be a non-empty list of player ids']];
        }
        if (count($order) > 200) {
            return ['errors' => ['Ballot too large']];
        }

        $roster = self::rosterIds($seasonId);
        $off = array_diff($order, $roster);
        if ($off) {
            return ['errors' => ['Players not on the current season roster: ' . implode(', ', $off)]];
        }

        $ballots = FanVoteSchema::table(FanVoteSchema::TABLE_BALLOTS);
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$ballots} WHERE user_id = %d AND season_id = %d",
            $userId,
            $seasonId
        ));
        $rank = 1;
        $ok = true;
        foreach ($order as $pid) {
            $res = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$ballots} (user_id, season_id, player_id, rank_pos, updated_at)
                 VALUES (%d, %d, %d, %d, %s)",
                $userId,
                $seasonId,
                $pid,
                $rank,
                $now
            ));
            $ok = $ok && $res !== false;
            $rank++;
        }
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return ['errors' => ['Could not save ballot']];
        }
        $wpdb->query('COMMIT');

        return ['season_id' => $seasonId, 'count' => count($order)];
    }

    /** The user's saved ballot for the current season: player_ids, rank 1 first. */
    public static function myBallot(int $userId): array
    {
        global $wpdb;

        $seasonId = self::currentSeason();
        if (!$seasonId) {
            return [];
        }
        $ballots = FanVoteSchema::table(FanVoteSchema::TABLE_BALLOTS);
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT player_id FROM {$ballots} WHERE user_id = %d AND season_id = %d ORDER BY rank_pos ASC",
            $userId,
            $seasonId
        )) ?: []);
    }

    /** Vote-weight multiplier for one user (current roles, max across ROLE_WEIGHTS). */
    public static function weightFor(int $userId): float
    {
        $w = self::userWeights([$userId]);
        return $w[$userId] ?? 1.0;
    }

    /**
     * Save (or clear) today's mover note for a player on the current season.
     * Empty note deletes the row. Returns false only when no current season.
     */
    public static function saveNote(int $playerId, string $note): bool
    {
        global $wpdb;

        $seasonId = self::currentSeason();
        if (!$seasonId) {
            return false;
        }

        $notes = FanVoteSchema::table(FanVoteSchema::TABLE_NOTES);
        $today = current_time('Y-m-d');

        // Cap to 140 real characters first, THEN encode — encoding after a raw
        // cap avoids slicing an emoji entity in half. utf8mb3 columns require
        // wp_encode_emoji() for any 4-byte character.
        $clean = sanitize_text_field($note);
        if (mb_strlen($clean) > 140) {
            $clean = mb_substr($clean, 0, 140);
        }
        $clean = wp_encode_emoji($clean);

        if ($clean === '') {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$notes} WHERE season_id = %d AND note_date = %s AND player_id = %d",
                $seasonId,
                $today,
                $playerId
            ));
            return true;
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$notes} (season_id, note_date, player_id, note)
             VALUES (%d, %s, %d, %s)
             ON DUPLICATE KEY UPDATE note = VALUES(note)",
            $seasonId,
            $today,
            $playerId,
            $clean
        ));

        return true;
    }

    // -----------------------------------------------------------------------
    // Aggregate read (the tracker page payload)
    // -----------------------------------------------------------------------

    /**
     * Everything the tracker page needs. Writes today's lazy snapshot if
     * missing (before reading the series / writing cache), then returns live
     * standings + daily history. Cached 60s per window.
     *
     * @return array When there is no current season: ['season_id'=>0, 'error'=>...].
     */
    public static function trackerPayload(int $days): array
    {
        global $wpdb;

        $seasonId = self::currentSeason();
        if (!$seasonId) {
            return ['season_id' => 0, 'error' => 'no_current_season'];
        }

        $days = max(1, min(365, $days));
        $cacheKey = 'bbjd_fanvote_tracker_' . $days;

        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        // Lazy snapshot BEFORE reading the series and BEFORE the cache write.
        self::ensureTodaySnapshot($seasonId);

        $snapshots = FanVoteSchema::table(FanVoteSchema::TABLE_SNAPSHOTS);
        $notesT    = FanVoteSchema::table(FanVoteSchema::TABLE_NOTES);
        $today     = current_time('Y-m-d');
        $cutoff    = (new \DateTime($today))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

        $display = self::rosterDisplay($seasonId);
        $tally   = self::tallyPoints($seasonId);
        $counts  = self::voteCounts($seasonId); // raw heart counts, still exposed per player

        // Dense points: every roster player, zeros included.
        $densePoints = [];
        foreach (array_keys($display) as $pid) {
            $densePoints[$pid] = $tally['points'][$pid] ?? 0.0;
        }
        $sharesToday = FanVoteMath::pointShares($densePoints, $tally['total_points']);

        // delta24h: live point-shares vs the latest snapshot STRICTLY before today.
        // Pre-v2 snapshot rows have points=0: fall back to heart-vote shares for those.
        $deltas = [];
        $prevDate = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(snap_date) FROM {$snapshots} WHERE season_id = %d AND snap_date < %s",
            $seasonId,
            $today
        ));
        if ($prevDate) {
            $prevRows = $wpdb->get_results($wpdb->prepare(
                "SELECT player_id, votes, total_voters, points, total_points FROM {$snapshots}
                  WHERE season_id = %d AND snap_date = %s",
                $seasonId,
                $prevDate
            ), ARRAY_A) ?: [];
            $prevPoints = [];
            $prevTotalPoints = 0.0;
            $prevCounts = [];
            $prevTotal  = 0;
            foreach ($prevRows as $r) {
                $prevPoints[(int) $r['player_id']] = (float) $r['points'];
                $prevTotalPoints = (float) $r['total_points'];
                $prevCounts[(int) $r['player_id']] = (int) $r['votes'];
                $prevTotal = (int) $r['total_voters'];
            }
            $prevShares = $prevTotalPoints > 0
                ? FanVoteMath::pointShares($prevPoints, $prevTotalPoints)
                : FanVoteMath::shares($prevCounts, $prevTotal);
            $deltas = FanVoteMath::deltas($sharesToday, $prevShares);
        }

        // Today's mover notes.
        $noteRows = $wpdb->get_results($wpdb->prepare(
            "SELECT player_id, note FROM {$notesT} WHERE season_id = %d AND note_date = %s",
            $seasonId,
            $today
        ), ARRAY_A) ?: [];
        $notesByPlayer = [];
        foreach ($noteRows as $r) {
            $notesByPlayer[(int) $r['player_id']] = (string) $r['note'];
        }

        // Players list — sorted by points desc, then name asc.
        $players = [];
        foreach ($display as $pid => $d) {
            $players[] = [
                'id'       => $pid,
                'name'     => $d['name'],
                'slug'     => $d['slug'],
                'photo'    => $d['photo'],
                'votes'    => (int) ($counts[$pid] ?? 0),
                'points'   => $sharesToday[$pid]['points'],
                'share'    => $sharesToday[$pid]['share'],
                'delta24h' => $deltas[$pid] ?? 0.0,
                'note'     => $notesByPlayer[$pid] ?? null,
            ];
        }
        usort($players, static function ($a, $b) {
            return ($b['points'] <=> $a['points']) ?: strcmp($a['name'], $b['name']);
        });

        // Series: one historical point per snap_date (< today), then today's live
        // numbers appended as the final point. Oldest -> newest. Legacy rows
        // (points all 0 but votes present) fall back to heart-vote shares.
        $histRows = $wpdb->get_results($wpdb->prepare(
            "SELECT snap_date, player_id, votes, total_voters, points, total_points FROM {$snapshots}
              WHERE season_id = %d AND snap_date >= %s AND snap_date < %s
              ORDER BY snap_date ASC",
            $seasonId,
            $cutoff,
            $today
        ), ARRAY_A) ?: [];
        $byDate = [];
        foreach ($histRows as $r) {
            $date = $r['snap_date'];
            $byDate[$date]['tv'] = (int) $r['total_voters'];
            $byDate[$date]['tp'] = (float) $r['total_points'];
            $byDate[$date]['counts'][(int) $r['player_id']] = (int) $r['votes'];
            $byDate[$date]['points'][(int) $r['player_id']] = (float) $r['points'];
        }
        $series = [];
        foreach ($byDate as $date => $g) {
            $series[] = [
                'date'         => $date,
                'total_voters' => $g['tv'],
                'shares'       => $g['tp'] > 0
                    ? self::pointSharesMap($g['points'], $g['tp'])
                    : self::sharesMap($g['counts'], $g['tv']),
            ];
        }
        $series[] = [
            'date'         => $today,
            'total_voters' => $tally['total_voters'],
            'shares'       => self::pointSharesMap($densePoints, $tally['total_points']),
        ];

        $payload = [
            'success'      => true,
            'season_id'    => $seasonId,
            'season_slug'  => (string) get_post_field('post_name', $seasonId),
            'total_voters' => $tally['total_voters'],
            'total_points' => $tally['total_points'],
            'min_voters'   => self::MIN_VOTERS,
            'as_of'        => $today,
            'players'      => $players,
            'series'       => $series,
        ];

        wp_cache_set($cacheKey, $payload, self::CACHE_GROUP, 60);

        return $payload;
    }

    /** Invalidate every tracker-window cache after a write. */
    public static function bustTrackerCaches(): void
    {
        foreach (self::CACHE_DAYS as $d) {
            wp_cache_delete('bbjd_fanvote_tracker_' . $d, self::CACHE_GROUP);
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private static function currentSeason(): int
    {
        return (int) get_option('bbj_v2_current_season', 0);
    }

    /** Roster player post IDs for a season. */
    private static function rosterIds(int $seasonId): array
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT bbj_player FROM {$wpdb->prefix}bbj_v2_player_season WHERE bbj_season = %d",
            $seasonId
        )) ?: []);
    }

    /** Roster display fields keyed by player id (name asc order). */
    private static function rosterDisplay(int $seasonId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID AS id, p.post_title AS name, p.post_name AS slug, bp.profile_picture
               FROM {$wpdb->prefix}bbj_v2_player_season ps
               INNER JOIN {$wpdb->posts} p ON p.ID = ps.bbj_player
               LEFT JOIN {$wpdb->prefix}bbj_players bp
                      ON (bp.post_id = p.ID OR (bp.id = p.ID AND bp.post_id = 0))
              WHERE ps.bbj_season = %d
              ORDER BY p.post_title ASC",
            $seasonId
        ), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $pid = (int) $r['id'];
            $pic = (int) ($r['profile_picture'] ?? 0);
            $out[$pid] = [
                'id'    => $pid,
                'name'  => (string) $r['name'],
                'slug'  => (string) $r['slug'],
                'photo' => $pic ? (wp_get_attachment_image_url($pic, 'thumbnail') ?: '') : '',
            ];
        }
        return $out;
    }

    /** player_id => vote count for the season. */
    private static function voteCounts(int $seasonId): array
    {
        global $wpdb;
        $votes = FanVoteSchema::table(FanVoteSchema::TABLE_VOTES);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT player_id, COUNT(*) c FROM {$votes} WHERE season_id = %d GROUP BY player_id",
            $seasonId
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['player_id']] = (int) $r['c'];
        }
        return $out;
    }

    private static function totalVoters(int $seasonId): int
    {
        global $wpdb;
        $votes = FanVoteSchema::table(FanVoteSchema::TABLE_VOTES);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$votes} WHERE season_id = %d",
            $seasonId
        ));
    }

    /**
     * user_id => weight for a set of users, resolved from wp capabilities in ONE query.
     * Users missing from the result (or with no weighted role) default to 1.0 at the caller.
     *
     * @param int[] $userIds
     * @return array<int,float>
     */
    private static function userWeights(array $userIds): array
    {
        global $wpdb;

        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (!$userIds) {
            return [];
        }
        $ids = implode(',', $userIds);
        $rows = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta}
              WHERE meta_key = '{$wpdb->prefix}capabilities' AND user_id IN ({$ids})",
            ARRAY_A
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $caps = maybe_unserialize($r['meta_value']);
            $w = 1.0;
            if (is_array($caps)) {
                foreach (self::ROLE_WEIGHTS as $role => $weight) {
                    if (!empty($caps[$role])) {
                        $w = max($w, $weight);
                    }
                }
            }
            $out[(int) $r['user_id']] = $w;
        }
        return $out;
    }

    /**
     * Live weighted standings for a season.
     *
     * @return array{points:array<int,float>,total_points:float,total_voters:int}
     */
    private static function tallyPoints(int $seasonId): array
    {
        global $wpdb;

        $ballotsT = FanVoteSchema::table(FanVoteSchema::TABLE_BALLOTS);
        $votesT   = FanVoteSchema::table(FanVoteSchema::TABLE_VOTES);

        $ballotRows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, player_id FROM {$ballotsT}
              WHERE season_id = %d ORDER BY user_id ASC, rank_pos ASC",
            $seasonId
        ), ARRAY_A) ?: [];
        $ballots = [];
        foreach ($ballotRows as $r) {
            $ballots[(int) $r['user_id']][] = (int) $r['player_id'];
        }

        $heartRows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, player_id FROM {$votesT} WHERE season_id = %d",
            $seasonId
        ), ARRAY_A) ?: [];
        $hearts = [];
        foreach ($heartRows as $r) {
            $hearts[(int) $r['user_id']] = (int) $r['player_id'];
        }

        $participants = array_values(array_unique(array_merge(array_keys($ballots), array_keys($hearts))));
        $weights = self::userWeights($participants);

        $points = FanVoteMath::tally($ballots, $hearts, $weights);
        return [
            'points'       => $points,
            'total_points' => round(array_sum($points), 1),
            'total_voters' => count($participants),
        ];
    }

    /** {player_id => share} from points (legacy fallback path uses sharesMap on votes). */
    private static function pointSharesMap(array $pointsByPlayer, float $totalPoints): array
    {
        $out = [];
        foreach (FanVoteMath::pointShares($pointsByPlayer, $totalPoints) as $pid => $v) {
            $out[$pid] = $v['share'];
        }
        return $out;
    }

    /** {player_id => share} from raw counts + total voters. */
    private static function sharesMap(array $countsByPlayer, int $totalVoters): array
    {
        $out = [];
        foreach (FanVoteMath::shares($countsByPlayer, $totalVoters) as $pid => $v) {
            $out[$pid] = $v['share'];
        }
        return $out;
    }

    /**
     * Write today's snapshot once per season/day: one row per roster player
     * (0-point players get a 0 row so the series stays dense), including the
     * weighted points/total_points. INSERT IGNORE on the (season_id, snap_date,
     * player_id) unique key makes it race-safe.
     */
    private static function ensureTodaySnapshot(int $seasonId): void
    {
        global $wpdb;
        $snapshots = FanVoteSchema::table(FanVoteSchema::TABLE_SNAPSHOTS);
        $today = current_time('Y-m-d');

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$snapshots} WHERE season_id = %d AND snap_date = %s",
            $seasonId,
            $today
        ));
        if ($exists > 0) {
            return;
        }

        $roster = self::rosterIds($seasonId);
        if (!$roster) {
            return;
        }
        $counts = self::voteCounts($seasonId);
        $tally  = self::tallyPoints($seasonId);

        foreach ($roster as $pid) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$snapshots}
                    (season_id, snap_date, player_id, votes, total_voters, points, total_points)
                 VALUES (%d, %s, %d, %d, %d, %f, %f)",
                $seasonId,
                $today,
                $pid,
                (int) ($counts[$pid] ?? 0),
                $tally['total_voters'],
                (float) ($tally['points'][$pid] ?? 0.0),
                $tally['total_points']
            ));
        }
    }
}
