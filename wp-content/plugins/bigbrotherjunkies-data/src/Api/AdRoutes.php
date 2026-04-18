<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Ads\AdManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API routes for ads
 *
 * Used by Next.js frontend to fetch ad content for slots
 */
class AdRoutes
{
    /**
     * API namespace
     */
    private const NAMESPACE = 'bbjd/v1';

    /**
     * Initialize the routes
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void
    {
        // Get ad for a specific slot
        register_rest_route(self::NAMESPACE, '/ad/(?P<slot>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSlotAd'],
            'permission_callback' => '__return_true',
            'args' => [
                'slot' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'device' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['desktop', 'mobile', 'auto'],
                    'default' => 'auto',
                ],
            ],
        ]);

        // Get multiple slots at once
        register_rest_route(self::NAMESPACE, '/ads', [
            'methods' => 'GET',
            'callback' => [$this, 'getMultipleSlots'],
            'permission_callback' => '__return_true',
            'args' => [
                'slots' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Comma-separated list of slot slugs',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'device' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['desktop', 'mobile'],
                    'default' => 'desktop',
                ],
            ],
        ]);

        // Get header/footer scripts (for ad networks)
        register_rest_route(self::NAMESPACE, '/ad-scripts', [
            'methods' => 'GET',
            'callback' => [$this, 'getAdScripts'],
            'permission_callback' => '__return_true',
        ]);

        // Check if user should see ads (for logged-in users)
        register_rest_route(self::NAMESPACE, '/ads/should-show', [
            'methods' => 'GET',
            'callback' => [$this, 'shouldShowAds'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get ad content for a specific slot
     */
    public function getSlotAd(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $slotSlug = $request->get_param('slot');
        $device = $request->get_param('device');

        $adManager = AdManager::getInstance();

        // Check if user should see ads based on role
        // Note: For API requests, we check the authenticated user if JWT is provided
        $shouldShow = $this->checkUserShouldSeeAds($request);

        if (!$shouldShow) {
            return new WP_REST_Response([
                'show' => false,
                'reason' => 'user_role',
            ], 200);
        }

        // Get the slot
        $slot = $adManager->slots()->findBySlug($slotSlug);

        if (!$slot || !$slot->isActive()) {
            return new WP_REST_Response([
                'show' => false,
                'reason' => 'slot_not_found',
            ], 200);
        }

        // Get ads for this slot
        $ads = $adManager->ads()->findBySlot($slot->id);

        if (empty($ads)) {
            return new WP_REST_Response([
                'show' => false,
                'reason' => 'no_ads',
            ], 200);
        }

        // Get the first/highest priority ad
        $ad = $ads[0];

        // Determine content based on device
        $content = $this->getAdContent($ad, $device);

        return new WP_REST_Response([
            'show' => true,
            'slot' => $slotSlug,
            'content' => $content,
            'desktop_content' => $ad->contentDesktop,
            'mobile_content' => $ad->contentMobile ?: $ad->contentDesktop,
            'show_branding' => (bool) $slot->getSetting('show_branding', false),
        ], 200);
    }

    /**
     * Get multiple slot ads at once
     */
    public function getMultipleSlots(WP_REST_Request $request): WP_REST_Response
    {
        $slotsParam = $request->get_param('slots');
        $device = $request->get_param('device');
        $slugs = array_map('trim', explode(',', $slotsParam));

        $adManager = AdManager::getInstance();
        $shouldShow = $this->checkUserShouldSeeAds($request);

        $results = [];

        foreach ($slugs as $slotSlug) {
            if (empty($slotSlug)) continue;

            if (!$shouldShow) {
                $results[$slotSlug] = [
                    'show' => false,
                    'reason' => 'user_role',
                ];
                continue;
            }

            $slot = $adManager->slots()->findBySlug($slotSlug);

            if (!$slot || !$slot->isActive()) {
                $results[$slotSlug] = [
                    'show' => false,
                    'reason' => 'slot_not_found',
                ];
                continue;
            }

            $ads = $adManager->ads()->findBySlot($slot->id);

            if (empty($ads)) {
                $results[$slotSlug] = [
                    'show' => false,
                    'reason' => 'no_ads',
                ];
                continue;
            }

            $ad = $ads[0];

            $results[$slotSlug] = [
                'show' => true,
                'content' => $this->getAdContent($ad, $device),
                'desktop_content' => $ad->contentDesktop,
                'mobile_content' => $ad->contentMobile ?: $ad->contentDesktop,
                'show_branding' => (bool) $slot->getSetting('show_branding', false),
            ];
        }

        return new WP_REST_Response($results, 200);
    }

    /**
     * Get header/footer scripts (global + ad network)
     */
    public function getAdScripts(WP_REST_Request $request): WP_REST_Response
    {
        $adManager = AdManager::getInstance();
        $settings = $adManager->getSettings();

        $shouldShowAds = $this->checkUserShouldSeeAds($request);

        // Global scripts always load for everyone
        $globalHeader = $settings['global_header_code'] ?? '';
        $globalFooter = $settings['global_footer_code'] ?? '';

        // Ad scripts only load for non-premium users
        $adHeader = $shouldShowAds ? ($settings['ad_header_code'] ?? $settings['header_code'] ?? '') : '';
        $adFooter = $shouldShowAds ? ($settings['ad_footer_code'] ?? $settings['footer_code'] ?? '') : '';

        return new WP_REST_Response([
            'show_ads' => $shouldShowAds,
            'global_header' => $globalHeader,
            'global_footer' => $globalFooter,
            'ad_header' => $adHeader,
            'ad_footer' => $adFooter,
            // Combined for convenience — what the frontend should actually inject
            'header' => trim($globalHeader . "\n" . $adHeader),
            'footer' => trim($globalFooter . "\n" . $adFooter),
        ], 200);
    }

    /**
     * Check if current user should see ads
     */
    public function shouldShowAds(WP_REST_Request $request): WP_REST_Response
    {
        $shouldShow = $this->checkUserShouldSeeAds($request);

        return new WP_REST_Response([
            'show_ads' => $shouldShow,
            'user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
        ], 200);
    }

    /**
     * Get ad content based on device type
     */
    private function getAdContent($ad, string $device): string
    {
        if ($device === 'mobile') {
            return $ad->contentMobile ?: $ad->contentDesktop;
        }

        if ($device === 'desktop') {
            return $ad->contentDesktop;
        }

        // Auto - return both wrapped
        return $ad->contentDesktop;
    }

    /**
     * Check if the requesting user should see ads
     */
    private function checkUserShouldSeeAds(WP_REST_Request $request): bool
    {
        // Global kill switch — if ads are disabled, nobody sees them
        $adManager = AdManager::getInstance();
        if (!$adManager->getSetting('enable_ads', true)) {
            return false;
        }

        // If not logged in, show ads
        if (!is_user_logged_in()) {
            return true;
        }

        // Get the ad manager settings
        $hiddenRoles = $adManager->getSetting('global_hidden_roles', []);

        $user = wp_get_current_user();

        // Check if user has any of the hidden roles
        foreach ($user->roles as $role) {
            if (in_array($role, $hiddenRoles, true)) {
                return false;
            }
        }

        return true;
    }
}
