<?php

namespace BigBrotherJunkies\Data\Auth\Shortcodes;

use BigBrotherJunkies\Data\Auth\RegistrationHandler;

/**
 * Registration form shortcode [bbjd_registration_form]
 */
class RegistrationForm
{
    private RegistrationHandler $handler;

    public function __construct(RegistrationHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Render the registration form
     */
    public function render(array $atts = []): string
    {
        // Don't show form if already logged in
        if (is_user_logged_in()) {
            return $this->renderLoggedInMessage();
        }

        // Check if registration is allowed
        if (!get_option('users_can_register')) {
            return $this->renderRegistrationDisabled();
        }

        $atts = shortcode_atts([
            'show_login_link' => 'true',
            'newsletter_list' => 'Big Brother Daily Digest',
        ], $atts);

        ob_start();
        ?>
        <div class="bbjd-auth-container max-w-[520px] mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-10 border border-gray-200 dark:border-gray-700">

                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="font-osw text-3xl font-semibold text-primary500 dark:text-second500 tracking-wide">
                        <?php esc_html_e('Create Account', 'bigbrotherjunkies-data'); ?>
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 text-[15px]">
                        <?php esc_html_e('Join the Big Brother Junkies community', 'bigbrotherjunkies-data'); ?>
                    </p>
                </div>

                <!-- Google Sign-Up Button (Top) -->
                <div class="mb-7">
                    <button
                        type="button"
                        id="bbjd-google-signup"
                        class="w-full flex items-center justify-center gap-3 py-3.5 px-5 bg-white border-2 border-gray-200 hover:border-gray-300 hover:bg-gray-50 rounded-xl text-[15px] font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <?php esc_html_e('Sign up with Google', 'bigbrotherjunkies-data'); ?>
                    </button>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-4 mb-7">
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-600"></div>
                    <span class="text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider font-medium">
                        <?php esc_html_e('or register with email', 'bigbrotherjunkies-data'); ?>
                    </span>
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-600"></div>
                </div>

                <!-- Registration Form -->
                <form id="bbjd-register-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('bbjd_register_nonce', 'bbjd_register_nonce_field'); ?>

                    <!-- Username -->
                    <div class="mb-5">
                        <label for="bbjd-username" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                            <?php esc_html_e('Username', 'bigbrotherjunkies-data'); ?> <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="bbjd-username"
                            name="username"
                            required
                            autocomplete="username"
                            pattern="[a-z0-9]+"
                            class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                            placeholder="<?php esc_attr_e('Choose a username', 'bigbrotherjunkies-data'); ?>"
                        >
                        <p class="mt-1.5 text-xs text-gray-400">
                            <?php esc_html_e('Lowercase letters (a-z) and numbers only', 'bigbrotherjunkies-data'); ?>
                        </p>
                    </div>

                    <!-- Email Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label for="bbjd-email" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                <?php esc_html_e('Email', 'bigbrotherjunkies-data'); ?> <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                id="bbjd-email"
                                name="email"
                                required
                                autocomplete="email"
                                class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                placeholder="<?php esc_attr_e('your@email.com', 'bigbrotherjunkies-data'); ?>"
                            >
                        </div>
                        <div>
                            <label for="bbjd-email-confirm" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                <?php esc_html_e('Confirm Email', 'bigbrotherjunkies-data'); ?> <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                id="bbjd-email-confirm"
                                name="email_confirm"
                                required
                                class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                placeholder="<?php esc_attr_e('Confirm email', 'bigbrotherjunkies-data'); ?>"
                            >
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label for="bbjd-reg-password" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                <?php esc_html_e('Password', 'bigbrotherjunkies-data'); ?> <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    id="bbjd-reg-password"
                                    name="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                    placeholder="<?php esc_attr_e('Min. 8 characters', 'bigbrotherjunkies-data'); ?>"
                                >
                                <button
                                    type="button"
                                    class="bbjd-toggle-password absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                >
                                    <svg class="w-5 h-5 eye-open" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg class="w-5 h-5 eye-closed hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label for="bbjd-password-confirm" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                <?php esc_html_e('Confirm Password', 'bigbrotherjunkies-data'); ?> <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="password"
                                id="bbjd-password-confirm"
                                name="password_confirm"
                                required
                                autocomplete="new-password"
                                class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                placeholder="<?php esc_attr_e('Confirm password', 'bigbrotherjunkies-data'); ?>"
                            >
                        </div>
                    </div>

                    <!-- Optional Fields - Collapsible Section -->
                    <div class="mb-6">
                        <button
                            type="button"
                            id="bbjd-toggle-optional"
                            class="flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-primary500 transition-colors"
                        >
                            <svg class="w-4 h-4 transition-transform" id="bbjd-optional-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                            <?php esc_html_e('Optional: Display name & profile picture', 'bigbrotherjunkies-data'); ?>
                        </button>

                        <div id="bbjd-optional-fields" class="hidden mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 space-y-4">
                            <!-- Display Name -->
                            <div>
                                <label for="bbjd-display-name" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                    <?php esc_html_e('Display Name', 'bigbrotherjunkies-data'); ?>
                                </label>
                                <input
                                    type="text"
                                    id="bbjd-display-name"
                                    name="display_name"
                                    class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                    placeholder="<?php esc_attr_e('How your name appears on the site', 'bigbrotherjunkies-data'); ?>"
                                >
                            </div>

                            <!-- First/Last Name -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="bbjd-first-name" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                        <?php esc_html_e('First Name', 'bigbrotherjunkies-data'); ?>
                                    </label>
                                    <input
                                        type="text"
                                        id="bbjd-first-name"
                                        name="first_name"
                                        autocomplete="given-name"
                                        class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                    >
                                </div>
                                <div>
                                    <label for="bbjd-last-name" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                        <?php esc_html_e('Last Name', 'bigbrotherjunkies-data'); ?>
                                    </label>
                                    <input
                                        type="text"
                                        id="bbjd-last-name"
                                        name="last_name"
                                        autocomplete="family-name"
                                        class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                    >
                                </div>
                            </div>

                            <!-- Profile Picture -->
                            <div>
                                <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                                    <?php esc_html_e('Profile Picture', 'bigbrotherjunkies-data'); ?>
                                </label>
                                <div
                                    id="bbjd-profile-dropzone"
                                    class="border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-xl p-6 text-center cursor-pointer hover:border-primary500 dark:hover:border-second500 transition-all"
                                >
                                    <input type="hidden" name="profile_picture_id" id="bbjd-profile-picture-id" value="">
                                    <div id="bbjd-dropzone-preview" class="hidden mb-3">
                                        <img src="" alt="Preview" class="mx-auto w-20 h-20 rounded-full object-cover border-2 border-primary500">
                                        <button type="button" id="bbjd-remove-picture" class="mt-2 text-xs text-red-500 hover:text-red-700 font-medium">
                                            <?php esc_html_e('Remove', 'bigbrotherjunkies-data'); ?>
                                        </button>
                                    </div>
                                    <div id="bbjd-dropzone-placeholder">
                                        <svg class="mx-auto w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">
                                            <?php esc_html_e('Click or drag to upload', 'bigbrotherjunkies-data'); ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <?php esc_html_e('JPG, PNG, GIF (max 5MB)', 'bigbrotherjunkies-data'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Newsletter Subscription -->
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                id="bbjd-subscribe"
                                name="subscribe_newsletter"
                                value="1"
                                checked
                                class="w-[18px] h-[18px] mt-0.5 rounded border-gray-300 text-primary500 focus:ring-primary500 cursor-pointer"
                            >
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <?php esc_html_e('Subscribe to newsletter', 'bigbrotherjunkies-data'); ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?php printf(
                                        esc_html__('Get %s updates delivered to your inbox', 'bigbrotherjunkies-data'),
                                        '<strong>' . esc_html($atts['newsletter_list']) . '</strong>'
                                    ); ?>
                                </p>
                            </div>
                        </label>
                    </div>

                    <!-- Honeypot (spam prevention) -->
                    <div class="bbjd-hp" style="position: absolute; left: -9999px;" aria-hidden="true">
                        <input type="text" name="bbjd_hp_field" tabindex="-1" autocomplete="off">
                    </div>

                    <!-- Error Message Container -->
                    <div id="bbjd-register-error" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 rounded-xl text-sm"></div>

                    <!-- Success Message Container -->
                    <div id="bbjd-register-success" class="hidden mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-600 dark:text-green-400 rounded-xl text-sm"></div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="w-full py-4 px-5 bg-gradient-to-r from-primary500 to-primaryHard hover:from-primaryHard hover:to-primary500 text-white font-osw text-base font-semibold uppercase tracking-wider rounded-xl transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary500/30 flex items-center justify-center"
                    >
                        <span class="bbjd-btn-text"><?php esc_html_e('Create Account', 'bigbrotherjunkies-data'); ?></span>
                        <svg class="bbjd-btn-spinner hidden w-5 h-5 ml-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>

                <!-- Login Link -->
                <?php if ($atts['show_login_link'] === 'true'): ?>
                    <div class="mt-7 pt-7 border-t border-gray-200 dark:border-gray-700 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <?php esc_html_e('Already have an account?', 'bigbrotherjunkies-data'); ?>
                            <a href="<?php echo esc_url(wp_login_url()); ?>" class="font-semibold text-primary500 hover:text-primaryHard transition-colors">
                                <?php esc_html_e('Sign in', 'bigbrotherjunkies-data'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('bbjd-toggle-optional');
            const optionalFields = document.getElementById('bbjd-optional-fields');
            const arrow = document.getElementById('bbjd-optional-arrow');

            if (toggleBtn && optionalFields) {
                toggleBtn.addEventListener('click', function() {
                    optionalFields.classList.toggle('hidden');
                    arrow.classList.toggle('rotate-180');
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render message for already logged-in users
     */
    private function renderLoggedInMessage(): string
    {
        $user = wp_get_current_user();

        ob_start();
        ?>
        <div class="bbjd-auth-container max-w-[420px] mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-10 border border-gray-200 dark:border-gray-700 text-center">
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    <?php printf(
                        esc_html__('You are already logged in as %s.', 'bigbrotherjunkies-data'),
                        '<strong>' . esc_html($user->display_name) . '</strong>'
                    ); ?>
                </p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-block py-3 px-6 bg-gradient-to-r from-primary500 to-primaryHard text-white font-osw font-semibold uppercase tracking-wider rounded-xl transition-all hover:-translate-y-0.5 hover:shadow-lg">
                    <?php esc_html_e('Go to Homepage', 'bigbrotherjunkies-data'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render message when registration is disabled
     */
    private function renderRegistrationDisabled(): string
    {
        ob_start();
        ?>
        <div class="bbjd-auth-container max-w-[420px] mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-10 border border-gray-200 dark:border-gray-700 text-center">
                <p class="text-gray-700 dark:text-gray-300">
                    <?php esc_html_e('User registration is currently disabled.', 'bigbrotherjunkies-data'); ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
