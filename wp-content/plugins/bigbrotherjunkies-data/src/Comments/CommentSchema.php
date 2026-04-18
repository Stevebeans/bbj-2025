<?php

namespace BigBrotherJunkies\Data\Comments;

/**
 * Database schema definitions for Comment System tables
 */
class CommentSchema
{
    // Existing tables
    public const TABLE_VOTES = 'bbj_comment_votes';
    public const TABLE_REPORTS = 'bbj_comment_reports';
    public const TABLE_BLACKLIST = 'bbj_comment_blacklist';

    // New tables for Comment System v2
    public const TABLE_MEDIA = 'bbj_comment_media';
    public const TABLE_REACTIONS = 'bbj_comment_reactions';
    public const TABLE_SESSIONS = 'bbj_user_sessions';
    public const TABLE_NOTIFICATIONS = 'bbj_notifications';
    public const TABLE_USER_STATS = 'bbj_user_stats';
    public const TABLE_FOLLOWS = 'bbj_user_follows';
    public const TABLE_PINNED = 'bbj_pinned_comments';
    public const TABLE_AVATARS = 'bbj_user_avatars';
    public const TABLE_ACTIVE_USERS = 'bbj_active_users';
    public const TABLE_POST_SUBSCRIPTIONS = 'bbj_post_subscriptions';
    public const TABLE_ANNOUNCEMENTS = 'bbj_announcements';
    public const TABLE_ANNOUNCEMENT_READS = 'bbj_announcement_reads';

