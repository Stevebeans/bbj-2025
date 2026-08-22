<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Standard footer appended to Facebook page posts at PUBLISH time only, so
 * queue titles/bodies stay clean for the fb_post_id -> title A/B join and
 * the In-Queue review UI. Plain-text site mention on purpose: an actual URL
 * would get the post's reach suppressed by Facebook.
 */
class PostFooter
{
    public const DEFAULT_FOOTER = 'Note: these posts can run behind. For up-to-the-minute updates, hit the Big Brother Junkies website.';

    /** Option override lives in bbjd_content_engine_settings['fb_post_footer']; a saved '' disables the footer. */
    public static function apply(string $body): string
    {
        $settings = get_option('bbjd_content_engine_settings', []);
        $footer = array_key_exists('fb_post_footer', $settings)
            ? (string) $settings['fb_post_footer']
            : self::DEFAULT_FOOTER;
        return self::applyTo($body, $footer);
    }

    /** Pure part (unit-tested). Skips blank bodies and never double-appends. */
    public static function applyTo(string $body, string $footer): string
    {
        $footer = trim($footer);
        if ($footer === '' || trim($body) === '') {
            return $body;
        }
        if (strpos($body, $footer) !== false) {
            return $body;
        }
        return rtrim($body) . "\n\n" . $footer;
    }
}
