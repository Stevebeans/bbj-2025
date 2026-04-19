<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="bbj-search-modal" class="bbj-search-modal" data-bbj-search-modal aria-hidden="true" hidden>
    <div class="bbj-search-modal-bar">
        <button type="button"
                class="bbj-search-modal-back"
                data-bbj-search-close
                aria-label="<?php esc_attr_e('Close search', 'bbj-v2-theme'); ?>">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
            </svg>
        </button>
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="bbj-search-modal-form">
            <label class="sr-only" for="bbj-search-modal-input"><?php esc_html_e('Search', 'bbj-v2-theme'); ?></label>
            <div class="relative w-full">
                <input type="search"
                       id="bbj-search-modal-input"
                       name="s"
                       class="input pr-10"
                       placeholder="<?php esc_attr_e('Search posts, players, seasons...', 'bbj-v2-theme'); ?>"
                       autocomplete="off">
                <button type="submit"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-500"
                        aria-label="<?php esc_attr_e('Submit search', 'bbj-v2-theme'); ?>">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </button>
            </div>
        </form>
    </div>
</div>
