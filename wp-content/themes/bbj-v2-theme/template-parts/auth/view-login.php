<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view is-active" data-bbj-auth-view="login">

    <div data-bbj-auth-webview-notice class="hidden bbj-form-error">
        <?php esc_html_e(
            "Google sign-in doesn't work inside the Facebook or Instagram browser. Tap the ••• menu and choose \"Open in Chrome\" or \"Open in Safari.\"",
            'bbj-v2-theme'
        ); ?>
    </div>

    <div data-bbj-google-login-container></div>

    <div class="bbj-divider"><span><?php esc_html_e('or', 'bbj-v2-theme'); ?></span></div>

    <form data-bbj-auth-form="login" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

        <div>
            <label for="bbj-login-username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Username or Email', 'bbj-v2-theme'); ?>
            </label>
            <input type="text" id="bbj-login-username" name="username" required autocomplete="username"
                   class="input">
        </div>

        <div>
            <label for="bbj-login-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Password', 'bbj-v2-theme'); ?>
            </label>
            <input type="password" id="bbj-login-password" name="password" required autocomplete="current-password"
                   class="input">
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember_me" checked
                       class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    <?php esc_html_e('Keep me logged in', 'bbj-v2-theme'); ?>
                </span>
            </label>
            <button type="button" data-bbj-auth-switch="forgot" class="text-sm text-primary-500 hover:underline">
                <?php esc_html_e('Forgot password?', 'bbj-v2-theme'); ?>
            </button>
        </div>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Log In', 'bbj-v2-theme'); ?>
        </button>
    </form>

    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        <?php esc_html_e("Don't have an account?", 'bbj-v2-theme'); ?>
        <button type="button" data-bbj-auth-switch="register" class="text-primary-500 hover:underline font-medium">
            <?php esc_html_e('Sign up', 'bbj-v2-theme'); ?>
        </button>
    </p>
</section>
