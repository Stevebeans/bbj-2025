<?php

namespace BigBrotherJunkies\Data\Auth;

/**
 * Handles user login functionality
 */
class LoginHandler
{
    /**
     * Handle AJAX login request
     */
    public function handleAjaxLogin(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bbjd_login_nonce')) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'bigbrotherjunkies-data')]);
        }

        $loginInput = sanitize_text_field($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        // Validate input
        if (empty($loginInput) || empty($password)) {
            wp_send_json_error(['message' => __('Please enter your username/email and password.', 'bigbrotherjunkies-data')]);
        }

        // Attempt login
        $result = $this->authenticateUser($loginInput, $password, $remember);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $this->getLoginErrorMessage($result)]);
        }

        wp_send_json_success([
            'message' => __('Login successful!', 'bigbrotherjunkies-data'),
            'redirect' => $this->getLoginRedirectUrl($result),
        ]);
    }

    /**
     * Authenticate user with username/email and password
     */
    public function authenticateUser(string $login, string $password, bool $remember = false): \WP_User|\WP_Error
    {
        // Determine if login is email or username
        $isEmail = is_email($login);

        if ($isEmail) {
            $user = get_user_by('email', $login);
            if (!$user) {
                return new \WP_Error('invalid_email', __('No account found with that email address.', 'bigbrotherjunkies-data'));
            }
            $username = $user->user_login;
        } else {
            $username = $login;
        }

        // Attempt WordPress authentication
        $credentials = [
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember,
        ];

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            return $user;
        }

        // Set current user
        wp_set_current_user($user->ID);

        return $user;
    }

    /**
     * Get user-friendly login error message
     */
    private function getLoginErrorMessage(\WP_Error $error): string
    {
        $code = $error->get_error_code();

        $messages = [
            'invalid_username' => __('Invalid username or email address.', 'bigbrotherjunkies-data'),
            'invalid_email' => __('No account found with that email address.', 'bigbrotherjunkies-data'),
            'incorrect_password' => __('Incorrect password. Please try again.', 'bigbrotherjunkies-data'),
            'empty_username' => __('Please enter your username or email.', 'bigbrotherjunkies-data'),
            'empty_password' => __('Please enter your password.', 'bigbrotherjunkies-data'),
        ];

        return $messages[$code] ?? __('Login failed. Please check your credentials and try again.', 'bigbrotherjunkies-data');
    }

    /**
     * Get redirect URL after successful login
     */
    private function getLoginRedirectUrl(\WP_User $user): string
    {
        // Check for redirect parameter
        $redirect = $_POST['redirect_to'] ?? '';

        if (!empty($redirect) && wp_validate_redirect($redirect)) {
            return $redirect;
        }

        // Default redirect based on user role
        if (user_can($user, 'manage_options')) {
            return admin_url();
        }

        // Regular users go to homepage or profile
        return home_url('/');
    }

    /**
     * Handle password reset request
     */
    public function handlePasswordReset(string $email): bool|\WP_Error
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return new \WP_Error('invalid_email', __('No account found with that email address.', 'bigbrotherjunkies-data'));
        }

        // Use WordPress built-in password reset
        $result = retrieve_password($user->user_login);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }
}
