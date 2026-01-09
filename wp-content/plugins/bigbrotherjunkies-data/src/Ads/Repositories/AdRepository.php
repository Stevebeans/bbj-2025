<?php

namespace BigBrotherJunkies\Data\Ads\Repositories;

use BigBrotherJunkies\Data\Ads\Models\Ad;
use BigBrotherJunkies\Data\Database\Schema;

/**
 * Repository for Ad database operations
 */
class AdRepository
{
    private const CACHE_GROUP = 'bbjd_ads';
    private const CACHE_TTL = 300;

    /**
     * Find ad by ID
     */
    public function find(int $id): ?Ad
    {
        $cacheKey = "ad:{$id}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        $ad = Ad::fromRow($row);
        wp_cache_set($cacheKey, $ad, self::CACHE_GROUP, self::CACHE_TTL);

        return $ad;
    }

    /**
     * Find ad by slug
     */
    public function findBySlug(string $slug): ?Ad
    {
        $cacheKey = "ad_slug:{$slug}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug)
        );

        if (!$row) {
            return null;
        }

        $ad = Ad::fromRow($row);
        wp_cache_set($cacheKey, $ad, self::CACHE_GROUP, self::CACHE_TTL);

        return $ad;
    }

    /**
     * Get all ads
     */
    public function findAll(array $args = []): array
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);

        $defaults = [
            'status' => null,
            'type' => null,
            'orderby' => 'priority',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $values = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['type']) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $orderClause = sprintf('ORDER BY %s %s', esc_sql($args['orderby']), esc_sql($args['order']));
        $limitClause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $sql = "SELECT * FROM {$table} {$whereClause} {$orderClause} {$limitClause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $rows = $wpdb->get_results($sql);

        return array_map([Ad::class, 'fromRow'], $rows);
    }

    /**
     * Get active ads for a slot
     */
    public function findBySlot(int $slotId): array
    {
        $cacheKey = "ads_slot:{$slotId}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $adsTable = Schema::table(Schema::TABLE_ADS);
        $assignmentsTable = Schema::table(Schema::TABLE_ASSIGNMENTS);

        $now = current_time('mysql');

        $sql = $wpdb->prepare(
            "SELECT a.*, sa.weight
             FROM {$adsTable} a
             INNER JOIN {$assignmentsTable} sa ON a.id = sa.ad_id
             WHERE sa.slot_id = %d
               AND a.status = 'active'
               AND (sa.start_date IS NULL OR sa.start_date <= %s)
               AND (sa.end_date IS NULL OR sa.end_date >= %s)
             ORDER BY sa.weight DESC, a.priority DESC",
            $slotId,
            $now,
            $now
        );

        $rows = $wpdb->get_results($sql);
        $ads = array_map([Ad::class, 'fromRow'], $rows);

        wp_cache_set($cacheKey, $ads, self::CACHE_GROUP, self::CACHE_TTL);

        return $ads;
    }

    /**
     * Create a new ad
     */
    public function create(Ad $ad): int
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);

        $wpdb->insert($table, $ad->toArray());
        $ad->id = $wpdb->insert_id;

        $this->bustCache($ad);

        return $ad->id;
    }

    /**
     * Update an existing ad
     */
    public function update(Ad $ad): bool
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);

        $result = $wpdb->update(
            $table,
            $ad->toArray(),
            ['id' => $ad->id]
        );

        $this->bustCache($ad);

        return $result !== false;
    }

    /**
     * Delete an ad
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);
        $assignmentsTable = Schema::table(Schema::TABLE_ASSIGNMENTS);

        // Delete assignments first
        $wpdb->delete($assignmentsTable, ['ad_id' => $id]);

        // Delete the ad
        $result = $wpdb->delete($table, ['id' => $id]);

        $this->bustCacheById($id);

        return $result !== false;
    }

    /**
     * Count all ads
     */
    public function count(array $args = []): int
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ADS);

        $where = [];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM {$table} {$whereClause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Bust cache for an ad
     */
    private function bustCache(Ad $ad): void
    {
        wp_cache_delete("ad:{$ad->id}", self::CACHE_GROUP);
        wp_cache_delete("ad_slug:{$ad->slug}", self::CACHE_GROUP);
        // Bust slot caches too since ad assignments may have changed
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Bust cache by ID
     */
    private function bustCacheById(int $id): void
    {
        wp_cache_delete("ad:{$id}", self::CACHE_GROUP);
        wp_cache_flush_group(self::CACHE_GROUP);
    }
}
