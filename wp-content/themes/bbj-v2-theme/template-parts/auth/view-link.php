<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="link">

    <div data-bbj-link-profile class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
        <div data-bbj-link-avatar class="w-16 h-16 rounded-full mx-auto mb-2 bg-gray-200 dark:bg-gray-600"></div>
        <p class="text-sm text-gray-600 dark:text-gray-300"><?php esc_html_e('Signing in as', 'bbj-v2-theme'); ?></p>
        <p class="font-medium text-gray-800 dark:text-white" data-bbj-link-name></p>
        <p class="text-sm text-gray-500 dark:text-gray-400" data-bbj-link-email></p>
    </div>

    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
        <?php esc_html_e('No BBJ account found with this email.', 'bbj-v2-theme'); ?>
    </p>

    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
        <h3 class="font-medium text-gray-800 dark:text-white mb-3">
            <?php esc_html_e('Have an existing BBJ account?', 'bbj-v2-theme'); ?>
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            <?php esc_html_e('Enter your BBJ credentials to link your Google account:', 'bbj-v2-theme'); ?>
        </p>

        <form data-bbj-auth-form="link" class="space-y-3" novalidate>
            <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

            <input type="text" name="username" placeholder="<?php esc_attr_e('Username or Email', 'bbj-v2-theme'); ?>"
                   autocomplete="username" required class="input text-sm">
            <input type="password" name="password" placeholder="<?php esc_attr_e('Password', 'bbj-v2-theme'); ?>"
                   autocomplete="current-password" required class="input text-sm">

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember_me" checked
                       class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    <?php esc_html_e('Keep me logged in', 'bbj-v2-theme'); ?>
                </span>
            </label>

            <button type="submit" class="btn-primary w-full" data-bbj-submit>
                <?php esc_html_e('Link Account', 'bbj-v2-theme'); ?>
            </button>
        </form>
    </div>

    <div class="bbj-divider"><span><?php esc_html_e('or', 'bbj-v2-theme'); ?></span></div>

    <button type="button" data-bbj-auth-create-from-google
            class="w-full py-2.5 border-2 border-primary-500 text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg font-medium transition-colors">
        <?php esc_html_e('Continue as New User', 'bbj-v2-theme'); ?>
    </button>

    <p class="text-xs text-center text-gray-400 dark:text-gray-500">
        <?php esc_html_e('You can also link accounts later in your profile settings.', 'bbj-v2-theme'); ?>
    </p>
</section>
