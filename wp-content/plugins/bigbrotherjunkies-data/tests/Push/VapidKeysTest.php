<?php

namespace BigBrotherJunkies\Data\Tests\Push;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Push\VapidKeys;

class VapidKeysTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_returns_configured_keys(): void
    {
        Functions\when('get_option')->justReturn([
            'vapid_public_key' => 'PUB', 'vapid_private_key' => 'PRIV', 'vapid_subject' => 'mailto:steve@bbj.com',
        ]);
        $this->assertSame('PUB', VapidKeys::publicKey());
        $this->assertSame('PRIV', VapidKeys::privateKey());
        $this->assertSame('mailto:steve@bbj.com', VapidKeys::subject());
        $this->assertTrue(VapidKeys::isConfigured());
    }

    public function test_not_configured_when_missing(): void
    {
        Functions\when('get_option')->justReturn([]);
        $this->assertFalse(VapidKeys::isConfigured());
        $this->assertSame('mailto:admin@bigbrotherjunkies.com', VapidKeys::subject());
    }
}
