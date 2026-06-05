<?php

namespace BigBrotherJunkies\Data\Push;

use Minishlink\WebPush\VAPID;

/**
 * VAPID key access + generation. Keys persist in the bbjd_api_settings option.
 */
class VapidKeys
{
    public const OPTION = 'bbjd_api_settings';

    private static function settings(): array
    {
        return (array) get_option(self::OPTION, []);
    }

    public static function publicKey(): string  { return (string) (self::settings()['vapid_public_key'] ?? ''); }
    public static function privateKey(): string { return (string) (self::settings()['vapid_private_key'] ?? ''); }

    public static function subject(): string
    {
        $s = self::settings()['vapid_subject'] ?? '';
        return $s !== '' ? (string) $s : 'mailto:admin@bigbrotherjunkies.com';
    }

    public static function isConfigured(): bool
    {
        return self::publicKey() !== '' && self::privateKey() !== '';
    }

    public static function ensureGenerated(): array
    {
        if (self::isConfigured()) {
            return ['public' => self::publicKey(), 'private' => self::privateKey(), 'generated' => false];
        }
        $keys = VAPID::createVapidKeys();
        $s = self::settings();
        $s['vapid_public_key'] = $keys['publicKey'];
        $s['vapid_private_key'] = $keys['privateKey'];
        update_option(self::OPTION, $s);
        return ['public' => $keys['publicKey'], 'private' => $keys['privateKey'], 'generated' => true];
    }

    public static function authArray(): array
    {
        return ['VAPID' => [
            'subject'    => self::subject(),
            'publicKey'  => self::publicKey(),
            'privateKey' => self::privateKey(),
        ]];
    }
}
