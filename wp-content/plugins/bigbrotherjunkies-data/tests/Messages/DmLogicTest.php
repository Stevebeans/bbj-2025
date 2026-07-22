<?php

namespace BigBrotherJunkies\Tests\Messages;

use BigBrotherJunkies\Data\Messages\DmLogic;
use PHPUnit\Framework\TestCase;

class DmLogicTest extends TestCase
{
    public function test_pair_orders_ids(): void
    {
        $this->assertSame([3, 9], DmLogic::pair(9, 3));
        $this->assertSame([3, 9], DmLogic::pair(3, 9));
    }

    public function test_can_message_blocked_wins_over_everything(): void
    {
        $r = DmLogic::canMessage('everyone', true, true);
        $this->assertFalse($r['ok']);
        $this->assertSame('blocked', $r['reason']);
    }

    public function test_can_message_nobody_closed(): void
    {
        $r = DmLogic::canMessage('nobody', true, false);
        $this->assertFalse($r['ok']);
        $this->assertSame('closed', $r['reason']);
    }

    public function test_can_message_following_requires_recipient_follow(): void
    {
        $this->assertFalse(DmLogic::canMessage('following', false, false)['ok']);
        $this->assertSame('not_followed', DmLogic::canMessage('following', false, false)['reason']);
        $this->assertTrue(DmLogic::canMessage('following', true, false)['ok']);
    }

    public function test_can_message_everyone_open(): void
    {
        $r = DmLogic::canMessage('everyone', false, false);
        $this->assertTrue($r['ok']);
        $this->assertNull($r['reason']);
    }

    public function test_unknown_privacy_treated_as_everyone(): void
    {
        $this->assertTrue(DmLogic::canMessage('garbage', false, false)['ok']);
    }

    public function test_is_unread_true_when_other_sent_after_my_read(): void
    {
        $this->assertTrue(DmLogic::isUnread('2026-07-22 10:00:00', '2026-07-22 09:00:00', 5, 1));
    }

    public function test_is_unread_false_when_i_sent_last(): void
    {
        $this->assertFalse(DmLogic::isUnread('2026-07-22 10:00:00', null, 1, 1));
    }

    public function test_is_unread_true_when_never_read(): void
    {
        $this->assertTrue(DmLogic::isUnread('2026-07-22 10:00:00', null, 5, 1));
    }

    public function test_is_unread_false_when_read_after(): void
    {
        $this->assertFalse(DmLogic::isUnread('2026-07-22 10:00:00', '2026-07-22 10:00:01', 5, 1));
    }

    public function test_valid_body_trims_and_accepts(): void
    {
        $r = DmLogic::validBody("  hey there  ");
        $this->assertTrue($r['ok']);
        $this->assertSame('hey there', $r['body']);
    }

    public function test_valid_body_rejects_empty_and_too_long(): void
    {
        $this->assertSame('empty', DmLogic::validBody("   ")['reason']);
        $this->assertSame('too_long', DmLogic::validBody(str_repeat('a', 2001))['reason']);
    }
}
