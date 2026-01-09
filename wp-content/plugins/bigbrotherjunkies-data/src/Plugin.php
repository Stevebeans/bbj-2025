<?php

namespace BigBrotherJunkies\Data;

use BigBrotherJunkies\Data\Admin\AdminLoader;

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
        // Load admin functionality
        if (is_admin()) {
            $adminLoader = new AdminLoader();
            $adminLoader->init();
        }

        // Future: Add frontend, REST API, etc.
    }
}
