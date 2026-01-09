<?php

namespace BigBrotherJunkies\Data\Hooks;

use BigBrotherJunkies\Data\Ads\AdManager;

/**
 * Provides theme integration functions
 */
class ThemeIntegration
{
    /**
     * Register global functions for theme use
     */
    public static function register(): void
    {
        // Functions are defined at the bottom of this file
    }
}

/**
 * Display ad in a slot
 *
 * @param string $slot Slot slug
 * @param array $options Optional display options
 */
function bbjd_ad(string $slot, array $options = []): void
{
    AdManager::getInstance()->displaySlot($slot, $options);
}

/**
 * Display responsive ad (desktop + mobile variants)
 *
 * @param string $slot Slot slug
 * @param array $options Optional display options (class, hide_mobile)
 */
function bbjd_ad_responsive(string $slot, array $options = []): void
{
    AdManager::getInstance()->displayResponsive($slot, $options);
}

/**
 * Check if ads should show on current page
 *
 * @param int|null $postId Optional post ID
 * @return bool
 */
function bbjd_should_show_ads(?int $postId = null): bool
{
    return AdManager::getInstance()->shouldShowAds($postId);
}

/**
 * Get ads for a slot (for custom rendering)
 *
 * @param string $slot Slot slug
 * @return array Array of Ad objects
 */
function bbjd_get_slot_ads(string $slot): array
{
    return AdManager::getInstance()->getAdsForSlot($slot);
}

/**
 * Get an ad manager setting
 *
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed
 */
function bbjd_get_setting(string $key, $default = null)
{
    return AdManager::getInstance()->getSetting($key, $default);
}
