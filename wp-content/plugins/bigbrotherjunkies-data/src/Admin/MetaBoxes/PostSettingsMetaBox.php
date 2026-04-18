<?php

namespace BigBrotherJunkies\Data\Admin\MetaBoxes;

/**
 * Post Settings Meta Box
 *
 * Adds settings like "Live Feed Thread" checkbox to posts
 */
class PostSettingsMetaBox
{
    private const META_KEY = '_bbjd_live_feed_thread';
    private const NONCE_ACTION = 'bbjd_post_settings_save';
    private const NONCE_NAME = 'bbjd_post_settings_nonce';

    public function init(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRestField']);
    }

    /**
     * Add meta box to post editor
     */
    public function addMetaBox(): void
    {
        add_meta_box(
            'bbjd_post_settings',
            __('BBJ Post Settings', 'bigbrotherjunkies-data'),
            [$this, 'renderMetaBox'],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box HTML
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $liveFeedThread = get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="bbjd_live_feed_thread"
                    value="1"
                    <?php checked($liveFeedThread, '1'); ?>
                />
                <?php _e('Live Feed Thread', 'bigbrotherjunkies-data'); ?>
            </label>
        </p>
        <p class="description">
            <?php _e('When checked, live feed updates from this post\'s date will be automatically displayed at the end of the article.', 'bigbrotherjunkies-data'); ?>
        </p>
        <?php
    }

    /**
     * Save meta box data
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        // Verify nonce
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save the checkbox value
        $liveFeedThread = isset($_POST['bbjd_live_feed_thread']) ? '1' : '0';
        update_post_meta($postId, self::META_KEY, $liveFeedThread);
    }

    /**
     * Register REST API field for posts
     */
    public function registerRestField(): void
    {
        register_rest_field('post', 'live_feed_thread', [
            'get_callback' => function ($post) {
                return get_post_meta($post['id'], self::META_KEY, true) === '1';
            },
            'schema' => [
                'description' => 'Whether to show live feed updates for this post\'s date',
                'type' => 'boolean',
                'context' => ['view', 'edit'],
            ],
        ]);
    }
}