    /**
     * Get full table name with prefix
     */
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /**
     * Get the votes table schema
     * Stores upvotes/downvotes on comments
     */
    public static function getVotesTableSchema(): string
    {
        $table = self::table(self::TABLE_VOTES);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            comment_id BIGINT(20) UNSIGNED NOT NULL,
            vote_type TINYINT(1) NOT NULL,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_comment (user_id, comment_id),
            KEY idx_comment (comment_id),
            KEY idx_post (post_id),
            KEY idx_user (user_id),
            KEY idx_vote_type (vote_type)
        ) {$charset};";
    }

    /**
     * Get the reports table schema
     * Stores user reports on comments
     */
    public static function getReportsTableSchema(): string
    {
        $table = self::table(self::TABLE_REPORTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT(20) UNSIGNED NOT NULL,
            reporter_id BIGINT(20) UNSIGNED NOT NULL,
            reason VARCHAR(50) NOT NULL,
            details TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            action_taken VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_comment (comment_id),
            KEY idx_reporter (reporter_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) {$charset};";
    }

    /**
     * Get the blacklist table schema
     * Stores blacklisted users and IPs
     */
    public static function getBlacklistTableSchema(): string
    {
        $table = self::table(self::TABLE_BLACKLIST);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            reason TEXT,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_ip (ip_address),
            KEY idx_active (is_active),
            KEY idx_expires (expires_at)
        ) {$charset};";
    }

    /**
     * Get the media table schema
     * Stores image/GIF attachments on comments (1 per comment max)
     */
    public static function getMediaTableSchema(): string
    {
        $table = self::table(self::TABLE_MEDIA);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_url VARCHAR(500) NOT NULL,
            file_size INT(11) UNSIGNED NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            width INT(11) UNSIGNED DEFAULT NULL,
            height INT(11) UNSIGNED DEFAULT NULL,
            giphy_id VARCHAR(100) DEFAULT NULL,
            storage_type VARCHAR(20) NOT NULL DEFAULT 'local',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY comment_media (comment_id),
            KEY idx_user (user_id),
            KEY idx_type (media_type),
            KEY idx_created (created_at)
        ) {$charset};";
    }

    /**
     * Get the reactions table schema
     * Stores emoji reactions on comments (like, love, haha, wow, sad, angry)
     */
    public static function getReactionsTableSchema(): string
    {
        $table = self::table(self::TABLE_REACTIONS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            reaction_type VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_comment_reaction (user_id, comment_id),
            KEY idx_comment (comment_id),
            KEY idx_user (user_id),
            KEY idx_type (reaction_type)
        ) {$charset};";
    }

    /**
     * Get the sessions table schema
     * Tracks user online status (session-based)
     */
    public static function getSessionsTableSchema(): string
    {
        $table = self::table(self::TABLE_SESSIONS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY (user_id),
            KEY idx_activity (last_activity)
        ) {$charset};";
    }

    /**
     * Get the notifications table schema
     * Unified notification system for replies, mentions, reactions
     */
    public static function getNotificationsTableSchema(): string
    {
        $table = self::table(self::TABLE_NOTIFICATIONS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            actor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            comment_id BIGINT(20) UNSIGNED DEFAULT NULL,
            post_id BIGINT(20) UNSIGNED DEFAULT NULL,
            data TEXT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            is_emailed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_read (user_id, is_read),
            KEY idx_type (type),
            KEY idx_created (created_at),
            KEY idx_comment (comment_id),
            KEY idx_post (post_id)
        ) {$charset};";
    }

    /**
     * Get the user stats table schema
     * Stores gamification data: streaks, first commenter counts
     */
    public static function getUserStatsTableSchema(): string
    {
        $table = self::table(self::TABLE_USER_STATS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            comment_streak_current INT(11) NOT NULL DEFAULT 0,
            comment_streak_longest INT(11) NOT NULL DEFAULT 0,
            login_streak_current INT(11) NOT NULL DEFAULT 0,
            login_streak_longest INT(11) NOT NULL DEFAULT 0,
            last_comment_date DATE DEFAULT NULL,
            last_login_date DATE DEFAULT NULL,
            first_commenter_count INT(11) NOT NULL DEFAULT 0,
            total_reactions_received INT(11) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_comment_streak (comment_streak_current),
            KEY idx_login_streak (login_streak_current),
            KEY idx_first_commenter (first_commenter_count)
        ) {$charset};";
    }

    /**
     * Get the follows table schema
     * Stores user following relationships
     */
    public static function getFollowsTableSchema(): string
    {
        $table = self::table(self::TABLE_FOLLOWS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            follower_id BIGINT(20) UNSIGNED NOT NULL,
            following_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY follower_following (follower_id, following_id),
            KEY idx_follower (follower_id),
            KEY idx_following (following_id)
        ) {$charset};";
    }

    /**
     * Get the pinned comments table schema
     * Stores pinned comments per post
     */
    public static function getPinnedTableSchema(): string
    {
        $table = self::table(self::TABLE_PINNED);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT(20) UNSIGNED NOT NULL,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            pinned_by BIGINT(20) UNSIGNED NOT NULL,
            pinned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY comment_pin (comment_id),
            KEY idx_post (post_id)
        ) {$charset};";
    }

    /**
     * Get the user avatars table schema
     * Stores user avatars with consistent sizing and WebP format
     */
    public static function getAvatarsTableSchema(): string
    {
        $table = self::table(self::TABLE_AVATARS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_url VARCHAR(500) NOT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            file_size INT(11) UNSIGNED NOT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) {$charset};";
    }

    /**
     * Get the active users table schema
     * Fast lookup table for @mention autocomplete
     * Contains only users who have commented (not spam)
     */
    public static function getActiveUsersTableSchema(): string
    {
        $table = self::table(self::TABLE_ACTIVE_USERS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            display_name VARCHAR(250) NOT NULL,
            user_login VARCHAR(60) NOT NULL,
            comment_count INT(11) UNSIGNED NOT NULL DEFAULT 0,
            last_active DATETIME DEFAULT NULL,
            PRIMARY KEY (user_id),
            KEY idx_display_name (display_name(50)),
            KEY idx_user_login (user_login),
            KEY idx_comment_count (comment_count)
        ) {$charset};";
    }

    /**
     * Get the post subscriptions table schema
     * Stores user subscriptions to posts for thread notifications
     */
    public static function getPostSubscriptionsTableSchema(): string
    {
        $table = self::table(self::TABLE_POST_SUBSCRIPTIONS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_subscription (user_id, post_id),
            KEY idx_post (post_id),
            KEY idx_user (user_id)
        ) {$charset};";
    }

    /**
     * Get the announcements table schema
     */
    public static function getAnnouncementsTableSchema(): string
    {
        $table = self::table(self::TABLE_ANNOUNCEMENTS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message TEXT NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_created (created_at)
        ) {$charset};";
    }

    /**
     * Get the announcement reads table schema
     */
    public static function getAnnouncementReadsTableSchema(): string
    {
        $table = self::table(self::TABLE_ANNOUNCEMENT_READS);
        $charset = self::getCharset();

        return "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            announcement_id BIGINT(20) UNSIGNED NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, announcement_id),
            KEY idx_announcement (announcement_id)
        ) {$charset};";
    }

    /**
     * Get all table schemas
     */
    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_VOTES => self::getVotesTableSchema(),
            self::TABLE_REPORTS => self::getReportsTableSchema(),
            self::TABLE_BLACKLIST => self::getBlacklistTableSchema(),
            self::TABLE_MEDIA => self::getMediaTableSchema(),
            self::TABLE_REACTIONS => self::getReactionsTableSchema(),
            self::TABLE_SESSIONS => self::getSessionsTableSchema(),
            self::TABLE_NOTIFICATIONS => self::getNotificationsTableSchema(),
            self::TABLE_USER_STATS => self::getUserStatsTableSchema(),
            self::TABLE_FOLLOWS => self::getFollowsTableSchema(),
            self::TABLE_PINNED => self::getPinnedTableSchema(),
            self::TABLE_AVATARS => self::getAvatarsTableSchema(),
            self::TABLE_ACTIVE_USERS => self::getActiveUsersTableSchema(),
            self::TABLE_POST_SUBSCRIPTIONS => self::getPostSubscriptionsTableSchema(),
            self::TABLE_ANNOUNCEMENTS => self::getAnnouncementsTableSchema(),
            self::TABLE_ANNOUNCEMENT_READS => self::getAnnouncementReadsTableSchema(),
        ];
    }

    /**
     * Get only the new v2 table schemas (for migration)
     */
    public static function getV2Schemas(): array
    {
        return [
            self::TABLE_MEDIA => self::getMediaTableSchema(),
            self::TABLE_REACTIONS => self::getReactionsTableSchema(),
            self::TABLE_SESSIONS => self::getSessionsTableSchema(),
            self::TABLE_NOTIFICATIONS => self::getNotificationsTableSchema(),
            self::TABLE_USER_STATS => self::getUserStatsTableSchema(),
            self::TABLE_FOLLOWS => self::getFollowsTableSchema(),
            self::TABLE_PINNED => self::getPinnedTableSchema(),
            self::TABLE_AVATARS => self::getAvatarsTableSchema(),
            self::TABLE_ACTIVE_USERS => self::getActiveUsersTableSchema(),
        ];
    }

    /**
     * Get charset collate string
     */
    private static function getCharset(): string
    {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
}
