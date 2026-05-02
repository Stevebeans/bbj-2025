<?php
/**
 * Header utility strip — top-most slim row.
 * Left: Contact | Privacy | Follow: [social icons]
 * Right: Current BB Time
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="hidden md:block border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-600 dark:text-gray-400">
    <div class="mx-auto max-w-screen-xl px-4 py-2 flex items-center justify-between">

        <div class="flex items-center gap-3">
            <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="hover:text-primary-500 transition">Contact</a>
            <span aria-hidden="true">|</span>
            <a href="<?php echo esc_url(home_url('/privacy/')); ?>" class="hover:text-primary-500 transition">Privacy</a>
            <span aria-hidden="true">|</span>
            <span class="flex items-center gap-2">
                <span>Follow:</span>
                <a href="https://facebook.com/bigbrotherjunkies" target="_blank" rel="noopener" aria-label="Facebook"
                   class="hover:text-primary-500 transition">
                    <svg class="w-4 h-4 inline-block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07C2 17.1 5.66 21.25 10.44 22V14.9H7.9v-2.83h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.83h-2.34V22C18.34 21.25 22 17.1 22 12.07z"/>
                    </svg>
                </a>
                <a href="https://instagram.com/bigbrotherjunkies" target="_blank" rel="noopener" aria-label="Instagram"
                   class="hover:text-primary-500 transition">
                    <svg class="w-4 h-4 inline-block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23a3.7 3.7 0 0 1-.9 1.38 3.7 3.7 0 0 1-1.38.9c-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 5.84A4 4 0 1 0 12 16a4 4 0 0 0 0-8zm5.2-.95a.93.93 0 1 1 0-1.86.93.93 0 0 1 0 1.86zM12 10a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/>
                    </svg>
                </a>
                <a href="https://twitter.com/BigBrotherBBJ" target="_blank" rel="noopener" aria-label="Twitter / X"
                   class="hover:text-primary-500 transition">
                    <svg class="w-4 h-4 inline-block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path d="M18.9 2H22l-7.5 8.6L23 22h-6.8l-5.3-6.9L4.7 22H1.6l8-9.2L1 2h6.9l4.8 6.3L18.9 2z"/>
                    </svg>
                </a>
                <a href="https://bsky.app/profile/realstevebeans.bsky.social" target="_blank" rel="noopener" aria-label="Bluesky"
                   class="hover:text-primary-500 transition">
                    <svg class="w-4 h-4 inline-block" viewBox="0 0 568 501" fill="currentColor" aria-hidden="true">
                      <path d="M123.121 33.664C188.241 82.553 258.281 181.68 284 234.873c25.719-53.192 95.759-152.32 160.879-201.21C491.866-1.611 568-28.906 568 57.947c0 17.34-9.938 145.649-15.768 166.48-20.265 72.394-94.117 90.859-159.819 79.671 114.875 19.56 144.05 84.332 80.933 149.104-119.846 123.011-172.272-30.859-185.702-70.246-2.462-7.22-3.614-10.598-3.644-7.724-.03-2.874-1.182.505-3.644 7.724-13.43 39.387-65.856 193.258-185.702 70.246-63.117-64.772-33.942-129.544 80.933-149.104-65.702 11.188-139.554-7.277-159.819-79.671C9.938 203.596 0 75.287 0 57.947 0-28.906 76.134-1.611 123.121 33.664Z"/>
                    </svg>
                </a>
            </span>
        </div>

        <div class="flex items-center gap-4">
            <span data-nosnippet>Current BB Time: <?php echo esc_html(bbj_v2_bb_time()); ?></span>
            <button type="button" data-bbj-dark-toggle
                    class="hover:text-primary-500 transition"
                    aria-label="Toggle dark mode">
                <svg class="w-4 h-4 inline-block dark:hidden" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.64 13a1 1 0 0 0-1.05-.14 8.05 8.05 0 0 1-3.37.73 8.15 8.15 0 0 1-8.14-8.1 8.59 8.59 0 0 1 .25-2A1 1 0 0 0 8 2.36a10.14 10.14 0 1 0 14 11.69 1 1 0 0 0-.36-1.05z"/></svg>
                <svg class="w-4 h-4 hidden dark:inline-block" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 18a6 6 0 1 1 0-12 6 6 0 0 1 0 12zm0-14a1 1 0 0 1-1-1V1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1zm0 20a1 1 0 0 1-1-1v-2a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1zm11-11h-2a1 1 0 1 1 0-2h2a1 1 0 1 1 0 2zM3 13H1a1 1 0 1 1 0-2h2a1 1 0 1 1 0 2zm16.24-7.24-1.41 1.41a1 1 0 1 1-1.42-1.42l1.42-1.41a1 1 0 1 1 1.41 1.42zM6.59 18.66 5.17 20.07a1 1 0 1 1-1.41-1.41l1.41-1.42a1 1 0 1 1 1.42 1.42zm12.65 1.41-1.41-1.41a1 1 0 1 1 1.41-1.42l1.42 1.42a1 1 0 1 1-1.42 1.41zM6.59 5.34 5.17 3.93a1 1 0 0 1 1.42-1.42L8 3.93a1 1 0 1 1-1.41 1.41z"/></svg>
            </button>
        </div>
    </div>
</div>
