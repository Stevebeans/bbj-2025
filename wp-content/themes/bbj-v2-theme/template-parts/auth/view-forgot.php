<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="forgot">

    <p class="text-sm text-gray-600 dark:text-gray-300">
        <?php esc_html_e("Enter the email associated with your BBJ account and we'll send a reset link.", 'bbj-v2-theme'); ?>
    </p>

    <form data-bbj-auth-form="forgot" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>
        <div data-bbj-form-success class="hidden p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300 text-sm">
            <?php esc_html_e('Check your email for a reset link. The link expires in 24 hours.', 'bbj-v2-theme'); ?>
        </div>

        <div>
            <label for="bbj-forgot-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Email', 'bbj-v2-theme'); ?>
            </label>
            <input type="email" id="bbj-forgot-email" name="email" required autocomplete="email" class="input">
        </div>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Send Reset Link', 'bbj-v2-theme'); ?>
        </button>
    </form>

    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        <button type="button" data-bbj-auth-switch="login" class="text-primary-500 hover:underline font-medium">
            <?php esc_html_e('Back to Log In', 'bbj-v2-theme'); ?>
        </button>
    </p>
</section>
