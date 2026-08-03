<?php
namespace BigBrotherJunkies\Data\BrandSafety;

class Log
{
    private const DB_VERSION = '1';
    private const VERSION_OPTION = 'bbjd_brand_safety_db_version';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'bbj_brand_safety_log';
    }

    public static function ensureTable(): void
    {
        if (get_option(self::VERSION_OPTION) === self::DB_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(20) NOT NULL,
            object_id BIGINT(20) UNSIGNED NOT NULL,
            term VARCHAR(100) NOT NULL,
            action VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_object (object_type, object_id),
            KEY idx_created (created_at)
        ) {$charset};");
        update_option(self::VERSION_OPTION, self::DB_VERSION);
    }

    public static function record(string $objectType, int $objectId, array $hits): void
    {
        if (!$hits) {
            return;
        }
        self::ensureTable();
        global $wpdb;
        foreach ($hits as $hit) {
            $wpdb->insert(self::table(), [
                'object_type' => $objectType,
                'object_id' => $objectId,
                'term' => $hit['term'],
                'action' => $hit['action'],
            ], ['%s', '%d', '%s', '%s']);
        }
    }

    public static function recent(int $page = 1, int $perPage = 50): array
    {
        self::ensureTable();
        global $wpdb;
        $table = self::table();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
            $perPage,
            $offset
        ), ARRAY_A);
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return ['rows' => $rows, 'total' => $total];
    }
}
