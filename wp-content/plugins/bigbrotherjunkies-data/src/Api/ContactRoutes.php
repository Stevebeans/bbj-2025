<?php

namespace BigBrotherJunkies\Data\Api;

/**
 * Contact Form API Routes
 *
 * Handles contact form submissions with reCAPTCHA verification
 */
class ContactRoutes
{
    /**
     * Contact reasons dropdown options
     */
    private const REASONS = [
        'question' => 'Question',
        'feedback' => 'Feedback',
        'bug_report' => 'Report a Bug',
        'suggestion' => 'Suggestion',
        'business' => 'Business Inquiry',
        'other' => 'Other',
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // Submit contact form
        register_rest_route('bbjd/v1', '/contact', [
            'methods' => 'POST',
            'callback' => [$this, 'submitContact'],
            'permission_callback' => '__return_true',
            'args' => [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($value) {
                        return is_email($value);
                    },
                ],
                'reason' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return array_key_exists($value, self::REASONS);
                    },
                ],
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'recaptcha_token' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // Honeypot field - should be empty
                'website' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get contact reasons (for dropdown)
        register_rest_route('bbjd/v1', '/contact/reasons', [
            'methods' => 'GET',
            'callback' => [$this, 'getReasons'],
            'permission_callback' => '__return_true',
        ]);

        // Get reCAPTCHA site key (public)
        register_rest_route('bbjd/v1', '/contact/recaptcha-key', [
            'methods' => 'GET',
            'callback' => [$this, 'getRecaptchaSiteKey'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Submit contact form
     */
    public function submitContact(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check honeypot - if filled, silently "succeed" but don't send
        $honeypot = $request->get_param('website');
        if (!empty($honeypot)) {
            // Bot detected, fake success
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Thank you for your message! We\'ll get back to you soon.',
            ]);
        }

        // Verify reCAPTCHA
        $recaptchaToken = $request->get_param('recaptcha_token');
        $recaptchaValid = $this->verifyRecaptcha($recaptchaToken);

        if (!$recaptchaValid) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'reCAPTCHA verification failed. Please try again.',
            ], 400);
        }

        // Get form data
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $reason = $request->get_param('reason');
        $reasonLabel = self::REASONS[$reason] ?? 'Other';
        $message = $request->get_param('message');

        // Rate limiting - check if this IP has submitted recently
        $ip = $this->getClientIp();
        $rateLimitKey = 'bbj_contact_' . md5($ip);
        $lastSubmit = get_transient($rateLimitKey);

        if ($lastSubmit !== false) {
            $waitTime = 60 - (time() - $lastSubmit);
            if ($waitTime > 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => "Please wait {$waitTime} seconds before submitting again.",
                ], 429);
            }
        }

        // Build email
        $to = get_option('admin_email');
        $subject = "[BBJ Contact] {$reasonLabel}: " . wp_trim_words($message, 10, '...');

        $body = "New contact form submission from Big Brother Junkies\n\n";
        $body .= "----------------------------------------\n";
        $body .= "Name: {$name}\n";
        $body .= "Email: {$email}\n";
        $body .= "Reason: {$reasonLabel}\n";
        $body .= "----------------------------------------\n\n";
        $body .= "Message:\n\n";
        $body .= $message . "\n\n";
        $body .= "----------------------------------------\n";
        $body .= "IP Address: {$ip}\n";
        $body .= "Submitted: " . current_time('mysql') . "\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            "Reply-To: {$name} <{$email}>",
        ];

        // Send email
        $sent = wp_mail($to, $subject, $body, $headers);

        if (!$sent) {
            // Log the error
            error_log("BBJ Contact Form: Failed to send email from {$email}");

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'There was an error sending your message. Please try again later.',
            ], 500);
        }

        // Set rate limit transient (60 seconds)
        set_transient($rateLimitKey, time(), 60);

        // Log successful submission
        error_log("BBJ Contact Form: Message sent from {$email} ({$reasonLabel})");

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Thank you for your message! We\'ll get back to you soon.',
        ]);
    }

    /**
     * Get contact reasons for dropdown
     */
    public function getReasons(): \WP_REST_Response
    {
        $reasons = [];
        foreach (self::REASONS as $value => $label) {
            $reasons[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return new \WP_REST_Response($reasons);
    }

    /**
     * Get reCAPTCHA site key
     */
    public function getRecaptchaSiteKey(): \WP_REST_Response
    {
        $siteKey = get_option('bbj_recaptcha_site_key', '');

        return new \WP_REST_Response([
            'site_key' => $siteKey,
        ]);
    }

    /**
     * Verify reCAPTCHA token with Google
     */
    private function verifyRecaptcha(string $token): bool
    {
        $secretKey = get_option('bbj_recaptcha_secret_key', '');

        // If no secret key configured, skip verification (for development)
        if (empty($secretKey)) {
            error_log('BBJ Contact Form: reCAPTCHA secret key not configured, skipping verification');
            return true;
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $this->getClientIp(),
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('BBJ Contact Form: reCAPTCHA verification request failed: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['success'])) {
            error_log('BBJ Contact Form: Invalid reCAPTCHA response');
            return false;
        }

        // For reCAPTCHA v3, also check score (optional)
        if (isset($body['score']) && $body['score'] < 0.5) {
            error_log('BBJ Contact Form: reCAPTCHA score too low: ' . $body['score']);
            return false;
        }

        return $body['success'] === true;
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can contain multiple IPs, get the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }
}
