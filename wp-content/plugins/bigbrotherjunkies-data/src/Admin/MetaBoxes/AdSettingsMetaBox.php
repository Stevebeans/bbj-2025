<?php

namespace BigBrotherJunkies\Data\Admin\MetaBoxes;

use BigBrotherJunkies\Data\Ads\ConditionChecker;

/**
 * Meta box for per-page ad settings
 */
class AdSettingsMetaBox
{
    private const NONCE_ACTION = 'bbjd_ad_settings';
    private const NONCE_NAME = 'bbjd_ad_settings_nonce';

    /**
     * Post types to show the meta box on
     */
    private array $postTypes = ['post', 'page', 'live-feed-updates'];

    /**
     * Initialize the meta box
     */
    public function init(): void
    {
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('save_post', [$this, 'save'], 10, 2);

        // Bust cache on meta update
        add_action('updated_post_meta', [$this, 'onMetaUpdate'], 10, 4);
        add_action('added_post_meta', [$this, 'onMetaUpdate'], 10, 4);
        add_action('deleted_post_meta', [$this, 'onMetaUpdate'], 10, 4);
    }

    /**
     * Register the meta box
     */
    public function register(): void
    {
        add_meta_box(
            'bbjd-ad-settings',
            __('Ad Settings', 'bigbrotherjunkies-data'),
            [$this, 'render'],
            $this->postTypes,
            'side',
            'default'
        );
    }

    /**
     * Render the meta box
     */
    public function render(\WP_Post $post): void
    {
        $hideAds = get_post_meta($post->ID, ConditionChecker::META_KEY_HIDE_ADS, true);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <div class="bbjd-meta-box">
            <label class="selectit" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox"
                       name="bbjd_hide_ads"
                       value="1"
                       <?php checked($hideAds, '1'); ?>>
                <span><?php esc_html_e('Hide ads on this page', 'bigbrotherjunkies-data'); ?></span>
            </label>
            <p class="description" style="margin-top: 8px; color: #666;">
                <?php esc_html_e('Check to hide all ad slots on this specific post/page.', 'bigbrotherjunkies-data'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save the meta box data
     */
    public function save(int $postId, \WP_Post $post): void
    {
        // Security checks
        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }

        if (!wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save the meta
        $hideAds = isset($_POST['bbjd_hide_ads']) ? '1' : '';

        if ($hideAds) {
            update_post_meta($postId, ConditionChecker::META_KEY_HIDE_ADS, '1');
        } else {
            delete_post_meta($postId, ConditionChecker::META_KEY_HIDE_ADS);
        }
    }

    /**
     * Handle meta update for cache busting
     */
    public function onMetaUpdate(int $metaId, int $postId, string $metaKey, $metaValue): void
    {
        if ($metaKey === ConditionChecker::META_KEY_HIDE_ADS) {
            ConditionChecker::bustCache($postId);
        }
    }
}
