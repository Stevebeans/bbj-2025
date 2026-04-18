<?php

namespace BigBrotherJunkies\Data\Comments;

/**
 * Handles database migrations for Comment System
 */
class CommentMigrator
{
    public const DB_VERSION_OPTION = 'bbj_comment_db_version';
    public const CURRENT_VERSION = '2.0.0'; // v2: Added media, reactions, sessions, notifications, stats, follows, pinned
    public const MIGRATION_STATUS_OPTION = 'bbj_comment_vote_migration_status';

    /**
     * Run migrations if needed
     */
    public static function migrate(): array
    {
        $results = [];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (CommentSchema::getAllSchemas() as $tableName => $schema) {
            dbDelta($schema);
            $results[$tableName] = self::tableExists($tableName);
        }

        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);

        return $results;
    }

    /**
     * Check if a table exists
     */
    public static function tableExists(string $tableName): bool
    {
        global $wpdb;
        $fullTableName = CommentSchema::table($tableName);
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $fullTableName)
        );
        return $result === $fullTableName;
    }

    /**
     * Get table row count
     */
    public static function getTableRowCount(string $tableName): int
    {
        global $wpdb;
        $fullTableName = CommentSchema::table($tableName);

        if (!self::tableExists($tableName)) {
            return -1;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$fullTableName}");
    }

    /**
     * Get status of all comment tables
     */
    public static function getTablesStatus(): array
    {
        $tables = [
            // v1 tables
            CommentSchema::TABLE_VOTES,
            CommentSchema::TABLE_REPORTS,
            CommentSchema::TABLE_BLACKLIST,
            // v2 tables
            CommentSchema::TABLE_MEDIA,
            CommentSchema::TABLE_REACTIONS,
            CommentSchema::TABLE_SESSIONS,
            CommentSchema::TABLE_NOTIFICATIONS,
            CommentSchema::TABLE_USER_STATS,
            CommentSchema::TABLE_FOLLOWS,
            CommentSchema::TABLE_PINNED,
            CommentSchema::TABLE_AVATARS,
            CommentSchema::TABLE_ACTIVE_USERS,
            CommentSchema::TABLE_POST_SUBSCRIPTIONS,
            // Announcements
            CommentSchema::TABLE_ANNOUNCEMENTS,
            CommentSchema::TABLE_ANNOUNCEMENT_READS,
        ];

        $status = [];
        foreach ($tables as $table) {
            $status[$table] = [
                'exists' => self::tableExists($table),
                'rows' => self::getTableRowCount($table),
                'full_name' => CommentSchema::table($table),
            ];
        }

        return $status;
    }

    /**
     * Check if migration is needed
     */
    public static function needsMigration(): bool
    {
        $currentVersion = get_option(self::DB_VERSION_OPTION, '0.0.0');
        return version_compare($currentVersion, self::CURRENT_VERSION, '<');
    }

    /**
     * Get current database version
     */
    public static function getCurrentVersion(): string
    {
        return get_option(self::DB_VERSION_OPTION, 'Not installed');
    }

    /**
     * Get WPDiscuz vote migration stats (preview before migration)
     */
    public static function getWpDiscuzVoteStats(): array
    {
        global $wpdb;

        $wpdiscuzTable = $wpdb->prefix . 'wc_users_voted';

        // Check if WPDiscuz table exists
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $wpdiscuzTable)
        ) === $wpdiscuzTable;

        if (!$tableExists) {
            return [
                'exists' => false,
                'message' => 'WPDiscuz votes table not found',
            ];
        }

        // Get stats for logged-in users only (is_guest = 0)
        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_votes,
                SUM(CASE WHEN vote_type = 1 THEN 1 ELSE 0 END) as upvotes,
                SUM(CASE WHEN vote_type = -1 THEN 1 ELSE 0 END) as downvotes,
                COUNT(DISTINCT user_id) as unique_voters,
                COUNT(DISTINCT comment_id) as unique_comments
            FROM {$wpdiscuzTable}
            WHERE is_guest = 0
        ", ARRAY_A);

        return [
            'exists' => true,
            'total_votes' => (int) $stats['total_votes'],
            'upvotes' => (int) $stats['upvotes'],
            'downvotes' => (int) $stats['downvotes'],
            'unique_voters' => (int) $stats['unique_voters'],
            'unique_comments' => (int) $stats['unique_comments'],
        ];
    }

    /**
     * Migrate votes from WPDiscuz to new table
     * Only migrates logged-in user votes (is_guest = 0)
     *
     * @param int $batchSize Number of rows to process per batch
     * @return array Migration results
     */
    public static function migrateWpDiscuzVotes(int $batchSize = 5000): array
    {
        global $wpdb;

        $sourceTable = $wpdb->prefix . 'wc_users_voted';
        $targetTable = CommentSchema::table(CommentSchema::TABLE_VOTES);

        // Get migration status
        $status = get_option(self::MIGRATION_STATUS_OPTION, [
            'started' => false,
            'completed' => false,
            'last_id' => 0,
            'migrated_count' => 0,
            'error_count' => 0,
            'errors' => [],
        ]);

        // Check if source table exists
        $sourceExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $sourceTable)
        ) === $sourceTable;

        if (!$sourceExists) {
            return [
                'success' => false,
                'message' => 'WPDiscuz votes table not found',
            ];
        }

        // Check if target table exists
        if (!self::tableExists(CommentSchema::TABLE_VOTES)) {
            return [
                'success' => false,
                'message' => 'Target votes table not found. Run migration first.',
            ];
        }

        // Mark as started
        $status['started'] = true;
        $status['start_time'] = current_time('mysql');

        // Get total count for progress tracking
        $totalToMigrate = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$sourceTable}
            WHERE is_guest = 0 AND id > {$status['last_id']}
        ");

        // Get batch of votes to migrate
        $votes = $wpdb->get_results($wpdb->prepare("
            SELECT id, user_id, comment_id, vote_type, post_id, date
            FROM {$sourceTable}
            WHERE is_guest = 0 AND id > %d
            ORDER BY id ASC
            LIMIT %d
        ", $status['last_id'], $batchSize), ARRAY_A);

        if (empty($votes)) {
            $status['completed'] = true;
            $status['end_time'] = current_time('mysql');
            update_option(self::MIGRATION_STATUS_OPTION, $status);

            return [
                'success' => true,
                'completed' => true,
                'message' => 'Migration completed',
                'total_migrated' => $status['migrated_count'],
                'errors' => $status['error_count'],
            ];
        }

        $migratedThisBatch = 0;
        $errorsThisBatch = 0;

        foreach ($votes as $vote) {
            // Convert Unix timestamp to MySQL datetime
            $createdAt = date('Y-m-d H:i:s', (int) $vote['date']);

            // Insert into new table (ignore duplicates)
            $result = $wpdb->query($wpdb->prepare("
                INSERT IGNORE INTO {$targetTable}
                (user_id, comment_id, vote_type, post_id, created_at)
                VALUES (%d, %d, %d, %d, %s)
            ",
                (int) $vote['user_id'],
                (int) $vote['comment_id'],
                (int) $vote['vote_type'],
                (int) $vote['post_id'],
                $createdAt
            ));

            if ($result !== false) {
                $migratedThisBatch++;
            } else {
                $errorsThisBatch++;
                if (count($status['errors']) < 100) {
                    $status['errors'][] = [
                        'vote_id' => $vote['id'],
                        'error' => $wpdb->last_error,
                    ];
                }
            }

            $status['last_id'] = (int) $vote['id'];
        }

        $status['migrated_count'] += $migratedThisBatch;
        $status['error_count'] += $errorsThisBatch;

        update_option(self::MIGRATION_STATUS_OPTION, $status);

        return [
            'success' => true,
            'completed' => false,
            'message' => "Migrated {$migratedThisBatch} votes this batch",
            'progress' => [
                'migrated_this_batch' => $migratedThisBatch,
                'total_migrated' => $status['migrated_count'],
                'remaining' => $totalToMigrate - $migratedThisBatch,
                'errors_this_batch' => $errorsThisBatch,
                'total_errors' => $status['error_count'],
            ],
        ];
    }

    /**
     * Reset migration status (to re-run migration)
     */
    public static function resetMigrationStatus(): void
    {
        delete_option(self::MIGRATION_STATUS_OPTION);
    }

    /**
     * Get current migration status
     */
    public static function getMigrationStatus(): array
    {
        return get_option(self::MIGRATION_STATUS_OPTION, [
            'started' => false,
            'completed' => false,
            'last_id' => 0,
            'migrated_count' => 0,
            'error_count' => 0,
        ]);
    }

    /**
     * Drop all comment tables (for dev/testing)
     */
    public static function dropAllTables(): array
    {
        global $wpdb;

        $results = [];
        $tables = [
            // v1 tables
            CommentSchema::TABLE_VOTES,
            CommentSchema::TABLE_REPORTS,
            CommentSchema::TABLE_BLACKLIST,
            // v2 tables
            CommentSchema::TABLE_MEDIA,
            CommentSchema::TABLE_REACTIONS,
            CommentSchema::TABLE_SESSIONS,
            CommentSchema::TABLE_NOTIFICATIONS,
            CommentSchema::TABLE_USER_STATS,
            CommentSchema::TABLE_FOLLOWS,
            CommentSchema::TABLE_PINNED,
            CommentSchema::TABLE_AVATARS,
            CommentSchema::TABLE_ACTIVE_USERS,
            CommentSchema::TABLE_POST_SUBSCRIPTIONS,
        ];

        foreach ($tables as $table) {
            $fullTableName = CommentSchema::table($table);
            $wpdb->query("DROP TABLE IF EXISTS {$fullTableName}");
            $results[$table] = !self::tableExists($table);
        }

        delete_option(self::DB_VERSION_OPTION);
        delete_option(self::MIGRATION_STATUS_OPTION);

        return $results;
    }

    /**
     * Run only v2 migrations (for upgrading from v1)
     */
    public static function migrateV2(): array
    {
        $results = [];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (CommentSchema::getV2Schemas() as $tableName => $schema) {
            dbDelta($schema);
            $results[$tableName] = self::tableExists($tableName);
        }

        update_option(self::DB_VERSION_OPTION, self::CURRENT_VERSION);

        return $results;
    }
}
