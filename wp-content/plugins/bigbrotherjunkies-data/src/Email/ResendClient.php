<?php

namespace BigBrotherJunkies\Data\Email;

/**
 * Thin wrapper around Resend's REST API
 *
 * Uses wp_remote_request() for HTTP calls.
 * Settings stored in 'bbjd_email_settings' option.
 *
 * @see https://resend.com/docs/api-reference
 */
class ResendClient
{
    private const BASE_URL = 'https://api.resend.com';
    private const BATCH_LIMIT = 100;
    private const TIMEOUT = 30;

    private string $apiKey;
    private string $fromName;
    private string $fromAddress;

    public function __construct()
    {
        $settings = get_option('bbjd_email_settings', []);

        $this->apiKey = $settings['resend_api_key'] ?? '';
        $this->fromName = $settings['email_from_name'] ?? 'Big Brother Junkies';
        $this->fromAddress = $settings['email_from_address'] ?? 'noreply@bigbrotherjunkies.com';
    }

    /**
     * Send a single email via Resend
     *
     * @param string $to      Recipient email address
     * @param string $subject Email subject line
     * @param string $html    HTML body content
     * @param array  $tags    Optional tags, e.g. [['name' => 'category', 'value' => 'confirmation']]
     * @return string|null     Resend message ID on success, null on failure
     */
    public function send(string $to, string $subject, string $html, array $tags = []): ?string
    {
        $body = [
            'from'    => "{$this->fromName} <{$this->fromAddress}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ];

        if (!empty($tags)) {
            $body['tags'] = $tags;
        }

        $response = $this->request('POST', '/emails', $body);

        if ($response === null) {
            return null;
        }

        return $response['id'] ?? null;
    }

    /**
     * Send a batch of emails via Resend (up to 100 per API call)
     *
     * @param array $emails Array of ['to' => email, 'subject' => ..., 'html' => ..., 'tags' => []]
     * @return array         Array of Resend message IDs (empty strings for failed items)
     */
    public function sendBatch(array $emails): array
    {
        $allIds = [];
        $from = "{$this->fromName} <{$this->fromAddress}>";

        $chunks = array_chunk($emails, self::BATCH_LIMIT);

        foreach ($chunks as $chunk) {
            $batchPayload = [];

            foreach ($chunk as $email) {
                $item = [
                    'from'    => $from,
                    'to'      => [$email['to']],
                    'subject' => $email['subject'],
                    'html'    => $email['html'],
                ];

                if (!empty($email['tags'])) {
                    $item['tags'] = $email['tags'];
                }

                $batchPayload[] = $item;
            }

            $response = $this->request('POST', '/emails/batch', $batchPayload);

            if ($response !== null && isset($response['data']) && is_array($response['data'])) {
                foreach ($response['data'] as $item) {
                    $allIds[] = $item['id'] ?? '';
                }
            } else {
                // Fill with empty strings for the failed chunk
                $allIds = array_merge($allIds, array_fill(0, count($chunk), ''));
            }
        }

        return $allIds;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getFromName(): string
    {
        return $this->fromName;
    }

    /**
     * Make an HTTP request to the Resend API
     *
     * @param string $method   HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path (e.g. '/emails')
     * @param array  $body     Request body (will be JSON-encoded)
     * @return array|null       Decoded response body, or null on failure
     */
    private function request(string $method, string $endpoint, array $body = []): ?array
    {
        if (!$this->isConfigured()) {
            error_log('[BBJ Email] Resend API key not configured');
            return null;
        }

        $url = self::BASE_URL . $endpoint;

        $args = [
            'method'  => $method,
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
        ];

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('[BBJ Email] Resend API error: ' . $response->get_error_message());
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = $decoded['message'] ?? $responseBody;
            error_log("[BBJ Email] Resend API HTTP {$statusCode}: {$errorMessage}");
            return null;
        }

        return $decoded;
    }
}
