<?php

namespace BigBrotherJunkies\Data\Tests\Push;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Push\PushSender;

class PushSenderTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_build_payload_json(): void
    {
        Functions\when('wp_json_encode')->alias('json_encode');

        $json = PushSender::buildPayload('New eviction!', 'Someone went home', '/live-feed-updates/x');
        $data = json_decode($json, true);
        $this->assertSame('New eviction!', $data['title']);
        $this->assertSame('Someone went home', $data['body']);
        $this->assertSame('/live-feed-updates/x', $data['url']);
    }

    public function test_build_payload_defaults_url_to_home(): void
    {
        Functions\when('wp_json_encode')->alias('json_encode');

        $data = json_decode(PushSender::buildPayload('t', 'b', ''), true);
        $this->assertSame('/', $data['url']);
    }
}
