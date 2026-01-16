<?php

namespace BigBrotherJunkies\Data\Auth;

use BigBrotherJunkies\Data\Auth\Integrations\MailPoetSubscriber;

/**
 * Handles user registration functionality
 */
class RegistrationHandler
{
    /**
     * Handle AJAX registration request
     */
    public function handleAjaxRegistration(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bbjd_register_nonce')) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'bigbrotherjunkies-data')]);
        }

        // Collect and sanitize data
        $data = $this->sanitizeRegistrationData($_POST);

        // Validate data
        $validation = $this->validateRegistrationData($data);

        if (is_wp_error($validation)) {
            wp_send_json_error([
                'message' => $validation->get_error_message(),
                'field' => $validation->get_error_code(),
            ]);
        }

        // Create user
        $result = $this->createUser($data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Handle profile picture if uploaded
        if (!empty($data['profile_picture_id'])) {
            $this->attachProfilePicture($result, (int) $data['profile_picture_id']);
        }

        // Handle newsletter subscription
        if (!empty($data['subscribe_newsletter'])) {
            $this->subscribeToNewsletter($data['email'], $data['first_name'], $data['last_name']);
        }

        wp_send_json_success([
            'message' => __('Registration successful! You can now log in.', 'bigbrotherjunkies-data'),
            'redirect' => wp_login_url(),
        ]);
    }

    /**
     * Sanitize registration data
     */
    private function sanitizeRegistrationData(array $post): array
    {
        return [
            'username' => sanitize_user(strtolower($post['username'] ?? ''), true),
            'email' => sanitize_email($post['email'] ?? ''),
            'email_confirm' => sanitize_email($post['email_confirm'] ?? ''),
            'password' => $post['password'] ?? '',
            'password_confirm' => $post['password_confirm'] ?? '',
            'display_name' => sanitize_text_field($post['display_name'] ?? ''),
            'first_name' => sanitize_text_field($post['first_name'] ?? ''),
            'last_name' => sanitize_text_field($post['last_name'] ?? ''),
            'profile_picture_id' => absint($post['profile_picture_id'] ?? 0),
            'subscribe_newsletter' => !empty($post['subscribe_newsletter']),
        ];
    }

    /**
     * Validate registration data
     */
    private function validateRegistrationData(array $data): bool|\WP_Error
    {
        // Username validation
        if (empty($data['username'])) {
            return new \WP_Error('username', __('Username is required.', 'bigbrotherjunkies-data'));
        }

        if (strlen($data['username']) < 3) {
            return new \WP_Error('username', __('Username must be at least 3 characters.', 'bigbrotherjunkies-data'));
        }

        if (!preg_match('/^[a-z0-9]+$/', $data['username'])) {
            return new \WP_Error('username', __('Username can only contain lowercase letters (a-z) and numbers (0-9).', 'bigbrotherjunkies-data'));
        }

        if (username_exists($data['username'])) {
            return new \WP_Error('username', __('This username is already taken.', 'bigbrotherjunkies-data'));
        }

        // Email validation
        if (empty($data['email'])) {
            return new \WP_Error('email', __('Email address is required.', 'bigbrotherjunkies-data'));
        }

        if (!is_email($data['email'])) {
            return new \WP_Error('email', __('Please enter a valid email address.', 'bigbrotherjunkies-data'));
        }

        if ($data['email'] !== $data['email_confirm']) {
            return new \WP_Error('email_confirm', __('Email addresses do not match.', 'bigbrotherjunkies-data'));
        }

        if (email_exists($data['email'])) {
            return new \WP_Error('email', __('An account with this email already exists.', 'bigbrotherjunkies-data'));
        }

        // Password validation
        if (empty($data['password'])) {
            return new \WP_Error('password', __('Password is required.', 'bigbrotherjunkies-data'));
        }

        if (strlen($data['password']) < 8) {
            return new \WP_Error('password', __('Password must be at least 8 characters.', 'bigbrotherjunkies-data'));
        }

        if ($data['password'] !== $data['password_confirm']) {
            return new \WP_Error('password_confirm', __('Passwords do not match.', 'bigbrotherjunkies-data'));
        }

        return true;
    }

    /**
     * Create WordPress user
     */
    private function createUser(array $data): int|\WP_Error
    {
        $userdata = [
            'user_login' => $data['username'],
            'user_email' => $data['email'],
            'user_pass' => $data['password'],
            'display_name' => !empty($data['display_name']) ? $data['display_name'] : $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => 'subscriber',
        ];

        $userId = wp_insert_user($userdata);

        if (is_wp_error($userId)) {
            return $userId;
        }

        // Fire action for other plugins/integrations
        do_action('bbjd_user_registered', $userId, $data);

        return $userId;
    }

    /**
     * Attach profile picture to user
     */
    private function attachProfilePicture(int $userId, int $attachmentId): void
    {
        // Verify attachment exists and is an image
        if (!wp_attachment_is_image($attachmentId)) {
            return;
        }

        // Store as user meta (compatible with most avatar plugins)
        update_user_meta($userId, 'wp_user_avatar', $attachmentId);

        // Also store in standard meta for compatibility
        update_user_meta($userId, '_bbjd_profile_picture', $attachmentId);
    }

    /**
     * Subscribe user to newsletter via MailPoet
     */
    private function subscribeToNewsletter(string $email, string $firstName = '', string $lastName = ''): void
    {
        try {
            $subscriber = new MailPoetSubscriber();
            $subscriber->subscribe($email, $firstName, $lastName);
        } catch (\Exception $e) {
            // Log error but don't fail registration
            error_log('BBJD Newsletter subscription failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle profile picture upload via AJAX
     */
    public function handleProfilePictureUpload(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'bbjd_register_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'bigbrotherjunkies-data')]);
        }

        // Check if file was uploaded
        if (empty($_FILES['profile_picture'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'bigbrotherjunkies-data')]);
        }

        $file = $_FILES['profile_picture'];

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload a JPG, PNG, or GIF.', 'bigbrotherjunkies-data')]);
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            wp_send_json_error(['message' => __('File too large. Maximum size is 5MB.', 'bigbrotherjunkies-data')]);
        }

        // Include WordPress upload handling
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Handle the upload
        $attachmentId = media_handle_upload('profile_picture', 0);

        if (is_wp_error($attachmentId)) {
            wp_send_json_error(['message' => $attachmentId->get_error_message()]);
        }

        wp_send_json_success([
            'attachment_id' => $attachmentId,
            'url' => wp_get_attachment_image_url($attachmentId, 'thumbnail'),
        ]);
    }
}
