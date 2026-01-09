<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Ads\Models\Ad;
use BigBrotherJunkies\Data\Ads\Repositories\AdRepository;
use BigBrotherJunkies\Data\Ads\Repositories\SlotRepository;
use BigBrotherJunkies\Data\Database\Schema;

/**
 * Ad edit/create admin page
 */
class AdEditPage
{
    public const MENU_SLUG = 'bbjd-ad-edit';

    private AdRepository $adRepository;
    private SlotRepository $slotRepository;

    public function __construct()
    {
        $this->adRepository = new AdRepository();
        $this->slotRepository = new SlotRepository();
    }

    /**
     * Handle actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_save_ad', [$this, 'handleSaveAd']);
    }

    /**
     * Handle save ad action
     */
    public function handleSaveAd(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_save_ad');

        $adId = intval($_POST['ad_id'] ?? 0);
        $isNew = $adId === 0;

        // Get or create ad
        $ad = $isNew ? new Ad() : $this->adRepository->find($adId);
        if (!$ad) {
            $ad = new Ad();
        }

        // Populate ad data
        $ad->name = sanitize_text_field($_POST['ad_name'] ?? '');
        $ad->slug = sanitize_title($_POST['ad_slug'] ?? $ad->name);
        $ad->type = sanitize_text_field($_POST['ad_type'] ?? 'html');
        $ad->contentDesktop = wp_unslash($_POST['content_desktop'] ?? '');
        $ad->contentMobile = wp_unslash($_POST['content_mobile'] ?? '');
        $ad->status = sanitize_text_field($_POST['ad_status'] ?? 'draft');
        $ad->priority = intval($_POST['ad_priority'] ?? 0);

        // Save ad
        if ($isNew) {
            $adId = $this->adRepository->create($ad);
        } else {
            $this->adRepository->update($ad);
        }

        // Handle slot assignments
        $this->saveSlotAssignments($adId, $_POST['slot_ids'] ?? []);

        wp_redirect(add_query_arg([
            'page' => AdsListPage::MENU_SLUG,
            'message' => 'saved',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Save slot assignments for an ad
     */
    private function saveSlotAssignments(int $adId, array $slotIds): void
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ASSIGNMENTS);

        // Delete existing assignments
        $wpdb->delete($table, ['ad_id' => $adId]);

        // Create new assignments
        foreach ($slotIds as $slotId) {
            $slotId = intval($slotId);
            if ($slotId > 0) {
                $wpdb->insert($table, [
                    'ad_id' => $adId,
                    'slot_id' => $slotId,
                    'weight' => 100,
                ]);
            }
        }
    }

    /**
     * Get slot assignments for an ad
     */
    private function getSlotAssignments(int $adId): array
    {
        global $wpdb;
        $table = Schema::table(Schema::TABLE_ASSIGNMENTS);

        $results = $wpdb->get_col(
            $wpdb->prepare("SELECT slot_id FROM {$table} WHERE ad_id = %d", $adId)
        );

        return array_map('intval', $results);
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $adId = intval($_GET['ad_id'] ?? 0);
        $ad = $adId ? $this->adRepository->find($adId) : new Ad();
        $isNew = !$ad || $ad->id === 0;

        if (!$ad) {
            $ad = new Ad();
        }

        $slots = $this->slotRepository->findAll();
        $assignedSlots = $adId ? $this->getSlotAssignments($adId) : [];
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    <?php echo $isNew ? 'Create New Ad' : 'Edit Ad'; ?>
                </h1>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('bbjd_save_ad'); ?>
                    <input type="hidden" name="action" value="bbjd_save_ad">
                    <input type="hidden" name="ad_id" value="<?php echo esc_attr($ad->id); ?>">

                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <!-- Basic Info -->
                        <div class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-2 bbjd-gap-6 bbjd-mb-6">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Name *
                                </label>
                                <input type="text" name="ad_name" value="<?php echo esc_attr($ad->name); ?>" required
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500">
                            </div>
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Slug
                                </label>
                                <input type="text" name="ad_slug" value="<?php echo esc_attr($ad->slug); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500"
                                       placeholder="auto-generated from name">
                            </div>
                        </div>

                        <div class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-3 bbjd-gap-6 bbjd-mb-6">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Type
                                </label>
                                <select name="ad_type"
                                        class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500">
                                    <?php foreach (Ad::getTypes() as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($ad->type, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Status
                                </label>
                                <select name="ad_status"
                                        class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500">
                                    <?php foreach (Ad::getStatuses() as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($ad->status, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Priority
                                </label>
                                <input type="number" name="ad_priority" value="<?php echo esc_attr($ad->priority); ?>"
                                       class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500">
                            </div>
                        </div>

                        <!-- Ad Content -->
                        <div class="bbjd-mb-6">
                            <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                Desktop Content
                            </label>
                            <textarea name="content_desktop" rows="8"
                                      class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500 bbjd-font-mono bbjd-text-sm"><?php echo esc_textarea($ad->contentDesktop); ?></textarea>
                        </div>

                        <div class="bbjd-mb-6">
                            <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                Mobile Content <span class="bbjd-text-gray-400">(optional - falls back to desktop)</span>
                            </label>
                            <textarea name="content_mobile" rows="6"
                                      class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-shadow-sm focus:bbjd-outline-none focus:bbjd-ring-primary500 focus:bbjd-border-primary500 bbjd-font-mono bbjd-text-sm"><?php echo esc_textarea($ad->contentMobile); ?></textarea>
                        </div>

                        <!-- Slot Assignments -->
                        <?php if (!empty($slots)): ?>
                        <div>
                            <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                Assign to Slots
                            </label>
                            <div class="bbjd-grid bbjd-grid-cols-2 md:bbjd-grid-cols-3 bbjd-gap-2">
                                <?php foreach ($slots as $slot): ?>
                                <label class="bbjd-flex bbjd-items-center bbjd-space-x-2">
                                    <input type="checkbox" name="slot_ids[]" value="<?php echo esc_attr($slot->id); ?>"
                                           <?php checked(in_array($slot->id, $assignedSlots)); ?>
                                           class="bbjd-rounded bbjd-border-gray-300 bbjd-text-primary500 focus:bbjd-ring-primary500">
                                    <span class="bbjd-text-sm bbjd-text-gray-700"><?php echo esc_html($slot->name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="bbjd-flex bbjd-justify-between">
                        <a href="<?php echo admin_url('admin.php?page=' . AdsListPage::MENU_SLUG); ?>"
                           class="bbjd-text-gray-600 hover:bbjd-text-gray-800">
                            &larr; Back to Ads
                        </a>
                        <button type="submit"
                                class="bbjd-bg-primary500 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                            <?php echo $isNew ? 'Create Ad' : 'Update Ad'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
