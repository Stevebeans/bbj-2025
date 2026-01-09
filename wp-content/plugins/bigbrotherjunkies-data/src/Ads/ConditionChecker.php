<?php

namespace BigBrotherJunkies\Data\Ads;

use BigBrotherJunkies\Data\Ads\Models\Slot;

/**
 * Evaluates conditions for ad display
 */
class ConditionChecker
{
    /**
     * Post meta key for hiding ads on a page
     */
    public const META_KEY_HIDE_ADS = '_bbjd_hide_ads';

    /**
     * Cache group for condition results
     */
    private const CACHE_GROUP = 'bbjd_conditions';

    /**
     * Check if ads should show for the current context (global check)
     * This checks: admin pages, global role hiding, page-level override, excluded pages
     */
    public function shouldShowAds(?int $postId = null): bool
    {
        // Never show in admin
        if (is_admin() && !wp_doing_ajax()) {
            return false;
        }

        // Check GLOBAL role hiding (complete ad-free experience)
        if ($this->userHasGlobalHiddenRole()) {
            return false;
        }

        // Check page-level override
        $postId = $postId ?? get_the_ID();
        if ($postId && $this->isAdFreePost($postId)) {
            return false;
        }

        // Check specific pages (login, etc.)
        if ($this->isExcludedPage()) {
            return false;
        }

        // Allow filtering
        return apply_filters('bbjd_should_show_ads', true, $postId);
    }

    /**
     * Check if a specific slot should show for the current user
     * This is checked AFTER shouldShowAds() passes
     *
     * @param Slot $slot The slot to check
     * @return bool True if the slot should be displayed
     */
    public function shouldShowSlot(Slot $slot): bool
    {
        // Check per-slot role hiding
        if ($this->userHasSlotHiddenRole($slot)) {
            return false;
        }

        // Allow filtering
        return apply_filters('bbjd_should_show_slot', true, $slot);
    }

    /**
     * Check if current user has a GLOBAL ad-free role
     * These roles see NO ads anywhere on the site
     */
    public function userHasGlobalHiddenRole(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $globalHiddenRoles = $this->getGlobalHiddenRoles();
        if (empty($globalHiddenRoles)) {
            return false;
        }

        $user = wp_get_current_user();

        foreach ($globalHiddenRoles as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current user has a role that hides a specific slot
     *
     * @param Slot $slot The slot to check
     * @return bool True if user should NOT see this slot
     */
    public function userHasSlotHiddenRole(Slot $slot): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $slotHiddenRoles = $slot->getHiddenRoles();
        if (empty($slotHiddenRoles)) {
            return false;
        }

        $user = wp_get_current_user();

        foreach ($slotHiddenRoles as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get roles that should not see ANY ads (global setting)
     */
    public function getGlobalHiddenRoles(): array
    {
        $settings = get_option(AdManager::SETTINGS_OPTION, []);
        return $settings['global_hidden_roles'] ?? [];
    }

    /**
     * Legacy method - now uses global hidden roles
     * @deprecated Use getGlobalHiddenRoles() instead
     */
    public function getHiddenRoles(): array
    {
        return $this->getGlobalHiddenRoles();
    }

    /**
     * Legacy method - now uses global role check
     * @deprecated Use userHasGlobalHiddenRole() instead
     */
    public function userHasHiddenRole(): bool
    {
        return $this->userHasGlobalHiddenRole();
    }

    /**
     * Check if a specific post has ads disabled
     */
    public function isAdFreePost(int $postId): bool
    {
        $cacheKey = "ad_free:{$postId}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached === '1';
        }

        $hideAds = get_post_meta($postId, self::META_KEY_HIDE_ADS, true);
        $result = !empty($hideAds);

        wp_cache_set($cacheKey, $result ? '1' : '0', self::CACHE_GROUP, 300);

        return $result;
    }

    /**
     * Check if current page is excluded from ads
     */
    public function isExcludedPage(): bool
    {
        // Login page
        if (is_page('log-in') || is_page('login')) {
            return true;
        }

        // Registration page
        if (is_page('registration') || is_page('register')) {
            return true;
        }

        // Allow filtering excluded pages
        $excludedPages = apply_filters('bbjd_excluded_pages', []);
        if (!empty($excludedPages) && is_page($excludedPages)) {
            return true;
        }

        return false;
    }

    /**
     * Check if ads should show for a specific post type
     */
    public function shouldShowForPostType(string $postType): bool
    {
        $settings = get_option(AdManager::SETTINGS_OPTION, []);
        $allowedTypes = $settings['auto_insert_post_types'] ?? ['post'];

        return in_array($postType, $allowedTypes, true);
    }

    /**
     * Bust cache for a post
     */
    public static function bustCache(int $postId): void
    {
        wp_cache_delete("ad_free:{$postId}", self::CACHE_GROUP);
    }
}
