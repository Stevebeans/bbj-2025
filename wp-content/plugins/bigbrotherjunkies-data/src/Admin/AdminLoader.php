<?php

namespace BigBrotherJunkies\Data\Admin;

use BigBrotherJunkies\Data\Admin\Pages\RegistrationsPage;
use BigBrotherJunkies\Data\Admin\Pages\UserCleanupPage;

/**
 * Handles admin page registration and asset loading
 */
class AdminLoader
{
    /**
     * Admin page hook suffix
     */
    private string $pageHook = '';

    /**
     * Additional page hooks for subpages
     */
    private array $pageHooks = [];

    /**
     * Registrations page instance
     */
    private ?RegistrationsPage $registrationsPage = null;

    private ?UserCleanupPage $userCleanupPage = null;

    /**
     * Initialize admin functionality
     */
    public function init(): void
    {
        $this->registrationsPage = new RegistrationsPage();
        $this->registrationsPage->handleActions();

        $this->userCleanupPage = new UserCleanupPage();
        $this->userCleanupPage->handleActions();

        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Register admin menu pages
     */
    public function registerAdminMenu(): void
    {
        $this->pageHook = add_menu_page(
            __('BBJ Data', 'bigbrotherjunkies-data'),
            __('BBJ Data', 'bigbrotherjunkies-data'),
            'manage_options',
            'bbjd-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-database',
            2
        );

        $this->pageHooks[] = $this->pageHook;

        // Registrations submenu
        $this->pageHooks[] = add_submenu_page(
            'bbjd-dashboard',
            __('Registrations', 'bigbrotherjunkies-data'),
            __('Registrations', 'bigbrotherjunkies-data'),
            'manage_options',
            RegistrationsPage::MENU_SLUG,
            [$this->registrationsPage, 'render']
        );

        // User Cleanup submenu
        $this->pageHooks[] = add_submenu_page(
            'bbjd-dashboard',
            __('User Cleanup', 'bigbrotherjunkies-data'),
            __('User Cleanup', 'bigbrotherjunkies-data'),
            'manage_options',
            UserCleanupPage::MENU_SLUG,
            [$this->userCleanupPage, 'render']
        );
    }

    /**
     * Check if current hook is a plugin admin page
     */
    private function isPluginPage(string $hook): bool
    {
        // Check direct page hooks
        if ($hook === $this->pageHook || in_array($hook, $this->pageHooks, true)) {
            return true;
        }

        // Check for plugin pages by slug
        $pluginSlugs = ['bbjd-ads', 'bbjd-ad-edit', 'bbjd-slots', 'bbjd-settings', 'bbjd-dev-tools', 'bbjd-registrations', 'bbjd-api-settings', 'bbjd-subscriptions', 'bbjd-mailing', 'bbjd-user-cleanup'];
        foreach ($pluginSlugs as $slug) {
            if (strpos($hook, $slug) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get cache-busting version based on file modification time
     */
    private function getAssetVersion(string $relativePath): string
    {
        $filePath = BBJD_PATH . $relativePath;
        if (file_exists($filePath)) {
            return (string) filemtime($filePath);
        }
        return BBJD_VERSION;
    }

    /**
     * Enqueue admin assets only on plugin pages
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on this plugin's admin pages
        if (!$this->isPluginPage($hook)) {
            return;
        }

        // Enqueue CSS with cache busting
        wp_enqueue_style(
            'bbjd-admin',
            BBJD_URL . 'build/css/admin.css',
            [],
            $this->getAssetVersion('build/css/admin.css')
        );

        // Enqueue JS if it exists (ready for future scripts)
        $jsPath = 'build/js/admin.js';
        if (file_exists(BBJD_PATH . $jsPath)) {
            wp_enqueue_script(
                'bbjd-admin',
                BBJD_URL . $jsPath,
                ['jquery'],
                $this->getAssetVersion($jsPath),
                true
            );

            // Localize script data if needed
            wp_localize_script('bbjd-admin', 'bbjdAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bbjd_admin_nonce'),
            ]);
        }
    }

    /**
     * Render the dashboard page
     */
    public function renderDashboard(): void
    {
        ?>
        <div class="bbjd-admin">
            <div class="bbjd-p-6 bbjd-max-w-4xl">
                <h1 class="bbjd-text-3xl bbjd-font-bold bbjd-text-primary500 bbjd-mb-4">
                    Big Brother Junkies Data
                </h1>
                <p class="bbjd-text-gray-600 bbjd-mb-6">
                    Plugin is active and autoloading is working!
                </p>
                <div class="bbjd-bg-second500 bbjd-p-4 bbjd-rounded-lg">
                    <p class="bbjd-text-primaryHard bbjd-font-semibold">
                        Tailwind CSS is loaded with the bbjd- prefix.
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}
