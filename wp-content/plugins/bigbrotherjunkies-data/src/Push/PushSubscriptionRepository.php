<?php

namespace BigBrotherJunkies\Data\Push;

class PushSubscriptionRepository
{
    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public static function toWebPushArray(object $row): array
    {
        return [
            'endpoint'        => $row->endpoint,
            'publicKey'       => $row->p256dh,
            'authToken'       => $row->auth,
            'contentEncoding' => 'aesgcm',
        ];
    }

    public static function upsert(string $endpoint, string $p256dh, string $auth, ?int $userId, ?string $ua): int
    {
        global $wpdb;
        $table = PushSchema::table();
        $hash = self::hash($endpoint);
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE endpoint_hash = %s", $hash));
        if ($existing) {
            $wpdb->update($table, [
                'user_id' => $userId, 'p256dh' => $p256dh, 'auth' => $auth, 'ua' => $ua,
            ], ['id' => (int) $existing]);
            return (int) $existing;
        }
        $wpdb->insert($table, [
            'user_id' => $userId, 'endpoint' => $endpoint, 'endpoint_hash' => $hash,
            'p256dh' => $p256dh, 'auth' => $auth, 'ua' => $ua,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function deleteByEndpoint(string $endpoint): void
    {
        global $wpdb;
        $wpdb->delete(PushSchema::table(), ['endpoint_hash' => self::hash($endpoint)]);
    }

    public static function all(): array
    {
        global $wpdb;
        return $wpdb->get_results("SELECT endpoint, p256dh, auth FROM " . PushSchema::table());
    }

    public static function count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . PushSchema::table());
    }
}
