<?php

namespace BigBrotherJunkies\Data\Api;

use WP_REST_Request;
use WP_REST_Response;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Utils\Revalidation;

/**
 * Ad Settings API Routes
 *
 * Provides endpoints for Freestar ad configuration:
 * - GET  /bbjd/v1/ad-settings  — Public read (frontend needs settings)
 * - POST /bbjd/v1/ad-settings  — Admin write (requires ad_management permission)
 */
class AdSettingsRoutes
{
    private const OPTION_KEY = 'bbjd_freestar_settings';

    private const DEFAULT_PARAMOUNT_URL = 'https://paramountplus.qflm.net/c/161260/3116112/3065';

    private const DEFAULTS = [
        'ads_enabled'          => true,
        'disabled_placements'  => [],
        'house_ad'             => 'premium-cta',
        'supporter_roles'      => ['administrator', 'editor', 'supporter', 'lifetime', 'full_bean'],
        'pwa_suppressed'       => ['bigbrotherjunkies_sticky_footer', 'bigbrotherjunkies_google_interstitial'],
        'incontent_interval'   => 5,
        // Live feeds: manual "in-season" toggle + configurable Paramount+ link.
        'feeds_live'           => true,
        'feeds_paramount_url'  => self::DEFAULT_PARAMOUNT_URL,
        // Site-wide countdown banner above the header (single event).
        'countdown_enabled'    => false,
        'countdown_label'      => '',
        'countdown_target'     => '', // ISO-8601 UTC instant, e.g. 2026-07-09T00:00:00.000Z
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('bbjd/v1', '/ad-settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => function () {
                    return PermissionChecker::userCan('ad_management');
                },
            ],
        ]);
    }

    public function getSettings(): WP_REST_Response
    {
        $settings = get_option(self::OPTION_KEY, self::DEFAULTS);
        $settings = wp_parse_args($settings, self::DEFAULTS);

        return new WP_REST_Response($settings, 200);
    }

    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        $current = wp_parse_args(get_option(self::OPTION_KEY, self::DEFAULTS), self::DEFAULTS);
        $body = $request->get_json_params();

        $updated = [
            'ads_enabled'         => isset($body['ads_enabled']) ? (bool) $body['ads_enabled'] : $current['ads_enabled'],
            'disabled_placements' => isset($body['disabled_placements']) ? array_map('sanitize_text_field', (array) $body['disabled_placements']) : $current['disabled_placements'],
            'house_ad'            => isset($body['house_ad']) ? sanitize_text_field($body['house_ad']) : $current['house_ad'],
            'supporter_roles'     => isset($body['supporter_roles']) ? array_map('sanitize_text_field', (array) $body['supporter_roles']) : $current['supporter_roles'],
            'pwa_suppressed'      => isset($body['pwa_suppressed']) ? array_map('sanitize_text_field', (array) $body['pwa_suppressed']) : $current['pwa_suppressed'],
            'incontent_interval'  => isset($body['incontent_interval']) ? max(2, min(10, absint($body['incontent_interval']))) : $current['incontent_interval'],
            'feeds_live'          => isset($body['feeds_live']) ? (bool) $body['feeds_live'] : $current['feeds_live'],
            'feeds_paramount_url' => isset($body['feeds_paramount_url']) ? esc_url_raw((string) $body['feeds_paramount_url']) : $current['feeds_paramount_url'],
            'countdown_enabled'   => isset($body['countdown_enabled']) ? (bool) $body['countdown_enabled'] : $current['countdown_enabled'],
            'countdown_label'     => isset($body['countdown_label']) ? sanitize_text_field((string) $body['countdown_label']) : $current['countdown_label'],
            'countdown_target'    => isset($body['countdown_target']) ? sanitize_text_field((string) $body['countdown_target']) : $current['countdown_target'],
        ];

        update_option(self::OPTION_KEY, $updated);

        // Purge the cached ad-settings tag so the header (feeds toggle / link) updates.
        Revalidation::revalidateTag('ad-settings');

        return new WP_REST_Response($updated, 200);
    }
}
