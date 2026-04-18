<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="register">

    <div data-bbj-auth-webview-notice class="hidden bbj-form-error">
        <?php esc_html_e(
            "Google sign-up doesn't work inside the Facebook or Instagram browser. Tap the ••• menu and choose \"Open in Chrome\" or \"Open in Safari.\"",
            'bbj-v2-theme'
        ); ?>
    </div>

    <div data-bbj-google-register-container class="flex justify-center"></div>

    <div class="bbj-divider"><span><?php esc_html_e('or', 'bbj-v2-theme'); ?></span></div>

    <form data-bbj-auth-form="register" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

        <div>
            <label for="bbj-reg-username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Username', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="text" id="bbj-reg-username" name="username" required autocomplete="username"
                   class="input" placeholder="<?php esc_attr_e('lowercase letters and numbers only', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="username"></p>
        </div>

        <div>
            <label for="bbj-reg-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Email', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="email" id="bbj-reg-email" name="email" required autocomplete="email"
                   class="input" placeholder="you@example.com">
            <p class="bbj-field-error hidden" data-bbj-field-error="email"></p>
        </div>

        <div>
            <label for="bbj-reg-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Password', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="password" id="bbj-reg-password" name="password" required autocomplete="new-password"
                   class="input" placeholder="<?php esc_attr_e('At least 8 characters', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="password"></p>
        </div>

        <div>
            <label for="bbj-reg-password2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Confirm Password', 'bbj-v2-theme'); ?> <span class="text-red-500">*</span>
            </label>
            <input type="password" id="bbj-reg-password2" name="confirm_password" required autocomplete="new-password"
                   class="input" placeholder="<?php esc_attr_e('Re-enter your password', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="confirm_password"></p>
        </div>

        <div>
            <label for="bbj-reg-display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Display Name', 'bbj-v2-theme'); ?>
                <span class="text-gray-400">(<?php esc_html_e('optional', 'bbj-v2-theme'); ?>)</span>
            </label>
            <input type="text" id="bbj-reg-display" name="display_name" autocomplete="name"
                   class="input" placeholder="<?php esc_attr_e('How you want to be known', 'bbj-v2-theme'); ?>">
        </div>

        <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" name="subscribe_newsletter" checked
                   class="mt-1 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-500 focus:ring-primary-500">
            <span class="text-sm text-gray-600 dark:text-gray-400">
                <?php esc_html_e('Email me when new posts are published', 'bbj-v2-theme'); ?>
            </span>
        </label>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Create Account', 'bbj-v2-theme'); ?>
        </button>
    </form>

    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        <?php esc_html_e('Already have an account?', 'bbj-v2-theme'); ?>
        <button type="button" data-bbj-auth-switch="login" class="text-primary-500 hover:underline font-medium">
            <?php esc_html_e('Log in', 'bbj-v2-theme'); ?>
        </button>
    </p>
</section>
