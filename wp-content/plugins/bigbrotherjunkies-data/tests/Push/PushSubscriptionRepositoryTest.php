<?php
namespace BigBrotherJunkies\Data\Tests\Push;
use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Push\PushSubscriptionRepository;

class PushSubscriptionRepositoryTest extends TestCase
{
    public function test_hash_is_stable_sha256(): void
    {
        $ep = 'https://fcm.googleapis.com/fcm/send/abc';
        $this->assertSame(hash('sha256', $ep), PushSubscriptionRepository::hash($ep));
        $this->assertSame(64, strlen(PushSubscriptionRepository::hash($ep)));
    }

    public function test_to_web_push_subscription_shape(): void
    {
        $row = (object) ['endpoint' => 'https://e', 'p256dh' => 'KEY', 'auth' => 'AUTH'];
        $shape = PushSubscriptionRepository::toWebPushArray($row);
        $this->assertSame('https://e', $shape['endpoint']);
        $this->assertSame('KEY', $shape['publicKey']);
        $this->assertSame('AUTH', $shape['authToken']);
        $this->assertSame('aesgcm', $shape['contentEncoding']);
    }
}
