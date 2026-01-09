<?php

namespace BigBrotherJunkies\Data\Admin\Pages;

use BigBrotherJunkies\Data\Ads\AdManager;

/**
 * Ad Manager Settings page
 */
class SettingsPage
{
    public const MENU_SLUG = 'bbjd-settings';

    /**
     * Handle actions
     */
    public function handleActions(): void
    {
        add_action('admin_post_bbjd_save_settings', [$this, 'handleSaveSettings']);
    }

    /**
     * Handle save settings action
     */
    public function handleSaveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('bbjd_save_settings');

        $adManager = AdManager::getInstance();
        $currentSettings = $adManager->getSettings();

        // Global role hiding - these roles see NO ads at all
        $globalHiddenRoles = isset($_POST['global_hidden_roles'])
            ? array_map('sanitize_text_field', (array) $_POST['global_hidden_roles'])
            : [];

        // Auto-insert settings
        $autoInsertPostTypes = isset($_POST['auto_insert_post_types'])
            ? array_map('sanitize_text_field', (array) $_POST['auto_insert_post_types'])
            : ['post'];

        // Header/Footer code - allow scripts for ad networks
        // Using wp_kses with allowed HTML for scripts
        $headerCode = isset($_POST['header_code']) ? wp_unslash($_POST['header_code']) : '';
        $footerCode = isset($_POST['footer_code']) ? wp_unslash($_POST['footer_code']) : '';

        $settings = [
            'global_hidden_roles' => $globalHiddenRoles,
            'auto_insert_post_types' => $autoInsertPostTypes,
            'auto_insert_default_interval' => intval($_POST['auto_insert_default_interval'] ?? 4),
            'auto_insert_max_per_post' => intval($_POST['auto_insert_max_per_post'] ?? 3),
            'cache_ttl' => intval($_POST['cache_ttl'] ?? 300),
            'header_code' => $headerCode,
            'footer_code' => $footerCode,
        ];

        $adManager->updateSettings($settings);

