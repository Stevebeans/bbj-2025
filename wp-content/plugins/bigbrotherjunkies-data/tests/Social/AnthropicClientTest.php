<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\AnthropicClient;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class AnthropicClientTest extends TestCase
{
    /** @var array<string,mixed>|null */
    private $capturedArgs;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->capturedArgs = null;
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** Stub the HTTP layer: capture the request, return a canned API body. */
    private function stubResponse(array $apiBody): void
    {
        Functions\when('wp_remote_post')->alias(function ($url, $args) use ($apiBody) {
            $this->capturedArgs = $args;
            return ['body' => json_encode($apiBody)];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($response) {
            return $response['body'];
        });
    }

    private function requestBody(): array
    {
        return json_decode($this->capturedArgs['body'], true);
    }

    public function test_sonnet5_disables_thinking_so_max_tokens_is_all_text(): void
    {
        $this->stubResponse(['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn']);
        (new AnthropicClient())->complete('sys', 'user', 'claude-sonnet-5', 'sk-test');
        $body = $this->requestBody();
        $this->assertSame(['type' => 'disabled'], $body['thinking']);
        $this->assertSame(8000, $body['max_tokens']);
    }

    public function test_other_models_send_no_thinking_field(): void
    {
        $this->stubResponse(['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn']);
        foreach (['claude-opus-4-8', 'claude-haiku-4-5'] as $model) {
            (new AnthropicClient())->complete('sys', 'user', $model, 'sk-test');
            $this->assertArrayNotHasKey('thinking', $this->requestBody(), $model);
        }
    }

    public function test_thinking_only_max_tokens_response_reports_token_limit(): void
    {
        // The failure seen on prod: adaptive thinking consumed the whole budget,
        // content has thinking blocks only (empty text), stop_reason max_tokens.
        $this->stubResponse([
            'content'     => [['type' => 'thinking', 'thinking' => '']],
            'stop_reason' => 'max_tokens',
        ]);
        $out = (new AnthropicClient())->complete('sys', 'user', 'claude-sonnet-5', 'sk-test');
        $this->assertArrayHasKey('error', $out);
        $this->assertStringContainsString('token limit', $out['error']);
    }

    public function test_plain_empty_response_still_reports_empty(): void
    {
        $this->stubResponse(['content' => [], 'stop_reason' => 'end_turn']);
        $out = (new AnthropicClient())->complete('sys', 'user', 'claude-sonnet-5', 'sk-test');
        $this->assertSame('Empty response from model', $out['error']);
    }
}
