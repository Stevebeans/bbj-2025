<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Auth\GoogleOAuth;
use BigBrotherJunkies\Data\Auth\Integrations\MailPoetSubscriber;
use Firebase\JWT\JWT;

/**
 * REST API routes for authentication
 *
 * Provides endpoints for the Next.js frontend to handle:
 * - Google OAuth sign-in
 * - Email/password registration
 * - Password reset
 */
class AuthRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    /**
     * Register the routes
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void
    {
        // Google OAuth sign-in
        register_rest_route(self::NAMESPACE, '/auth/google', [
            'methods' => 'POST',
            'callback' => [$this, 'handleGoogleAuth'],
            'permission_callback' => '__return_true',
            'args' => [
                'credential' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Email/password registration
        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRegister'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Password reset request
        register_rest_route(self::NAMESPACE, '/auth/forgot-password', [
            'methods' => 'POST',
            'callback' => [$this, 'handleForgotPassword'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        // Check if email exists (for registration validation)
        register_rest_route(self::NAMESPACE, '/auth/check-email', [
            'methods' => 'POST',
            'callback' => [$this, 'handleCheckEmail'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        // Check if username exists (for registration validation)
        register_rest_route(self::NAMESPACE, '/auth/check-username', [
            'methods' => 'POST',
            'callback' => [$this, 'handleCheckUsername'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Link Google account to existing user (requires credentials)
        register_rest_route(self::NAMESPACE, '/auth/link-google', [
            'methods' => 'POST',
            'callback' => [$this, 'handleLinkGoogle'],
            'permission_callback' => '__return_true',
            'args' => [
                'credential' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'username' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);

        // Create new account from Google (when user chooses not to link)
        register_rest_route(self::NAMESPACE, '/auth/create-from-google', [
            'methods' => 'POST',
            'callback' => [$this, 'handleCreateFromGoogle'],
            'permission_callback' => '__return_true',
            'args' => [
                'credential' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Handle Google OAuth sign-in
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleGoogleAuth(\WP_REST_Request $request)
    {
        $credential = $request->get_param('credential');

        $googleOAuth = new GoogleOAuth();

        if (!$googleOAuth->isConfigured()) {
            return new \WP_Error(
                'google_not_configured',
                __('Google sign-in is not configured.', 'bigbrotherjunkies-data'),
                ['status' => 500]
            );
        }

        // Verify the Google ID token
        $googleUser = $this->verifyGoogleToken($credential, $googleOAuth);

        if (is_wp_error($googleUser)) {
            return $googleUser;
        }

        // Try to find existing user
        $result = $this->findGoogleUser($googleUser);

        // No existing user found - prompt for linking or new account
        if ($result === null) {
            return new \WP_REST_Response([
                'success' => false,
                'needs_linking' => true,
                'message' => __('No account found with this email. Link an existing account or create a new one.', 'bigbrotherjunkies-data'),
                'google_user' => [
                    'email' => $googleUser['email'],
                    'name' => $googleUser['name'],
                    'picture' => $googleUser['picture'],
                ],
                // Store credential temporarily for linking/creation (frontend will send it back)
                'credential' => $credential,
            ], 200);
        }

        $user = $result['user'];
        $accountLinked = $result['account_linked'] ?? false;

        // Generate JWT token
        $token = $this->generateJwtToken($user);

        if (is_wp_error($token)) {
            return $token;
        }

        $message = __('Welcome back!', 'bigbrotherjunkies-data');
        if ($accountLinked) {
            $message = __('Your Google account has been linked to your existing BBJ account.', 'bigbrotherjunkies-data');
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => $message,
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar' => get_avatar_url($user->ID),
            ],
            'is_new_user' => false,
            'account_linked' => $accountLinked,
        ], 200);
    }

    /**
     * Handle email/password registration
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleRegister(\WP_REST_Request $request)
    {
        // Verify reCAPTCHA if configured
        $recaptchaToken = $request->get_param('recaptcha_token');
        $recaptchaResult = $this->verifyRecaptcha($recaptchaToken);

        if (is_wp_error($recaptchaResult)) {
            // Log failed registration attempt
            $this->logRegistration([
                'user_id' => 0,
                'username' => sanitize_user($request->get_param('username') ?? '', true),
                'email' => sanitize_email($request->get_param('email') ?? ''),
                'method' => 'email',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'recaptcha_score' => null,
                'status' => 'blocked',
                'reason' => $recaptchaResult->get_error_code(),
            ]);
            return $recaptchaResult;
        }

        // Store the reCAPTCHA score for logging
        $recaptchaScore = is_array($recaptchaResult) ? ($recaptchaResult['score'] ?? null) : null;

        $data = [
            'username' => sanitize_user(strtolower($request->get_param('username')), true),
            'email' => $request->get_param('email'),
            'password' => $request->get_param('password'),
            'display_name' => sanitize_text_field($request->get_param('display_name') ?? ''),
            'subscribe_newsletter' => (bool) $request->get_param('subscribe_newsletter'),
        ];

        // Validate username
        if (empty($data['username'])) {
            return new \WP_Error('invalid_username', __('Username is required.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'username']);
        }

        if (strlen($data['username']) < 3) {
            return new \WP_Error('invalid_username', __('Username must be at least 3 characters.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'username']);
        }

        if (!preg_match('/^[a-z0-9]+$/', $data['username'])) {
            return new \WP_Error('invalid_username', __('Username can only contain lowercase letters (a-z) and numbers (0-9).', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'username']);
        }

        if (username_exists($data['username'])) {
            return new \WP_Error('username_exists', __('This username is already taken.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'username']);
        }

        // Validate email
        if (empty($data['email'])) {
            return new \WP_Error('invalid_email', __('Email address is required.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'email']);
        }

        if (!is_email($data['email'])) {
            return new \WP_Error('invalid_email', __('Please enter a valid email address.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'email']);
        }

        if (email_exists($data['email'])) {
            return new \WP_Error('email_exists', __('An account with this email already exists.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'email']);
        }

        // Validate password
        if (empty($data['password'])) {
            return new \WP_Error('invalid_password', __('Password is required.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'password']);
        }

        if (strlen($data['password']) < 8) {
            return new \WP_Error('invalid_password', __('Password must be at least 8 characters.', 'bigbrotherjunkies-data'), ['status' => 400, 'field' => 'password']);
        }

        // Create user
        $userdata = [
            'user_login' => $data['username'],
            'user_email' => $data['email'],
            'user_pass' => $data['password'],
            'display_name' => !empty($data['display_name']) ? $data['display_name'] : $data['username'],
            'role' => 'subscriber',
        ];

        $userId = wp_insert_user($userdata);

        if (is_wp_error($userId)) {
            return new \WP_Error('registration_failed', $userId->get_error_message(), ['status' => 500]);
        }

        // Mark as needing email verification
        update_user_meta($userId, 'bbjd_email_verified', false);
        update_user_meta($userId, 'bbjd_registered_via', 'email');

        // Generate verification token
        $verificationToken = wp_generate_password(32, false);
        update_user_meta($userId, 'bbjd_verification_token', $verificationToken);
        update_user_meta($userId, 'bbjd_verification_expires', time() + (24 * HOUR_IN_SECONDS));

        // Send verification email
        $this->sendVerificationEmail($data['email'], $data['username'], $verificationToken);

        // Handle newsletter subscription
        if ($data['subscribe_newsletter']) {
            try {
                $subscriber = new MailPoetSubscriber();
                $subscriber->subscribe($data['email'], '', '');
            } catch (\Exception $e) {
                // Log error but don't fail registration
                error_log('BBJD Newsletter subscription failed: ' . $e->getMessage());
            }
        }

        // Fire action for other plugins/integrations
        do_action('bbjd_user_registered', $userId, $data);

        // Log the registration
        $this->logRegistration([
            'user_id' => $userId,
            'username' => $data['username'],
            'email' => $data['email'],
            'method' => 'email',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'recaptcha_score' => $recaptchaScore,
            'status' => 'success',
        ]);

        // Get user object for JWT generation
        $user = get_user_by('ID', $userId);

        // Generate JWT token for auto-login
        $token = $this->generateJwtToken($user);

        if (is_wp_error($token)) {
            // Registration succeeded but token generation failed
            // Still return success but without token
            return new \WP_REST_Response([
                'success' => true,
                'message' => __('Registration successful! Please check your email to verify your account.', 'bigbrotherjunkies-data'),
                'user' => [
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'avatar' => get_avatar_url($user->ID),
                ],
                'requires_verification' => true,
            ], 201);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Registration successful! Please check your email to verify your account.', 'bigbrotherjunkies-data'),
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar' => get_avatar_url($user->ID),
            ],
            'requires_verification' => true,
        ], 201);
    }

    /**
     * Handle password reset request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleForgotPassword(\WP_REST_Request $request)
    {
        $email = $request->get_param('email');

        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', __('Please enter a valid email address.', 'bigbrotherjunkies-data'), ['status' => 400]);
        }

        $user = get_user_by('email', $email);

        // Always return success to prevent email enumeration
        $successResponse = new \WP_REST_Response([
            'success' => true,
            'message' => __('If an account with that email exists, you will receive a password reset link shortly.', 'bigbrotherjunkies-data'),
        ], 200);

        if (!$user) {
            return $successResponse;
        }

        // Generate reset key
        $resetKey = get_password_reset_key($user);

        if (is_wp_error($resetKey)) {
            // Log error but return success to prevent enumeration
            error_log('Password reset key generation failed: ' . $resetKey->get_error_message());
            return $successResponse;
        }

        // Send reset email
        $this->sendPasswordResetEmail($user, $resetKey);

        return $successResponse;
    }

    /**
     * Check if email exists
     */
    public function handleCheckEmail(\WP_REST_Request $request): \WP_REST_Response
    {
        $email = $request->get_param('email');

        return new \WP_REST_Response([
            'exists' => email_exists($email) !== false,
        ], 200);
    }

    /**
     * Check if username exists
     */
    public function handleCheckUsername(\WP_REST_Request $request): \WP_REST_Response
    {
        $username = sanitize_user(strtolower($request->get_param('username')), true);

        $valid = true;
        $message = '';

        if (strlen($username) < 3) {
            $valid = false;
            $message = __('Username must be at least 3 characters.', 'bigbrotherjunkies-data');
        } elseif (!preg_match('/^[a-z0-9]+$/', $username)) {
            $valid = false;
            $message = __('Username can only contain lowercase letters and numbers.', 'bigbrotherjunkies-data');
        } elseif (username_exists($username)) {
            $valid = false;
            $message = __('This username is already taken.', 'bigbrotherjunkies-data');
        }

        return new \WP_REST_Response([
            'valid' => $valid,
            'exists' => username_exists($username) !== false,
            'message' => $message,
        ], 200);
    }

    /**
     * Verify Google ID token
     *
     * @return array|\WP_Error
     */
    private function verifyGoogleToken(string $idToken, GoogleOAuth $googleOAuth)
    {
        $response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken), [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('google_error', __('Could not verify Google credentials. Please try again.', 'bigbrotherjunkies-data'), ['status' => 500]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($statusCode !== 200 || empty($data)) {
            return new \WP_Error('invalid_token', __('Invalid Google credentials. Please try again.', 'bigbrotherjunkies-data'), ['status' => 401]);
        }

        // Verify the token was issued for our app
        $clientId = $googleOAuth->getClientId();
        if (($data['aud'] ?? '') !== $clientId) {
            return new \WP_Error('invalid_audience', __('Token was not issued for this application.', 'bigbrotherjunkies-data'), ['status' => 401]);
        }

        // Verify the token hasn't expired
        if (isset($data['exp']) && $data['exp'] < time()) {
            return new \WP_Error('token_expired', __('Google credentials have expired. Please try again.', 'bigbrotherjunkies-data'), ['status' => 401]);
        }

        // Verify email is present and verified
        if (empty($data['email'])) {
            return new \WP_Error('no_email', __('Could not retrieve email from Google account.', 'bigbrotherjunkies-data'), ['status' => 400]);
        }

        if (($data['email_verified'] ?? 'false') !== 'true') {
            return new \WP_Error('email_not_verified', __('Please verify your Google email address first.', 'bigbrotherjunkies-data'), ['status' => 400]);
        }

        // Only accept HTTPS picture URLs for security
        $picture = '';
        if (!empty($data['picture'])) {
            $url = esc_url_raw($data['picture']);
            if (filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 'https://') === 0) {
                $picture = $url;
            }
        }

        return [
            'email' => sanitize_email($data['email']),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'given_name' => sanitize_text_field($data['given_name'] ?? ''),
            'family_name' => sanitize_text_field($data['family_name'] ?? ''),
            'picture' => $picture,
            'google_id' => sanitize_text_field($data['sub'] ?? ''),
        ];
    }

    /**
     * Find WordPress user from Google data (does not create)
     * Returns null if no user found
     */
    private function findGoogleUser(array $googleUser): ?array
    {
        // First, check if user exists by email
        $existingUser = get_user_by('email', $googleUser['email']);

        if ($existingUser) {
            // Check if Google account was already linked
            $existingGoogleId = get_user_meta($existingUser->ID, 'bbjd_google_id', true);
            $accountLinked = empty($existingGoogleId);

            // Link Google ID to existing account
            update_user_meta($existingUser->ID, 'bbjd_google_id', $googleUser['google_id']);

            // Mark email as verified (Google verified it)
            update_user_meta($existingUser->ID, 'bbjd_email_verified', true);

            return [
                'user' => $existingUser,
                'is_new' => false,
                'account_linked' => $accountLinked,
            ];
        }

        // Check if user exists by Google ID (in case email changed)
        $users = get_users([
            'meta_key' => 'bbjd_google_id',
            'meta_value' => $googleUser['google_id'],
            'number' => 1,
        ]);

        if (!empty($users)) {
            return [
                'user' => $users[0],
                'is_new' => false,
                'account_linked' => false,
            ];
        }

        // No user found
        return null;
    }

    /**
     * Create WordPress user from Google data
     *
     * @return array|\WP_Error
     */
    private function createGoogleUser(array $googleUser)
    {
        $username = $this->generateUniqueUsername($googleUser['email']);
        $password = wp_generate_password(24, true, true);

        $userdata = [
            'user_login' => $username,
            'user_email' => $googleUser['email'],
            'user_pass' => $password,
            'display_name' => $googleUser['name'] ?: $username,
            'first_name' => $googleUser['given_name'],
            'last_name' => $googleUser['family_name'],
            'role' => 'subscriber',
        ];

        $userId = wp_insert_user($userdata);

        if (is_wp_error($userId)) {
            return $userId;
        }

        // Store Google ID and mark as verified
        update_user_meta($userId, 'bbjd_google_id', $googleUser['google_id']);
        update_user_meta($userId, 'bbjd_email_verified', true);
        update_user_meta($userId, 'bbjd_registered_via', 'google');

        // Store Google profile picture URL
        if (!empty($googleUser['picture'])) {
            update_user_meta($userId, 'bbjd_google_picture', $googleUser['picture']);
        }

        return [
            'user' => get_user_by('ID', $userId),
            'is_new' => true,
        ];
    }

    /**
     * Handle linking Google account to existing user
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleLinkGoogle(\WP_REST_Request $request)
    {
        $credential = $request->get_param('credential');
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $googleOAuth = new GoogleOAuth();

        if (!$googleOAuth->isConfigured()) {
            return new \WP_Error(
                'google_not_configured',
                __('Google sign-in is not configured.', 'bigbrotherjunkies-data'),
                ['status' => 500]
            );
        }

        // Verify the Google ID token
        $googleUser = $this->verifyGoogleToken($credential, $googleOAuth);

        if (is_wp_error($googleUser)) {
            return $googleUser;
        }

        // Verify the username/password
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return new \WP_Error(
                'invalid_credentials',
                __('Invalid username or password. Please check your credentials and try again.', 'bigbrotherjunkies-data'),
                ['status' => 401]
            );
        }

        // Link Google ID to the verified account
        update_user_meta($user->ID, 'bbjd_google_id', $googleUser['google_id']);

        // Store Google profile picture if user doesn't have one
        if (!empty($googleUser['picture'])) {
            $existingPicture = get_user_meta($user->ID, 'bbjd_google_picture', true);
            if (empty($existingPicture)) {
                update_user_meta($user->ID, 'bbjd_google_picture', $googleUser['picture']);
            }
        }

        // Generate JWT token
        $token = $this->generateJwtToken($user);

        if (is_wp_error($token)) {
            return $token;
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Your Google account has been linked successfully! You can now sign in with either method.', 'bigbrotherjunkies-data'),
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar' => get_avatar_url($user->ID),
            ],
        ], 200);
    }

    /**
     * Handle creating new account from Google
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleCreateFromGoogle(\WP_REST_Request $request)
    {
        $credential = $request->get_param('credential');

        $googleOAuth = new GoogleOAuth();

        if (!$googleOAuth->isConfigured()) {
            return new \WP_Error(
                'google_not_configured',
                __('Google sign-in is not configured.', 'bigbrotherjunkies-data'),
                ['status' => 500]
            );
        }

        // Verify the Google ID token
        $googleUser = $this->verifyGoogleToken($credential, $googleOAuth);

        if (is_wp_error($googleUser)) {
            return $googleUser;
        }

        // Check if user already exists (shouldn't happen, but safety check)
        $existingUser = get_user_by('email', $googleUser['email']);
        if ($existingUser) {
            return new \WP_Error(
                'email_exists',
                __('An account with this email already exists.', 'bigbrotherjunkies-data'),
                ['status' => 400]
            );
        }

        // Create the new user
        $result = $this->createGoogleUser($googleUser);

        if (is_wp_error($result)) {
            return $result;
        }

        $user = $result['user'];

        // Log the Google registration
        $this->logRegistration([
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'method' => 'google',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'recaptcha_score' => null,
            'status' => 'success',
        ]);

        // Generate JWT token
        $token = $this->generateJwtToken($user);

        if (is_wp_error($token)) {
            return $token;
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Account created successfully! Welcome to Big Brother Junkies!', 'bigbrotherjunkies-data'),
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'email' => $user->user_email,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar' => get_avatar_url($user->ID),
            ],
            'is_new_user' => true,
        ], 201);
    }

    /**
     * Generate a unique username from email
     */
    private function generateUniqueUsername(string $email): string
    {
        $baseUsername = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]));

        if (strlen($baseUsername) < 3) {
            $baseUsername = 'user' . $baseUsername;
        }

        $username = $baseUsername;
        $counter = 1;

        while (username_exists($username)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate JWT token for user
     *
     * @return string|\WP_Error
     */
    private function generateJwtToken(\WP_User $user)
    {
        $secretKey = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;

        if (!$secretKey) {
            return new \WP_Error('jwt_not_configured', __('JWT authentication is not configured.', 'bigbrotherjunkies-data'), ['status' => 500]);
        }

        $issuedAt = time();
        $expire = apply_filters('jwt_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);

        $tokenData = [
            'iss' => get_bloginfo('url'),
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'user' => [
                    'id' => $user->ID,
                ],
            ],
        ];

        $algorithm = apply_filters('jwt_auth_algorithm', 'HS256');

        try {
            return JWT::encode($tokenData, $secretKey, $algorithm);
        } catch (\Exception $e) {
            return new \WP_Error('jwt_encode_failed', __('Failed to generate authentication token.', 'bigbrotherjunkies-data'), ['status' => 500]);
        }
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail(string $email, string $username, string $token): void
    {
        $verifyUrl = add_query_arg([
            'action' => 'bbjd_verify_email',
            'token' => $token,
            'email' => urlencode($email),
        ], home_url('/'));

        $subject = sprintf(__('Verify your email - %s', 'bigbrotherjunkies-data'), get_bloginfo('name'));

        $message = sprintf(
            __("Hi %s,\n\nThanks for registering at %s! Please click the link below to verify your email address:\n\n%s\n\nThis link will expire in 24 hours.\n\nIf you didn't create an account, you can safely ignore this email.\n\nThanks,\n%s", 'bigbrotherjunkies-data'),
            $username,
            get_bloginfo('name'),
            $verifyUrl,
            get_bloginfo('name')
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail(\WP_User $user, string $resetKey): void
    {
        // Use WordPress's built-in password reset URL format
        $resetUrl = network_site_url("wp-login.php?action=rp&key=$resetKey&login=" . rawurlencode($user->user_login), 'login');

        $subject = sprintf(__('Password Reset - %s', 'bigbrotherjunkies-data'), get_bloginfo('name'));

        $message = sprintf(
            __("Hi %s,\n\nSomeone requested a password reset for your account at %s.\n\nIf this was you, click the link below to reset your password:\n\n%s\n\nThis link will expire in 24 hours.\n\nIf you didn't request this, you can safely ignore this email.\n\nThanks,\n%s", 'bigbrotherjunkies-data'),
            $user->display_name,
            get_bloginfo('name'),
            $resetUrl,
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Verify reCAPTCHA token
     *
     * @param string|null $token The reCAPTCHA token from the frontend
     * @return array|\WP_Error Array with score if valid, WP_Error if invalid
     */
    private function verifyRecaptcha($token)
    {
        $secretKey = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';

        // If secret key is not configured, skip verification (allows testing without captcha)
        if (empty($secretKey)) {
            return ['score' => null, 'skipped' => true];
        }

        // Token is required if reCAPTCHA is configured
        if (empty($token)) {
            return new \WP_Error(
                'recaptcha_missing',
                __('Security verification is required. Please try again.', 'bigbrotherjunkies-data'),
                ['status' => 400]
            );
        }

        // Verify with Google
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body' => [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('BBJD reCAPTCHA verification failed: ' . $response->get_error_message());
            return new \WP_Error(
                'recaptcha_error',
                __('Security verification failed. Please try again.', 'bigbrotherjunkies-data'),
                ['status' => 500]
            );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        // Log the full response for debugging
        error_log('BBJD reCAPTCHA response: ' . $body);

        if (empty($result['success'])) {
            $errorCodes = $result['error-codes'] ?? [];
            error_log('BBJD reCAPTCHA failed with codes: ' . implode(', ', $errorCodes));

            // Provide more helpful error messages based on error codes
            $errorMessage = __('Security verification failed. Please try again.', 'bigbrotherjunkies-data');
            if (in_array('invalid-input-secret', $errorCodes)) {
                error_log('BBJD reCAPTCHA: Invalid secret key - check RECAPTCHA_SECRET_KEY in wp-config.php');
            }
            if (in_array('timeout-or-duplicate', $errorCodes)) {
                $errorMessage = __('Security token expired. Please try again.', 'bigbrotherjunkies-data');
            }

            return new \WP_Error(
                'recaptcha_failed',
                $errorMessage,
                ['status' => 400]
            );
        }

        // Check the score (v3 returns a score from 0.0 to 1.0)
        // 0.0 = likely bot, 1.0 = likely human
        // Threshold of 0.5 is Google's recommended default
        $score = $result['score'] ?? 1.0;
        $threshold = apply_filters('bbjd_recaptcha_threshold', 0.5);

        // Log all scores for monitoring
        error_log(sprintf(
            'BBJD reCAPTCHA: score=%.2f, threshold=%.2f, action=%s, hostname=%s, passed=%s',
            $score,
            $threshold,
            $result['action'] ?? 'unknown',
            $result['hostname'] ?? 'unknown',
            $score >= $threshold ? 'YES' : 'NO'
        ));

        if ($score < $threshold) {
            return new \WP_Error(
                'recaptcha_low_score',
                __('Registration blocked due to suspicious activity. Please try again later.', 'bigbrotherjunkies-data'),
                ['status' => 403]
            );
        }

        // Verify action matches (optional but recommended)
        $action = $result['action'] ?? '';
        if ($action !== 'register') {
            error_log("BBJD reCAPTCHA action mismatch: expected 'register', got '{$action}'");
            // Don't fail on action mismatch, just log it
        }

        return [
            'score' => $score,
            'action' => $action,
            'hostname' => $result['hostname'] ?? '',
        ];
    }

    /**
     * Log a registration attempt
     */
    private function logRegistration(array $data): void
    {
        $logs = get_option('bbjd_registration_logs', []);

        // Add timestamp
        $data['timestamp'] = current_time('mysql');

        // Add to beginning of array
        array_unshift($logs, $data);

        // Keep only last 100 entries
        $logs = array_slice($logs, 0, 100);

        update_option('bbjd_registration_logs', $logs);
    }

    /**
     * Get registration logs
     */
    public static function getRegistrationLogs(int $limit = 50): array
    {
        $logs = get_option('bbjd_registration_logs', []);
        return array_slice($logs, 0, $limit);
    }

    /**
     * Clear registration logs
     */
    public static function clearRegistrationLogs(): void
    {
        delete_option('bbjd_registration_logs');
    }
}
