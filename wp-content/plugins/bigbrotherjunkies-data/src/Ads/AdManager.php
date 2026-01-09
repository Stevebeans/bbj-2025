<?php

namespace BigBrotherJunkies\Data\Ads;

use BigBrotherJunkies\Data\Ads\Models\Ad;
use BigBrotherJunkies\Data\Ads\Models\Slot;
use BigBrotherJunkies\Data\Ads\Repositories\AdRepository;
use BigBrotherJunkies\Data\Ads\Repositories\SlotRepository;

/**
 * Main Ad Manager service
 */
class AdManager
{
    private static ?AdManager $instance = null;

    private AdRepository $adRepository;
    private SlotRepository $slotRepository;
    private ConditionChecker $conditionChecker;

    /**
     * Settings option name
     */
    public const SETTINGS_OPTION = 'bbjd_ad_settings';

    /**
     * Get singleton instance
     */
    public static function getInstance(): AdManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->adRepository = new AdRepository();
        $this->slotRepository = new SlotRepository();
        $this->conditionChecker = new ConditionChecker();
    }

    /**
     * Get the ad repository
     */
    public function ads(): AdRepository
    {
        return $this->adRepository;
    }

    /**
     * Get the slot repository
     */
    public function slots(): SlotRepository
    {
        return $this->slotRepository;
    }

    /**
     * Get the condition checker
     */
    public function conditions(): ConditionChecker
    {
        return $this->conditionChecker;
    }

    /**
     * Display ads for a slot
     */
    public function displaySlot(string $slotSlug, array $options = []): void
    {
        // Global check first (admin pages, global role hiding, page override)
        if (!$this->shouldShowAds()) {
            return;
        }

        $slot = $this->slotRepository->findBySlug($slotSlug);
        if (!$slot || !$slot->isActive()) {
            return;
        }

        // Per-slot check (slot-specific role hiding)
        if (!$this->conditionChecker->shouldShowSlot($slot)) {
            return;
        }

        $ads = $this->adRepository->findBySlot($slot->id);
        if (empty($ads)) {
            return;
        }

        // Pick an ad (for now, just use the highest priority/weight)
        $ad = $ads[0];

        $this->renderAd($ad, $slot, $options);
    }

    /**
     * Display responsive ads (desktop + mobile)
     */
    public function displayResponsive(string $slotSlug, array $options = []): void
    {
        // Global check first
        if (!$this->shouldShowAds()) {
            return;
        }

        $slot = $this->slotRepository->findBySlug($slotSlug);
        if (!$slot || !$slot->isActive()) {
            return;
        }

        // Per-slot check (slot-specific role hiding)
        if (!$this->conditionChecker->shouldShowSlot($slot)) {
            return;
        }

        $ads = $this->adRepository->findBySlot($slot->id);
        if (empty($ads)) {
            return;
        }

        $ad = $ads[0];
        $extraClass = $options['class'] ?? '';
        $hideMobile = $options['hide_mobile'] ?? false;

        // Desktop version
        $desktopContent = $ad->contentDesktop;
        if (!empty($desktopContent)) {
            printf(
                '<div class="bbjd-ad bbjd-ad-desktop hidden md:block %s">%s</div>',
                esc_attr($extraClass),
                $desktopContent
            );
        }

        // Mobile version
        if (!$hideMobile) {
            $mobileContent = !empty($ad->contentMobile) ? $ad->contentMobile : $desktopContent;
            if (!empty($mobileContent)) {
                printf(
                    '<div class="bbjd-ad bbjd-ad-mobile block md:hidden %s">%s</div>',
                    esc_attr($extraClass),
                    $mobileContent
                );
            }
        }
    }

    /**
     * Check if ads should show on the current page/context
     */
    public function shouldShowAds(?int $postId = null): bool
    {
        return $this->conditionChecker->shouldShowAds($postId);
    }

    /**
     * Get ads for a slot (for custom rendering)
     */
    public function getAdsForSlot(string $slotSlug): array
    {
        $slot = $this->slotRepository->findBySlug($slotSlug);
        if (!$slot) {
            return [];
        }

        return $this->adRepository->findBySlot($slot->id);
    }

    /**
     * Get settings
     */
    public function getSettings(): array
    {
        $defaults = [
            'default_user_roles_hide' => ['administrator', 'supporter', 'updater', 'comment_mod', 'second_in_command'],
            'auto_insert_post_types' => ['post'],
            'auto_insert_default_interval' => 4,
            'auto_insert_max_per_post' => 3,
            'enable_tracking' => false,
            'cache_ttl' => 300,
            'lazy_load_ads' => false,
        ];

        $settings = get_option(self::SETTINGS_OPTION, []);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): bool
    {
        return update_option(self::SETTINGS_OPTION, $settings);
    }

    /**
     * Get a specific setting
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Render an ad
     */
    private function renderAd(Ad $ad, Slot $slot, array $options = []): void
    {
        $content = $ad->getContent($this->isMobile());

        if (empty($content)) {
            return;
        }

        $extraClass = $options['class'] ?? '';

        printf(
            '<div class="bbjd-ad bbjd-slot-%s %s" data-ad-id="%d" data-slot-id="%d">%s</div>',
            esc_attr($slot->slug),
            esc_attr($extraClass),
            $ad->id,
            $slot->id,
            $content
        );
    }

    /**
     * Simple mobile detection
     */
    private function isMobile(): bool
    {
        if (!function_exists('wp_is_mobile')) {
            return false;
        }
        return wp_is_mobile();
    }
}
