<?php

namespace BigBrotherJunkies\Data\Hooks;

use BigBrotherJunkies\Data\Ads\AdManager;

/**
 * Outputs header and footer code from settings
 * Used for ad network scripts (Freestar, Google AdSense, etc.)
 */
class HeaderFooterCode
{
    /**
     * Initialize the hooks
     */
    public function init(): void
    {
        // Only on frontend
        if (is_admin()) {
            return;
        }

        add_action('wp_head', [$this, 'outputHeaderCode'], 1);
        add_action('wp_footer', [$this, 'outputFooterCode'], 99);
    }

    /**
     * Output header code in <head>
     */
    public function outputHeaderCode(): void
    {
        $settings = AdManager::getInstance()->getSettings();
        $headerCode = $settings['header_code'] ?? '';

        if (!empty($headerCode)) {
            // Output with comment markers for debugging
            echo "\n<!-- BBJD Header Code Start -->\n";
            echo $headerCode;
            echo "\n<!-- BBJD Header Code End -->\n";
        }
    }

    /**
     * Output footer code before </body>
     */
    public function outputFooterCode(): void
    {
        $settings = AdManager::getInstance()->getSettings();
        $footerCode = $settings['footer_code'] ?? '';

        if (!empty($footerCode)) {
            // Output with comment markers for debugging
            echo "\n<!-- BBJD Footer Code Start -->\n";
            echo $footerCode;
            echo "\n<!-- BBJD Footer Code End -->\n";
        }
    }
}
