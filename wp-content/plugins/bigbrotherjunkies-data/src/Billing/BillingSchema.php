<?php

namespace BigBrotherJunkies\Data\Billing;

/**
 * Database schema definitions for Billing System tables
 */
class BillingSchema
{
    public const TABLE_SUBSCRIPTIONS = 'bbj_subscriptions';

    /**
     * Get full table name with prefix
     */
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /**
     * Get the subscriptions table schema
     * Stores user subscriptions for Stripe and PayPal
     */
    public static function getSubscriptionsTableSchema(): string
    {
        $table = self::table(self::TABLE_SUBSCRIPTIONS);
        $charset = self::getCharset();

        // NOTE: no inline `-- comments` here — dbDelta cannot parse SQL comments
        // and emits a malformed `ADD `` (``)` ALTER for each one (log spam every
        // migration). Keep this CREATE TABLE comment-free.
        return "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            stripe_customer_id VARCHAR(255) DEFAULT NULL,
            stripe_subscription_id VARCHAR(255) DEFAULT NULL,
            paypal_subscription_id VARCHAR(255) DEFAULT NULL,
            paypal_payer_id VARCHAR(255) DEFAULT NULL,
            plan_type ENUM('monthly', 'annual', 'lifetime') NOT NULL,
            tier ENUM('supporter', 'full_bean') NOT NULL DEFAULT 'supporter',
            processor ENUM('stripe', 'paypal') NOT NULL,
            status ENUM('active', 'canceled', 'past_due', 'expired', 'lifetime') NOT NULL,
            amount_cents INT(11) UNSIGNED DEFAULT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            current_period_start DATETIME DEFAULT NULL,
            current_period_end DATETIME DEFAULT NULL,
            canceled_at DATETIME DEFAULT NULL,
            cancel_at_period_end TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_stripe_customer (stripe_customer_id),
            KEY idx_stripe_sub (stripe_subscription_id),
            KEY idx_paypal_sub (paypal_subscription_id),
            KEY idx_status (status),
            KEY idx_tier (tier)
        ) {$charset};";
    }

    /**
     * Get all table schemas
     */
    public static function getAllSchemas(): array
    {
        return [
            self::TABLE_SUBSCRIPTIONS => self::getSubscriptionsTableSchema(),
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
