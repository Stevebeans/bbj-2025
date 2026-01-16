<?php

namespace BigBrotherJunkies\Data\Auth;

/**
 * Google OAuth handler using Google Identity Services (GIS)
 *
 * Flow:
 * 1. Frontend loads GIS library with our Client ID
 * 2. User clicks "Continue with Google"
 * 3. Google returns a JWT credential to frontend
 * 4. Frontend sends credential to our AJAX endpoint
 * 5. We verify the JWT and create/link user account
 */
class GoogleOAuth
{
    /**
     * Option name for storing Google Client ID
     */
    public const OPTION_CLIENT_ID = 'bbjd_google_client_id';

    /**
     * Option name for storing Google Client Secret (not needed for GIS but kept for future)
     */
    public const OPTION_CLIENT_SECRET = 'bbjd_google_client_secret';

    /**
     * Google's token verification endpoint
     */
    private const TOKEN_INFO_URL = 'https://oauth2.googleapis.com/tokeninfo';

    /**
     * Get Google Client ID
     */
    public function getClientId(): string
    {
        return get_option(self::OPTION_CLIENT_ID, '');
    }

    /**
     * Check if Google OAuth is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->getClientId());
    }

    /**
     * Handle AJAX Google Sign-In request
     */
    public function handleGoogleSignIn(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bbjd_google_nonce')) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh and try again.', 'bigbrotherjunkies-data')]);
        }

        $credential = sanitize_text_field($_POST['credential'] ?? '');

        if (empty($credential)) {
            wp_send_json_error(['message' => __('No credential received from Google.', 'bigbrotherjunkies-data')]);
        }

        // Verify the Google ID token
        $googleUser = $this->verifyIdToken($credential);

        if (is_wp_error($googleUser)) {
            wp_send_json_error(['message' => $googleUser->get_error_message()]);
        }

        // Find or create WordPress user
        $result = $this->findOrCreateUser($googleUser);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Log the user in
        $user = $result['user'];
        $isNewUser = $result['is_new'];

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true, is_ssl());

        wp_send_json_success([
            'message' => $isNewUser
                ? __('Account created successfully! Welcome!', 'bigbrotherjunkies-data')
                : __('Welcome back!', 'bigbrotherjunkies-data'),
            'redirect' => $this->getRedirectUrl($user),
            'is_new_user' => $isNewUser,
        ]);
    }

    /**
     * Verify Google ID token
     *
     * @param string $idToken The JWT from Google
     * @return array|\WP_Error User info array or error
     */
    private function verifyIdToken(string $idToken): array|\WP_Error
    {
        // Call Google's tokeninfo endpoint to verify the token
        $response = wp_remote_get(self::TOKEN_INFO_URL . '?id_token=' . urlencode($idToken), [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('google_error', __('Could not verify Google credentials. Please try again.', 'bigbrotherjunkies-data'));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($statusCode !== 200 || empty($data)) {
            return new \WP_Error('invalid_token', __('Invalid Google credentials. Please try again.', 'bigbrotherjunkies-data'));
        }

        // Verify the token was issued for our app
        $clientId = $this->getClientId();
        if (($data['aud'] ?? '') !== $clientId) {
            return new \WP_Error('invalid_audience', __('Token was not issued for this application.', 'bigbrotherjunkies-data'));
        }

        // Verify the token hasn't expired
        if (isset($data['exp']) && $data['exp'] < time()) {
            return new \WP_Error('token_expired', __('Google credentials have expired. Please try again.', 'bigbrotherjunkies-data'));
        }

        // Verify email is present and verified
        if (empty($data['email'])) {
            return new \WP_Error('no_email', __('Could not retrieve email from Google account.', 'bigbrotherjunkies-data'));
        }

        if (($data['email_verified'] ?? 'false') !== 'true') {
            return new \WP_Error('email_not_verified', __('Please verify your Google email address first.', 'bigbrotherjunkies-data'));
        }

        return [
            'email' => sanitize_email($data['email']),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'given_name' => sanitize_text_field($data['given_name'] ?? ''),
            'family_name' => sanitize_text_field($data['family_name'] ?? ''),
            'picture' => esc_url_raw($data['picture'] ?? ''),
            'google_id' => sanitize_text_field($data['sub'] ?? ''),
        ];
    }

    /**
     * Find existing user or create new one
     *
     * @param array $googleUser Verified Google user data
     * @return array|\WP_Error Array with 'user' and 'is_new' keys, or error
     */
    private function findOrCreateUser(array $googleUser): array|\WP_Error
    {
        // First, check if user exists by email
        $existingUser = get_user_by('email', $googleUser['email']);

        if ($existingUser) {
            // Link Google ID to existing account (if not already linked)
            $this->linkGoogleId($existingUser->ID, $googleUser['google_id']);

            return [
                'user' => $existingUser,
                'is_new' => false,
            ];
        }

        // Check if user exists by Google ID (in case email changed)
        $userByGoogleId = $this->findUserByGoogleId($googleUser['google_id']);

        if ($userByGoogleId) {
            return [
                'user' => $userByGoogleId,
                'is_new' => false,
            ];
        }

        // Create new user
        $newUser = $this->createUser($googleUser);

        if (is_wp_error($newUser)) {
            return $newUser;
        }

        return [
            'user' => $newUser,
            'is_new' => true,
        ];
    }

    /**
     * Create a new WordPress user from Google data
     */
    private function createUser(array $googleUser): \WP_User|\WP_Error
    {
        // Generate username from email
        $username = $this->generateUsername($googleUser['email']);

        // Generate random password (user will use Google to login)
        $password = wp_generate_password(24, true, true);

        // Prepare user data
        $userData = [
            'user_login' => $username,
            'user_email' => $googleUser['email'],
            'user_pass' => $password,
            'display_name' => $googleUser['name'] ?: $username,
            'first_name' => $googleUser['given_name'],
            'last_name' => $googleUser['family_name'],
            'role' => 'subscriber',
        ];

        // Create the user
        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            return $userId;
        }

        // Store Google ID
        $this->linkGoogleId($userId, $googleUser['google_id']);

        // Store Google profile picture URL
        if (!empty($googleUser['picture'])) {
            update_user_meta($userId, 'bbjd_google_picture', $googleUser['picture']);
        }

        // Mark as registered via Google
        update_user_meta($userId, 'bbjd_registered_via', 'google');

        return get_user_by('ID', $userId);
    }

    /**
     * Generate a unique username from email
     */
    private function generateUsername(string $email): string
    {
        // Get the part before @
        $baseUsername = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]));

        // Ensure it's at least 3 characters
        if (strlen($baseUsername) < 3) {
            $baseUsername = 'user' . $baseUsername;
        }

        // Make sure it's unique
        $username = $baseUsername;
        $counter = 1;

        while (username_exists($username)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Link Google ID to user account
     */
    private function linkGoogleId(int $userId, string $googleId): void
    {
        update_user_meta($userId, 'bbjd_google_id', $googleId);
    }

    /**
     * Find user by Google ID
     */
    private function findUserByGoogleId(string $googleId): ?\WP_User
    {
        $users = get_users([
            'meta_key' => 'bbjd_google_id',
            'meta_value' => $googleId,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get redirect URL after login
     */
    private function getRedirectUrl(\WP_User $user): string
    {
        // Check for redirect parameter
        $redirect = $_POST['redirect_to'] ?? '';

        if (!empty($redirect) && wp_validate_redirect($redirect)) {
            return $redirect;
        }

        // Admins go to dashboard
        if (user_can($user, 'manage_options')) {
            return admin_url();
        }

        // Regular users go to homepage
        return home_url('/');
    }

    /**
     * Register admin settings for Google OAuth
     */
    public function registerSettings(): void
    {
        register_setting('bbjd_auth_settings', self::OPTION_CLIENT_ID, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('bbjd_auth_settings', self::OPTION_CLIENT_SECRET, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }
}
