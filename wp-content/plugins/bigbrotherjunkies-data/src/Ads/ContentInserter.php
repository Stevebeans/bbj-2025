<?php

namespace BigBrotherJunkies\Data\Ads;

use BigBrotherJunkies\Data\Ads\Models\Slot;
use BigBrotherJunkies\Data\Ads\Repositories\SlotRepository;
use BigBrotherJunkies\Data\Ads\Repositories\AdRepository;

/**
 * Handles auto-insertion of ads into content
 */
class ContentInserter
{
    private SlotRepository $slotRepository;
    private AdRepository $adRepository;
    private ConditionChecker $conditionChecker;

    public function __construct()
    {
        $this->slotRepository = new SlotRepository();
        $this->adRepository = new AdRepository();
        $this->conditionChecker = new ConditionChecker();
    }

    /**
     * Initialize content filtering
     */
    public function init(): void
    {
        add_filter('the_content', [$this, 'insertAdsInContent'], 20);
    }

    /**
     * Insert ads into post content
     */
    public function insertAdsInContent(string $content): string
    {
        // Don't process in admin, feeds, or if ads shouldn't show
        if (is_admin() || is_feed() || !is_singular()) {
            return $content;
        }

        $postId = get_the_ID();
        $postType = get_post_type($postId);

        // Check if ads should show
        if (!$this->conditionChecker->shouldShowAds($postId)) {
            return $content;
        }

        // Check if post type is allowed for auto-insertion
        if (!$this->conditionChecker->shouldShowForPostType($postType)) {
            return $content;
        }

        // Get auto-insert slots for content
        $slots = $this->slotRepository->findAll([
            'type' => Slot::TYPE_AUTO_CONTENT,
            'status' => 'active',
        ]);

        if (empty($slots)) {
            return $content;
        }

        // Process each auto-insert slot
        foreach ($slots as $slot) {
            $content = $this->processSlot($content, $slot);
        }

        return $content;
    }

    /**
     * Process a single slot's insertion rules
     */
    private function processSlot(string $content, Slot $slot): string
    {
        $ads = $this->adRepository->findBySlot($slot->id);
        if (empty($ads)) {
            return $content;
        }

        // Get slot settings
        $settings = $slot->settings;
        $interval = $settings['paragraph_interval'] ?? 4;
        $maxInsertions = $settings['max_insertions'] ?? 3;
        $skipFirst = $settings['skip_first_paragraphs'] ?? 2;
        $minParagraphs = $settings['min_paragraphs'] ?? 6;

        // Split content into paragraphs
        $paragraphs = $this->splitIntoParagraphs($content);
        $paragraphCount = count($paragraphs);

        // Don't insert if not enough paragraphs
        if ($paragraphCount < $minParagraphs) {
            return $content;
        }

        // Calculate insertion points
        $insertionPoints = $this->calculateInsertionPoints(
            $paragraphCount,
            $interval,
            $maxInsertions,
            $skipFirst
        );

        if (empty($insertionPoints)) {
            return $content;
        }

        // Get ad to insert (for now, use first ad)
        $ad = $ads[0];
        $adHtml = $this->renderAdForInsertion($ad, $slot);

        // Insert ads at calculated points (in reverse to preserve positions)
        $insertionPoints = array_reverse($insertionPoints);
        foreach ($insertionPoints as $position) {
            array_splice($paragraphs, $position, 0, [$adHtml]);
        }

        return implode("\n", $paragraphs);
    }

    /**
     * Split content into paragraphs
     */
    private function splitIntoParagraphs(string $content): array
    {
        // Split by closing paragraph tags
        $parts = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        $paragraphs = [];
        $current = '';

        foreach ($parts as $part) {
            $current .= $part;
            if (preg_match('/<\/p>/i', $part)) {
                $paragraphs[] = $current;
                $current = '';
            }
        }

        // Add any remaining content
        if (!empty(trim($current))) {
            $paragraphs[] = $current;
        }

        return array_filter($paragraphs, function ($p) {
            return !empty(trim(strip_tags($p)));
        });
    }

    /**
     * Calculate where to insert ads
     */
    private function calculateInsertionPoints(
        int $paragraphCount,
        int $interval,
        int $maxInsertions,
        int $skipFirst
    ): array {
        $points = [];
        $insertCount = 0;

        for ($i = $skipFirst; $i < $paragraphCount && $insertCount < $maxInsertions; $i += $interval) {
            // Don't insert right at the end
            if ($i >= $paragraphCount - 1) {
                break;
            }
            $points[] = $i;
            $insertCount++;
        }

        return $points;
    }

    /**
     * Render ad HTML for insertion
     */
    private function renderAdForInsertion($ad, Slot $slot): string
    {
        $content = $ad->contentDesktop;

        if (empty($content)) {
            return '';
        }

        return sprintf(
            '<div class="bbjd-ad bbjd-ad-auto bbjd-slot-%s" data-ad-id="%d" data-slot-id="%d">%s</div>',
            esc_attr($slot->slug),
            $ad->id,
            $slot->id,
            $content
        );
    }

    /**
     * Insert ads between feed updates (for index page)
     */
    public function insertAdsBetweenItems(array $items, string $slotSlug, int $interval = 5): array
    {
        if (!$this->conditionChecker->shouldShowAds()) {
            return $items;
        }

        $slot = $this->slotRepository->findBySlug($slotSlug);
        if (!$slot || !$slot->isActive()) {
            return $items;
        }

        $ads = $this->adRepository->findBySlot($slot->id);
        if (empty($ads)) {
            return $items;
        }

        $ad = $ads[0];
        $adHtml = $this->renderAdForInsertion($ad, $slot);

        $result = [];
        $count = 0;

        foreach ($items as $item) {
            $result[] = $item;
            $count++;

            if ($count % $interval === 0) {
                $result[] = ['type' => 'ad', 'html' => $adHtml];
            }
        }

        return $result;
    }
}
