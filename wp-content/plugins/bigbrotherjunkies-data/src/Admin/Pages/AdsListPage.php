<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Ads\AdManager;
use BigBrotherJunkies\Data\Ads\Models\Ad;
use BigBrotherJunkies\Data\Ads\Repositories\AdRepository;

/**
 * Ads list admin page
 */
class AdsListPage
{
    public const MENU_SLUG = 'bbjd-ads';

    private AdRepository $adRepository;

    public function __construct()
    {
        $this->adRepository = new AdRepository();
    }

    /**
     * Handle actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_delete_ad', [$this, 'handleDeleteAd']);
    }

    /**
     * Handle delete ad action
     */
    public function handleDeleteAd(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $adId = intval($_GET['ad_id'] ?? 0);
        check_admin_referer('bbjd_delete_ad_' . $adId);

        if ($adId) {
            $this->adRepository->delete($adId);
        }

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'deleted',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $ads = $this->adRepository->findAll(['orderby' => 'name', 'order' => 'ASC']);
        $message = $_GET['message'] ?? '';
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6">
                <div class="bbjd-flex bbjd-justify-between bbjd-items-center bbjd-mb-6">
                    <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500">
                        Ads
                    </h1>
                    <a href="<?php echo admin_url('admin.php?page=' . AdEditPage::MENU_SLUG); ?>"
                       class="bbjd-bg-primary500 bbjd-text-white bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                        Add New Ad
                    </a>
                </div>

                <?php $this->renderMessages($message); ?>

                <?php if (empty($ads)): ?>
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-8 bbjd-text-center">
                        <p class="bbjd-text-gray-500 bbjd-mb-4">No ads found. Create your first ad to get started.</p>
                        <a href="<?php echo admin_url('admin.php?page=' . AdEditPage::MENU_SLUG); ?>"
                           class="bbjd-bg-second500 bbjd-text-primaryHard bbjd-px-4 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-secondSoft bbjd-transition-colors">
                            Create Ad
                        </a>
                    </div>
                <?php else: ?>
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-overflow-hidden">
                        <table class="bbjd-min-w-full bbjd-divide-y bbjd-divide-gray-200">
                            <thead class="bbjd-bg-gray-50">
                                <tr>
                                    <th class="bbjd-px-6 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase bbjd-tracking-wider">Name</th>
                                    <th class="bbjd-px-6 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase bbjd-tracking-wider">Type</th>
                                    <th class="bbjd-px-6 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase bbjd-tracking-wider">Status</th>
                                    <th class="bbjd-px-6 bbjd-py-3 bbjd-text-left bbjd-text-xs bbjd-font-medium bbjd-text-gray-500 bbjd-uppercase bbjd-tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bbjd-bg-white bbjd-divide-y bbjd-divide-gray-200">
                                <?php foreach ($ads as $ad): ?>
                                <tr>
                                    <td class="bbjd-px-6 bbjd-py-4 bbjd-whitespace-nowrap">
                                        <div class="bbjd-text-sm bbjd-font-medium bbjd-text-gray-900">
                                            <?php echo esc_html($ad->name); ?>
                                        </div>
                                        <div class="bbjd-text-sm bbjd-text-gray-500">
                                            <?php echo esc_html($ad->slug); ?>
                                        </div>
                                    </td>
                                    <td class="bbjd-px-6 bbjd-py-4 bbjd-whitespace-nowrap bbjd-text-sm bbjd-text-gray-500">
                                        <?php echo esc_html(Ad::getTypes()[$ad->type] ?? $ad->type); ?>
                                    </td>
                                    <td class="bbjd-px-6 bbjd-py-4 bbjd-whitespace-nowrap">
                                        <?php $this->renderStatusBadge($ad->status); ?>
                                    </td>
                                    <td class="bbjd-px-6 bbjd-py-4 bbjd-whitespace-nowrap bbjd-text-sm bbjd-font-medium">
                                        <a href="<?php echo admin_url('admin.php?page=' . AdEditPage::MENU_SLUG . '&ad_id=' . $ad->id); ?>"
                                           class="bbjd-text-primary500 hover:bbjd-text-primaryHard bbjd-mr-4">
                                            Edit
                                        </a>
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=bbjd_delete_ad&ad_id=' . $ad->id),
                                            'bbjd_delete_ad_' . $ad->id
                                        ); ?>"
                                           class="bbjd-text-thirdColor hover:bbjd-text-red-700"
                                           onclick="return confirm('Are you sure you want to delete this ad?');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render status badge
     */
    private function renderStatusBadge(string $status): void
    {
        $colors = [
            'active' => 'bbjd-bg-green-100 bbjd-text-green-800',
            'paused' => 'bbjd-bg-yellow-100 bbjd-text-yellow-800',
            'draft' => 'bbjd-bg-gray-100 bbjd-text-gray-800',
        ];

        $color = $colors[$status] ?? $colors['draft'];
        ?>
        <span class="bbjd-inline-flex bbjd-items-center bbjd-px-2.5 bbjd-py-0.5 bbjd-rounded-full bbjd-text-xs bbjd-font-medium <?php echo $color; ?>">
            <?php echo esc_html(ucfirst($status)); ?>
        </span>
        <?php
    }

    /**
     * Render messages
     */
    private function renderMessages(string $message): void
    {
        if ($message === 'deleted') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>Ad deleted successfully.</p>
            </div>
            <?php
        } elseif ($message === 'saved') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>Ad saved successfully.</p>
            </div>
            <?php
        }
    }
}
