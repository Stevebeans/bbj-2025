<?php

namespace BigBrotherJunkies\Data\Utils;

/**
 * Maps WP content to its PUBLIC (Next.js frontend) URL.
 *
 * get_permalink() resolves against home_url() = wp.bigbrotherjunkies.com,
 * which is the headless backend, not the site users should ever land on.
 * Every user-facing link (emails, notifications) must go through here.
 */
class FrontendUrl
{
    /** Public site base, admin-configurable via the email settings page. */
    public static function base(): string
    {
        $settings = get_option('bbjd_email_settings', []);
        return rtrim($settings['frontend_url'] ?? 'https://bigbrotherjunkies.com', '/');
    }

    /**
     * Public URL for a post/CPT entry. Falls back to the frontend root path
     * for unknown types (matches the Next.js catch-all /[slug] route).
     *
     * @param \WP_Post|int $post
     */
    public static function forPost($post): string
    {
        $post = get_post($post);
        if (!$post) {
            return self::base();
        }

        $slug = $post->post_name;
        switch ($post->post_type) {
            case 'live-feed-updates':
                $path = '/live-feed-updates/' . $slug;
                break;
            case 'bigbrother-players':
                $path = '/bigbrother-players/' . $slug;
                break;
            case 'post':
            case 'bigbrother-seasons':
            default:
                $path = '/' . $slug;
                break;
        }

        return self::base() . $path;
    }
}
