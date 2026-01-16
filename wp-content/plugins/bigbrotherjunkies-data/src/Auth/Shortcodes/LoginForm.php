<?php

namespace BigBrotherJunkies\Data\Auth\Shortcodes;

use BigBrotherJunkies\Data\Auth\LoginHandler;

/**
 * Login form shortcode [bbjd_login_form]
 */
class LoginForm
{
    private LoginHandler $handler;

    public function __construct(LoginHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Render the login form
     */
    public function render(array $atts = []): string
    {
        // Don't show form if already logged in
        if (is_user_logged_in()) {
            return $this->renderLoggedInMessage();
        }

        $atts = shortcode_atts([
            'redirect' => '',
            'show_register_link' => 'true',
            'show_forgot_link' => 'true',
        ], $atts);

        ob_start();
        ?>
        <div class="bbjd-auth-container max-w-[420px] mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-10 border border-gray-200 dark:border-gray-700">

                <!-- Header -->
                <div class="text-center mb-8">
                    <h1 class="font-osw text-3xl font-semibold text-primary500 dark:text-second500 tracking-wide">
                        <?php esc_html_e('Welcome Back', 'bigbrotherjunkies-data'); ?>
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 text-[15px]">
                        <?php esc_html_e('Sign in to access live feed updates & spoilers', 'bigbrotherjunkies-data'); ?>
                    </p>
                </div>

                <!-- Google Sign-In Button (Top) -->
                <div class="mb-7">
                    <button
                        type="button"
                        id="bbjd-google-signin"
                        class="w-full flex items-center justify-center gap-3 py-3.5 px-5 bg-white border-2 border-gray-200 hover:border-gray-300 hover:bg-gray-50 rounded-xl text-[15px] font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <?php esc_html_e('Continue with Google', 'bigbrotherjunkies-data'); ?>
                    </button>
                </div>

                <!-- Divider -->
                <div class="flex items-center gap-4 mb-7">
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-600"></div>
                    <span class="text-gray-400 dark:text-gray-500 text-xs uppercase tracking-wider font-medium">
                        <?php esc_html_e('or', 'bigbrotherjunkies-data'); ?>
                    </span>
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-600"></div>
                </div>

                <!-- Login Form -->
                <form id="bbjd-login-form" method="post">
                    <?php wp_nonce_field('bbjd_login_nonce', 'bbjd_login_nonce_field'); ?>

                    <?php if (!empty($atts['redirect'])): ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url($atts['redirect']); ?>">
                    <?php endif; ?>

                    <!-- Username/Email Field -->
                    <div class="mb-5">
                        <label for="bbjd-login" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                            <?php esc_html_e('Username or Email', 'bigbrotherjunkies-data'); ?>
                        </label>
                        <input
                            type="text"
                            id="bbjd-login"
                            name="login"
                            required
                            autocomplete="username"
                            class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                            placeholder="<?php esc_attr_e('Enter your username or email', 'bigbrotherjunkies-data'); ?>"
                        >
                    </div>

                    <!-- Password Field -->
                    <div class="mb-5">
                        <label for="bbjd-password" class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">
                            <?php esc_html_e('Password', 'bigbrotherjunkies-data'); ?>
                        </label>
                        <div class="relative">
                            <input
                                type="password"
                                id="bbjd-password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="w-full px-4 py-3.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-[15px] text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-primary500 focus:ring-2 focus:ring-primary500/20 transition-all"
                                placeholder="<?php esc_attr_e('Enter your password', 'bigbrotherjunkies-data'); ?>"
                            >
                            <button
                                type="button"
                                class="bbjd-toggle-password absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                aria-label="<?php esc_attr_e('Toggle password visibility', 'bigbrotherjunkies-data'); ?>"
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

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center gap-2 cursor-pointer text-gray-600 dark:text-gray-400 text-sm">
                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                                class="w-[18px] h-[18px] rounded border-gray-300 text-primary500 focus:ring-primary500 cursor-pointer"
                            >
                            <?php esc_html_e('Remember me', 'bigbrotherjunkies-data'); ?>
                        </label>

                        <?php if ($atts['show_forgot_link'] === 'true'): ?>
                            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="text-sm font-medium text-primary500 hover:text-primaryHard transition-colors">
                                <?php esc_html_e('Forgot password?', 'bigbrotherjunkies-data'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Error Message Container -->
                    <div id="bbjd-login-error" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 rounded-xl text-sm"></div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        class="w-full py-4 px-5 bg-gradient-to-r from-primary500 to-primaryHard hover:from-primaryHard hover:to-primary500 text-white font-osw text-base font-semibold uppercase tracking-wider rounded-xl transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary500/30 flex items-center justify-center"
                    >
                        <span class="bbjd-btn-text"><?php esc_html_e('Sign In', 'bigbrotherjunkies-data'); ?></span>
                        <svg class="bbjd-btn-spinner hidden w-5 h-5 ml-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>

                <!-- Register Link -->
                <?php if ($atts['show_register_link'] === 'true'): ?>
                    <div class="mt-7 pt-7 border-t border-gray-200 dark:border-gray-700 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <?php esc_html_e("Don't have an account?", 'bigbrotherjunkies-data'); ?>
                            <a href="<?php echo esc_url(home_url('/registration/')); ?>" class="font-semibold text-primary500 hover:text-primaryHard transition-colors">
                                <?php esc_html_e('Create one now', 'bigbrotherjunkies-data'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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
        <div class="bbjd-auth-container max-w-md mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center">
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    <?php printf(
                        esc_html__('You are already logged in as %s.', 'bigbrotherjunkies-data'),
                        '<strong>' . esc_html($user->display_name) . '</strong>'
                    ); ?>
                </p>
                <div class="space-x-4">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-block px-4 py-2 bg-primary500 hover:bg-primarySoft text-second500 rounded-lg transition">
                        <?php esc_html_e('Go to Homepage', 'bigbrotherjunkies-data'); ?>
                    </a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="inline-block px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                        <?php esc_html_e('Log Out', 'bigbrotherjunkies-data'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
