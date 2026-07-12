<?php

namespace BigBrotherJunkies\Data\FanVotes;

/**
 * Data layer for fan-favorite ("AFP") voting: single standing vote per user
 * per season, daily lazy snapshots for history, and per-day mover notes.
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
    public const DB_VERSION        = '1.0.0';

    public const CACHE_GROUP = 'bbj_fanvotes';
    public const MIN_VOTERS  = 10;

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
        // Pure calendar arithmetic on the site-tz date string (no tz shift).
        $cutoff    = (new \DateTime($today))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

        $display     = self::rosterDisplay($seasonId);
        $counts      = self::voteCounts($seasonId);
        $totalVoters = self::totalVoters($seasonId);

        // Dense counts: every roster player, zeros included.
        $countsByPlayer = [];
        foreach (array_keys($display) as $pid) {
            $countsByPlayer[$pid] = $counts[$pid] ?? 0;
        }
        $sharesToday = FanVoteMath::shares($countsByPlayer, $totalVoters);

        // delta24h: live shares vs the latest snapshot STRICTLY before today.
        // No prior snapshot at all => all deltas 0 (NOT today's full share).
        $deltas = [];
        $prevDate = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(snap_date) FROM {$snapshots} WHERE season_id = %d AND snap_date < %s",
            $seasonId,
            $today
        ));
        if ($prevDate) {
            $prevRows = $wpdb->get_results($wpdb->prepare(
                "SELECT player_id, votes, total_voters FROM {$snapshots}
                  WHERE season_id = %d AND snap_date = %s",
                $seasonId,
                $prevDate
            ), ARRAY_A) ?: [];
            $prevCounts = [];
            $prevTotal  = 0;
            foreach ($prevRows as $r) {
                $prevCounts[(int) $r['player_id']] = (int) $r['votes'];
                $prevTotal = (int) $r['total_voters'];
            }
            $deltas = FanVoteMath::deltas($sharesToday, FanVoteMath::shares($prevCounts, $prevTotal));
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

        // Players list.
        $players = [];
        foreach ($display as $pid => $d) {
            $players[] = [
                'id'       => $pid,
                'name'     => $d['name'],
                'slug'     => $d['slug'],
                'photo'    => $d['photo'],
                'votes'    => $sharesToday[$pid]['votes'],
                'share'    => $sharesToday[$pid]['share'],
                'delta24h' => $deltas[$pid] ?? 0.0,
                'note'     => $notesByPlayer[$pid] ?? null,
            ];
        }
        usort($players, static function ($a, $b) {
            return ($b['votes'] <=> $a['votes']) ?: strcmp($a['name'], $b['name']);
        });

        // Series: one historical point per snap_date (< today), then today's
        // live numbers appended as the final point. Oldest -> newest.
        $histRows = $wpdb->get_results($wpdb->prepare(
            "SELECT snap_date, player_id, votes, total_voters FROM {$snapshots}
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
            $byDate[$date]['counts'][(int) $r['player_id']] = (int) $r['votes'];
        }
        $series = [];
        foreach ($byDate as $date => $g) {
            $series[] = [
                'date'         => $date,
                'total_voters' => $g['tv'],
                'shares'       => self::sharesMap($g['counts'], $g['tv']),
            ];
        }
        $series[] = [
            'date'         => $today,
            'total_voters' => $totalVoters,
            'shares'       => self::sharesMap($countsByPlayer, $totalVoters),
        ];

        $payload = [
            'success'      => true,
            'season_id'    => $seasonId,
            'season_slug'  => (string) get_post_field('post_name', $seasonId),
            'total_voters' => $totalVoters,
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
     * (0-vote players get a 0 row so the series stays dense). INSERT IGNORE on
     * the (season_id, snap_date, player_id) unique key makes it race-safe.
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
        $counts      = self::voteCounts($seasonId);
        $totalVoters = self::totalVoters($seasonId);

        foreach ($roster as $pid) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$snapshots} (season_id, snap_date, player_id, votes, total_voters)
                 VALUES (%d, %s, %d, %d, %d)",
                $seasonId,
                $today,
                $pid,
                (int) ($counts[$pid] ?? 0),
                $totalVoters
            ));
        }
    }
}