        wp_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'message' => 'saved',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render the page
     */
    public function render(): void
    {
        $adManager = AdManager::getInstance();
        $settings = $adManager->getSettings();
        $message = $_GET['message'] ?? '';

        // Get all WordPress roles
        $wpRoles = wp_roles();
        $allRoles = $wpRoles->get_names();

        // Currently hidden roles
        $globalHiddenRoles = $settings['global_hidden_roles'] ?? [];
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-6">
                    Ad Manager Settings
                </h1>

                <?php $this->renderMessages($message); ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('bbjd_save_settings'); ?>
                    <input type="hidden" name="action" value="bbjd_save_settings">

                    <!-- Global Role Hiding -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            Global Ad-Free Roles
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            Users with these roles will see <strong>NO ads</strong> anywhere on the site.
                            This setting overrides all per-slot settings.
                        </p>

                        <div class="bbjd-grid bbjd-grid-cols-2 md:bbjd-grid-cols-3 lg:bbjd-grid-cols-4 bbjd-gap-3">
                            <?php foreach ($allRoles as $roleSlug => $roleName): ?>
                            <label class="bbjd-flex bbjd-items-center bbjd-space-x-2 bbjd-p-2 bbjd-bg-gray-50 bbjd-rounded hover:bbjd-bg-gray-100">
                                <input type="checkbox"
                                       name="global_hidden_roles[]"
                                       value="<?php echo esc_attr($roleSlug); ?>"
                                       <?php checked(in_array($roleSlug, $globalHiddenRoles)); ?>
                                       class="bbjd-rounded bbjd-border-gray-300 bbjd-text-primary500 focus:bbjd-ring-primary500">
                                <span class="bbjd-text-sm bbjd-text-gray-700"><?php echo esc_html($roleName); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-3">
                            Tip: Use this for ad-free membership tiers. Users with checked roles get a completely ad-free experience.
                        </p>
                    </div>

                    <!-- Header/Footer Code -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-2">
                            Header &amp; Footer Code
                        </h2>
                        <p class="bbjd-text-gray-600 bbjd-text-sm bbjd-mb-4">
                            Add scripts from ad networks (Freestar, Google AdSense, etc.) that need to be loaded in the header or footer.
                            These will be injected on all frontend pages.
                        </p>

                        <div class="bbjd-space-y-4">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Header Code <span class="bbjd-text-gray-400 bbjd-font-normal">(outputs in &lt;head&gt;)</span>
                                </label>
                                <textarea name="header_code" rows="6"
                                          class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-font-mono bbjd-text-sm"
                                          placeholder="<!-- Paste your header scripts here -->"><?php echo esc_textarea($settings['header_code'] ?? ''); ?></textarea>
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Common uses: Google AdSense async script, Freestar initialization, analytics scripts.
                                </p>
                            </div>

                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Footer Code <span class="bbjd-text-gray-400 bbjd-font-normal">(outputs before &lt;/body&gt;)</span>
                                </label>
                                <textarea name="footer_code" rows="6"
                                          class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-font-mono bbjd-text-sm"
                                          placeholder="<!-- Paste your footer scripts here -->"><?php echo esc_textarea($settings['footer_code'] ?? ''); ?></textarea>
                                <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">
                                    Common uses: Scripts that should load after page content, tracking pixels.
                                </p>
                            </div>
                        </div>

                        <div class="bbjd-mt-4 bbjd-p-3 bbjd-bg-yellow-50 bbjd-rounded bbjd-border bbjd-border-yellow-200">
                            <p class="bbjd-text-xs bbjd-text-yellow-800">
                                <strong>Note:</strong> Code here is output for all users. If you want to hide scripts for ad-free roles,
                                the ad network scripts should handle that via their own targeting, or you can use conditional logic in your theme.
                            </p>
                        </div>
                    </div>

                    <!-- Auto-Insert Settings -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                            Auto-Insert Defaults
                        </h2>

                        <div class="bbjd-grid bbjd-grid-cols-1 md:bbjd-grid-cols-2 bbjd-gap-6">
                            <div>
                                <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-2">
                                    Post Types for Auto-Insert
                                </label>
                                <div class="bbjd-space-y-2">
                                    <?php
                                    $postTypes = get_post_types(['public' => true], 'objects');
                                    $currentPostTypes = $settings['auto_insert_post_types'] ?? ['post'];
                                    foreach ($postTypes as $pt):
                                        if ($pt->name === 'attachment') continue;
                                    ?>
                                    <label class="bbjd-flex bbjd-items-center bbjd-space-x-2">
                                        <input type="checkbox"
                                               name="auto_insert_post_types[]"
                                               value="<?php echo esc_attr($pt->name); ?>"
                                               <?php checked(in_array($pt->name, $currentPostTypes)); ?>
                                               class="bbjd-rounded bbjd-border-gray-300 bbjd-text-primary500">
                                        <span class="bbjd-text-sm"><?php echo esc_html($pt->label); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="bbjd-space-y-4">
                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Default Paragraph Interval
                                    </label>
                                    <input type="number"
                                           name="auto_insert_default_interval"
                                           value="<?php echo esc_attr($settings['auto_insert_default_interval'] ?? 4); ?>"
                                           min="1" max="20"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                    <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">Insert ads every X paragraphs</p>
                                </div>

                                <div>
                                    <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                        Max Ads Per Post
                                    </label>
                                    <input type="number"
                                           name="auto_insert_max_per_post"
                                           value="<?php echo esc_attr($settings['auto_insert_max_per_post'] ?? 3); ?>"
                                           min="1" max="10"
                                           class="bbjd-w-full bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cache Settings -->
                    <div class="bbjd-bg-white bbjd-rounded-lg bbjd-shadow bbjd-p-6 bbjd-mb-6">
                        <h2 class="bbjd-text-xl bbjd-font-semibold bbjd-text-gray-800 bbjd-mb-4">
                            Performance
                        </h2>

                        <div>
                            <label class="bbjd-block bbjd-text-sm bbjd-font-medium bbjd-text-gray-700 bbjd-mb-1">
                                Cache TTL (seconds)
                            </label>
                            <input type="number"
                                   name="cache_ttl"
                                   value="<?php echo esc_attr($settings['cache_ttl'] ?? 300); ?>"
                                   min="0" max="86400"
                                   class="bbjd-w-32 bbjd-px-3 bbjd-py-2 bbjd-border bbjd-border-gray-300 bbjd-rounded-md bbjd-text-sm">
                            <p class="bbjd-text-xs bbjd-text-gray-500 bbjd-mt-1">How long to cache ad queries. Set to 0 to disable caching.</p>
                        </div>
                    </div>

                    <div class="bbjd-flex bbjd-justify-end">
                        <button type="submit"
                                class="bbjd-bg-primary500 bbjd-text-white bbjd-px-6 bbjd-py-2 bbjd-rounded bbjd-font-medium hover:bbjd-bg-primaryHard bbjd-transition-colors">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render messages
     */
    private function renderMessages(string $message): void
    {
        if ($message === 'saved') {
            ?>
            <div class="bbjd-bg-green-100 bbjd-border-l-4 bbjd-border-green-500 bbjd-text-green-700 bbjd-p-4 bbjd-mb-6 bbjd-rounded">
                <p>Settings saved successfully.</p>
            </div>
            <?php
        }
    }
}
