<?php

namespace BigBrotherJunkies\Data\Social;

use BigBrotherJunkies\Data\BrandSafety\Log;
use BigBrotherJunkies\Data\BrandSafety\Scanner;

/**
 * Approved-quote persistence. Candidates never land here — only rows Steve
 * approved. quote_text is censored (brand-safety) then wp_encode_emoji'd at
 * rest (house rule), decoded on the way out.
 */
class QuoteStore
{
    public static function insert(array $args): int
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);

        $quoteCensored = Scanner::censor((string) $args['quote_text']);
        $contextCensored = Scanner::censor((string) ($args['context'] ?? ''));

        $ok = $wpdb->insert($table, [
            'season_id'    => (int) $args['season_id'],
            'player_id'    => isset($args['player_id']) && (int) $args['player_id'] > 0 ? (int) $args['player_id'] : null,
            'quote_text'   => wp_encode_emoji($quoteCensored['html']),
            'context'      => wp_encode_emoji($contextCensored['html']),
            'said_on'      => (string) $args['said_on'],
            'source_count' => max(1, (int) ($args['source_count'] ?? 1)),
            'created_at'   => gmdate('Y-m-d H:i:s'),
        ]);

        $insertId = $ok ? (int) $wpdb->insert_id : 0;

        // Log::record() issues its own $wpdb->insert() calls, which would
        // otherwise clobber $wpdb->insert_id before we read it above.
        if ($ok) {
            Log::record('quote', $insertId, array_merge($quoteCensored['hits'], $contextCensored['hits']));
        }

        return $insertId;
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
        return (bool) $wpdb->delete($table, ['id' => $id]);
    }

    public static function get(int $id): ?array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        return $row ? self::decode($row) : null;
    }

    public static function forSeason(int $seasonId, int $limit = 50): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE season_id = %d ORDER BY said_on DESC, id DESC LIMIT %d",
            $seasonId,
            max(1, min(200, $limit))
        ), ARRAY_A) ?: [];
        return array_map([self::class, 'withPlayer'], array_map([self::class, 'decode'], $rows));
    }

    public static function forPlayer(int $playerId, int $limit = 50): array
    {
        global $wpdb;
        $table = SocialSchema::table(SocialSchema::TABLE_QUOTES);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE player_id = %d ORDER BY said_on DESC, id DESC LIMIT %d",
            $playerId,
            max(1, min(200, $limit))
        ), ARRAY_A) ?: [];
        return array_map([self::class, 'decode'], $rows);
    }

    private static function decode(array $row): array
    {
        $row['quote_text'] = html_entity_decode((string) $row['quote_text'], ENT_QUOTES, 'UTF-8');
        $row['context']    = html_entity_decode((string) $row['context'], ENT_QUOTES, 'UTF-8');
        $row['player_id']  = $row['player_id'] !== null ? (int) $row['player_id'] : null;
        $row['season_id']  = (int) $row['season_id'];
        $row['id']         = (int) $row['id'];
        $row['source_count'] = (int) $row['source_count'];
        return $row;
    }

    private static function withPlayer(array $row): array
    {
        $row['player_name'] = null;
        $row['player_slug'] = null;
        if ($row['player_id']) {
            $post = get_post($row['player_id']);
            if ($post) {
                $row['player_name'] = $post->post_title;
                $row['player_slug'] = $post->post_name;
            }
        }
        return $row;
    }
}
