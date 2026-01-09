<?php

namespace BigBrotherJunkies\Data;

use BigBrotherJunkies\Data\Admin\AdminLoader;
use BigBrotherJunkies\Data\Admin\MetaBoxes\AdSettingsMetaBox;
use BigBrotherJunkies\Data\Admin\Pages\AdsListPage;
use BigBrotherJunkies\Data\Admin\Pages\AdEditPage;
use BigBrotherJunkies\Data\Admin\Pages\SlotsPage;
use BigBrotherJunkies\Data\Admin\Pages\SettingsPage;
use BigBrotherJunkies\Data\Admin\Pages\DevToolsPage;
use BigBrotherJunkies\Data\Ads\AdManager;
use BigBrotherJunkies\Data\Ads\ContentInserter;

/**
 * Main plugin class
 */
class Plugin
{
    /**
     * Singleton instance
     */
    private static ?Plugin $instance = null;

    /**
     * Admin pages
     */
    private array $adminPages = [];

    /**
     * Get singleton instance
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        // Private constructor
    }

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        // Initialize Ad Manager
        $this->initAdManager();

        // Load admin functionality
        if (is_admin()) {
            $this->initAdmin();
        }

        // Load frontend functionality
        $this->initFrontend();

        // Load theme integration functions
        $this->loadThemeFunctions();
    }

    /**
     * Initialize the Ad Manager
     */
    private function initAdManager(): void
    {
        // This ensures the singleton is created
        AdManager::getInstance();
    }

    /**
     * Initialize admin functionality
     */
    private function initAdmin(): void
    {
        // Original BBJ Data page
        $adminLoader = new AdminLoader();
        $adminLoader->init();

        // Ad Manager pages
        $this->adminPages = [
            'ads_list' => new AdsListPage(),
            'ad_edit' => new AdEditPage(),
            'slots' => new SlotsPage(),
            'settings' => new SettingsPage(),
            'dev_tools' => new DevToolsPage(),
        ];

        // Register admin menus
        add_action('admin_menu', [$this, 'registerAdminMenus']);

        // Register page action handlers
        foreach ($this->adminPages as $page) {
            if (method_exists($page, 'handleActions')) {
                $page->handleActions();
            }
        }

        // Meta boxes
        $adMetaBox = new AdSettingsMetaBox();
        $adMetaBox->init();
    }

    /**
     * Register admin menus
     */
    public function registerAdminMenus(): void
    {
        // Main Ad Manager menu
        add_menu_page(
            __('Ad Manager', 'bigbrotherjunkies-data'),
            __('Ad Manager', 'bigbrotherjunkies-data'),
            'manage_options',
            AdsListPage::MENU_SLUG,
            [$this->adminPages['ads_list'], 'render'],
            'dashicons-megaphone',
            31
        );

        // Ads submenu (same as parent)
        add_submenu_page(
            AdsListPage::MENU_SLUG,
            __('All Ads', 'bigbrotherjunkies-data'),
            __('All Ads', 'bigbrotherjunkies-data'),
            'manage_options',
            AdsListPage::MENU_SLUG,
            [$this->adminPages['ads_list'], 'render']
        );

        // Add New Ad
        add_submenu_page(
            AdsListPage::MENU_SLUG,
            __('Add New Ad', 'bigbrotherjunkies-data'),
            __('Add New', 'bigbrotherjunkies-data'),
            'manage_options',
            AdEditPage::MENU_SLUG,
            [$this->adminPages['ad_edit'], 'render']
        );

        // Slots
        add_submenu_page(
            AdsListPage::MENU_SLUG,
            __('Ad Slots', 'bigbrotherjunkies-data'),
            __('Slots', 'bigbrotherjunkies-data'),
            'manage_options',
            SlotsPage::MENU_SLUG,
            [$this->adminPages['slots'], 'render']
        );

        // Settings
        add_submenu_page(
            AdsListPage::MENU_SLUG,
            __('Ad Settings', 'bigbrotherjunkies-data'),
            __('Settings', 'bigbrotherjunkies-data'),
            'manage_options',
            SettingsPage::MENU_SLUG,
            [$this->adminPages['settings'], 'render']
        );

        // Dev Tools
        add_submenu_page(
            AdsListPage::MENU_SLUG,
            __('Dev Tools', 'bigbrotherjunkies-data'),
            __('Dev Tools', 'bigbrotherjunkies-data'),
            'manage_options',
            DevToolsPage::MENU_SLUG,
            [$this->adminPages['dev_tools'], 'render']
        );
    }

    /**
     * Initialize frontend functionality
     */
    private function initFrontend(): void
    {
        // Content inserter for auto-insertion
        $contentInserter = new ContentInserter();
        $contentInserter->init();
    }

    /**
     * Load theme integration functions
     */
    private function loadThemeFunctions(): void
    {
        // The functions are defined in ThemeIntegration.php and auto-loaded
        // They become globally available once the file is included via autoloader
        require_once BBJD_PATH . 'src/Hooks/ThemeIntegration.php';
    }
}
