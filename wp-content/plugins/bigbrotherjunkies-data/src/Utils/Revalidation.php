<?php

namespace BigBrotherJunkies\Data\Utils;

/**
 * Utility class for triggering Next.js revalidation
 */
class Revalidation
{
    /**
     * Trigger revalidation for a season
     */
    public static function revalidateSeason(string $slug): bool
    {
        return self::trigger('season', $slug);
    }

    /**
     * Trigger revalidation for a player
     */
    public static function revalidatePlayer(string $slug): bool
    {
        return self::trigger('player', $slug);
    }

    /**
     * Trigger revalidation for a post
     */
    public static function revalidatePost(string $slug): bool
    {
        return self::trigger('post', $slug);
    }

    /**
     * Trigger revalidation for the spoiler bar
     */
    public static function revalidateSpoilerBar(): bool
    {
        return self::triggerByTag('spoiler-bar');
    }

    /**
     * Trigger revalidation by tag
     */
    public static function revalidateTag(string $tag): bool
    {
        return self::triggerByTag($tag);
    }

    /**
     * Send revalidation request to Next.js
     */
    private static function trigger(string $type, string $slug): bool
    {
        $nextUrl = defined('NEXT_PUBLIC_SITE_URL') ? NEXT_PUBLIC_SITE_URL : 'https://bigbrotherjunkies.com';
        $secret = defined('REVALIDATION_SECRET') ? REVALIDATION_SECRET : get_option('bbj_revalidation_secret', '');

        if (empty($secret)) {
            error_log('BBJ Revalidation: No secret configured');
            return false;
        }

        $url = rtrim($nextUrl, '/') . '/api/revalidate';

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'secret' => $secret,
                'type' => $type,
                'slug' => $slug,
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            error_log('BBJ Revalidation Error: ' . $response->get_error_message());
            return false;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            error_log('BBJ Revalidation Error: HTTP ' . $statusCode);
            return false;
        }

        return true;
    }

    /**
     * Send revalidation request by tag
     */
    private static function triggerByTag(string $tag): bool
    {
        $nextUrl = defined('NEXT_PUBLIC_SITE_URL') ? NEXT_PUBLIC_SITE_URL : 'https://bigbrotherjunkies.com';
        $secret = defined('REVALIDATION_SECRET') ? REVALIDATION_SECRET : get_option('bbj_revalidation_secret', '');

        if (empty($secret)) {
            return false;
        }

        $url = rtrim($nextUrl, '/') . '/api/revalidate';

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'secret' => $secret,
                'tag' => $tag,
            ]),
            'timeout' => 10,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}
