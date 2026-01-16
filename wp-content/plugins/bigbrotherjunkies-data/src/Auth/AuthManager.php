<?php

namespace BigBrotherJunkies\Data\Auth;

use BigBrotherJunkies\Data\Auth\Shortcodes\LoginForm;
use BigBrotherJunkies\Data\Auth\Shortcodes\RegistrationForm;

/**
 * Main authentication manager - coordinates login/registration functionality
 */
class AuthManager
{
    /**
     * Singleton instance
     */
    private static ?AuthManager $instance = null;

    /**
     * Login handler instance
     */
    private LoginHandler $loginHandler;

    /**
     * Registration handler instance
     */
    private RegistrationHandler $registrationHandler;

    /**
     * Google OAuth handler instance
     */
    private GoogleOAuth $googleOAuth;

    /**
     * Get singleton instance
     */
    public static function getInstance(): AuthManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->loginHandler = new LoginHandler();
        $this->registrationHandler = new RegistrationHandler();
        $this->googleOAuth = new GoogleOAuth();
    }

    /**
     * Initialize authentication system
     */
    public function init(): void
    {
        // Register shortcodes
        $this->registerShortcodes();

        // Register AJAX handlers
        $this->registerAjaxHandlers();

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Initialize admin settings
        if (is_admin()) {
            $adminSettings = new AdminSettings();
            $adminSettings->init();
        }
    }

    /**
     * Register shortcodes
     */
    private function registerShortcodes(): void
    {
        $loginForm = new LoginForm($this->loginHandler);
        $registrationForm = new RegistrationForm($this->registrationHandler);

        add_shortcode('bbjd_login_form', [$loginForm, 'render']);
        add_shortcode('bbjd_registration_form', [$registrationForm, 'render']);
    }

    /**
     * Register AJAX handlers for form submissions
     */
    private function registerAjaxHandlers(): void
    {
        // Login AJAX (for non-logged-in users)
        add_action('wp_ajax_nopriv_bbjd_login', [$this->loginHandler, 'handleAjaxLogin']);

        // Registration AJAX (for non-logged-in users)
        add_action('wp_ajax_nopriv_bbjd_register', [$this->registrationHandler, 'handleAjaxRegistration']);

        // Profile picture upload (for non-logged-in users during registration)
        add_action('wp_ajax_nopriv_bbjd_upload_profile_picture', [$this->registrationHandler, 'handleProfilePictureUpload']);

        // Google Sign-In AJAX (for non-logged-in users)
        add_action('wp_ajax_nopriv_bbjd_google_signin', [$this->googleOAuth, 'handleGoogleSignIn']);
    }

    /**
     * Enqueue frontend assets for auth forms
     */
    public function enqueueAssets(): void
    {
        // Only enqueue on pages with our shortcodes
        global $post;

        if (!$post) {
            return;
        }

        $hasLoginForm = has_shortcode($post->post_content, 'bbjd_login_form');
        $hasRegistrationForm = has_shortcode($post->post_content, 'bbjd_registration_form');

        if (!$hasLoginForm && !$hasRegistrationForm) {
            return;
        }

        // Auth form styles
        wp_enqueue_style(
            'bbjd-auth-forms',
            BBJD_URL . 'assets/css/auth-forms.css',
            [],
            BBJD_VERSION
        );

        // Auth form scripts
        wp_enqueue_script(
            'bbjd-auth-forms',
            BBJD_URL . 'assets/js/auth-forms.js',
            ['jquery'],
            BBJD_VERSION,
            true
        );

        // Localize script with AJAX URL and nonces
        wp_localize_script('bbjd-auth-forms', 'bbjdAuth', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'loginNonce' => wp_create_nonce('bbjd_login_nonce'),
            'registerNonce' => wp_create_nonce('bbjd_register_nonce'),
            'googleNonce' => wp_create_nonce('bbjd_google_nonce'),
            'googleClientId' => $this->googleOAuth->getClientId(),
            'googleEnabled' => $this->googleOAuth->isConfigured(),
            'messages' => [
                'loginSuccess' => __('Login successful! Redirecting...', 'bigbrotherjunkies-data'),
                'registerSuccess' => __('Registration successful! Redirecting to login...', 'bigbrotherjunkies-data'),
                'error' => __('An error occurred. Please try again.', 'bigbrotherjunkies-data'),
                'googleError' => __('Google Sign-In failed. Please try again.', 'bigbrotherjunkies-data'),
            ],
        ]);

        // Enqueue Google Identity Services library if configured
        if ($this->googleOAuth->isConfigured()) {
            wp_enqueue_script(
                'google-gsi',
                'https://accounts.google.com/gsi/client',
                [],
                null,
                false // Load in head for GIS
            );
        }

        // Enqueue Dropzone for profile picture upload if on registration page
        if ($hasRegistrationForm) {
            wp_enqueue_script(
                'dropzone',
                'https://unpkg.com/dropzone@5/dist/min/dropzone.min.js',
                [],
                '5.9.3',
                true
            );
            wp_enqueue_style(
                'dropzone',
                'https://unpkg.com/dropzone@5/dist/min/dropzone.min.css',
                [],
                '5.9.3'
            );
        }
    }

    /**
     * Get login handler
     */
    public function getLoginHandler(): LoginHandler
    {
        return $this->loginHandler;
    }

    /**
     * Get registration handler
     */
    public function getRegistrationHandler(): RegistrationHandler
    {
        return $this->registrationHandler;
    }

    /**
     * Get Google OAuth handler
     */
    public function getGoogleOAuth(): GoogleOAuth
    {
        return $this->googleOAuth;
    }
}
