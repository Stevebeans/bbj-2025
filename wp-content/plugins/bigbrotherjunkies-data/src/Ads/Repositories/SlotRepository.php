<?php

namespace BigBrotherJunkies\Data\Ads\Repositories;

use BigBrotherJunkies\Data\Ads\Models\Slot;
use BigBrotherJunkies\Data\Database\Schema;

/**
 * Repository for Slot database operations
 */
class SlotRepository
{
    private const CACHE_GROUP = 'bbjd_slots';
    private const CACHE_TTL = 300;

    /**
     * Find slot by ID
     */
    public function find(int $id): ?Slot
    {
        $cacheKey = "slot:{$id}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        $slot = Slot::fromRow($row);
        wp_cache_set($cacheKey, $slot, self::CACHE_GROUP, self::CACHE_TTL);

        return $slot;
    }

    /**
     * Find slot by slug
     */
    public function findBySlug(string $slug): ?Slot
    {
        $cacheKey = "slot_slug:{$slug}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug)
        );

        if (!$row) {
            return null;
        }

        $slot = Slot::fromRow($row);
        wp_cache_set($cacheKey, $slot, self::CACHE_GROUP, self::CACHE_TTL);

        return $slot;
    }

    /**
     * Get all slots
     */
    public function findAll(array $args = []): array
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $defaults = [
            'status' => null,
            'type' => null,
            'orderby' => 'name',
            'order' => 'ASC',
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

        $sql = "SELECT * FROM {$table} {$whereClause} {$orderClause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $rows = $wpdb->get_results($sql);

        return array_map([Slot::class, 'fromRow'], $rows);
    }

    /**
     * Get all active slots
     */
    public function findActive(): array
    {
        return $this->findAll(['status' => 'active']);
    }

    /**
     * Get all auto-insertion slots
     */
    public function findAutoInsertSlots(): array
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'active'
               AND type IN ('auto_content', 'auto_comments', 'auto_feed')
             ORDER BY name ASC"
        );

        return array_map([Slot::class, 'fromRow'], $rows);
    }

    /**
     * Create a new slot
     */
    public function create(Slot $slot): int
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $wpdb->insert($table, $slot->toArray());
        $slot->id = $wpdb->insert_id;

        $this->bustCache($slot);

        return $slot->id;
    }

    /**
     * Update an existing slot
     */
    public function update(Slot $slot): bool
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $result = $wpdb->update(
            $table,
            $slot->toArray(),
            ['id' => $slot->id]
        );

        $this->bustCache($slot);

        return $result !== false;
    }

    /**
     * Delete a slot
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);
        $assignmentsTable = Schema::table(Schema::TABLE_ASSIGNMENTS);

        // Delete assignments first
        $wpdb->delete($assignmentsTable, ['slot_id' => $id]);

        // Delete the slot
        $result = $wpdb->delete($table, ['id' => $id]);

        $this->bustCacheById($id);

        return $result !== false;
    }

    /**
     * Count all slots
     */
    public function count(array $args = []): int
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_SLOTS);

        $where = [];
        $values = [];

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM {$table} {$whereClause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get count of ads assigned to a slot
     */
    public function getAdCount(int $slotId): int
    {
        global $wpdb;
        $assignmentsTable = Schema::table(Schema::TABLE_ASSIGNMENTS);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$assignmentsTable} WHERE slot_id = %d",
                $slotId
            )
        );
    }

    /**
     * Bust cache for a slot
     */
    private function bustCache(Slot $slot): void
    {
        wp_cache_delete("slot:{$slot->id}", self::CACHE_GROUP);
        wp_cache_delete("slot_slug:{$slot->slug}", self::CACHE_GROUP);
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Bust cache by ID
     */
    private function bustCacheById(int $id): void
    {
        wp_cache_delete("slot:{$id}", self::CACHE_GROUP);
        wp_cache_flush_group(self::CACHE_GROUP);
    }
}
