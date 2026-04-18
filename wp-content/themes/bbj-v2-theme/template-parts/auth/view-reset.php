<?php
if (!defined('ABSPATH')) { exit; }
?>
<section class="bbj-modal-view" data-bbj-auth-view="reset">

    <p class="text-sm text-gray-600 dark:text-gray-300">
        <?php esc_html_e('Enter a new password for your BBJ account.', 'bbj-v2-theme'); ?>
    </p>

    <form data-bbj-auth-form="reset" class="space-y-4" novalidate>
        <div data-bbj-form-error class="hidden bbj-form-error" role="alert"></div>

        <div>
            <label for="bbj-reset-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('New Password', 'bbj-v2-theme'); ?>
            </label>
            <input type="password" id="bbj-reset-password" name="password" required autocomplete="new-password"
                   class="input" placeholder="<?php esc_attr_e('At least 8 characters', 'bbj-v2-theme'); ?>">
            <p class="bbj-field-error hidden" data-bbj-field-error="password"></p>
        </div>

        <div>
            <label for="bbj-reset-password2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                <?php esc_html_e('Confirm Password', 'bbj-v2-theme'); ?>
            </label>
            <input type="password" id="bbj-reset-password2" name="confirm_password" required autocomplete="new-password"
                   class="input">
            <p class="bbj-field-error hidden" data-bbj-field-error="confirm_password"></p>
        </div>

        <button type="submit" class="btn-primary w-full" data-bbj-submit>
            <?php esc_html_e('Set New Password', 'bbj-v2-theme'); ?>
        </button>
    </form>
</section>
