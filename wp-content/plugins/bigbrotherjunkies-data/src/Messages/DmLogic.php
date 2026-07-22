<?php

namespace BigBrotherJunkies\Data\Messages;

/**
 * Pure DM decision logic (no WordPress) so it unit-tests cleanly.
 */
class DmLogic
{
    public const MAX_BODY_CHARS = 2000;
    public const PRIVACY_VALUES = ['everyone', 'following', 'nobody'];

    /** Ordered [lo, hi] pair for the threads UNIQUE key. */
    public static function pair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    /**
     * May the sender open/continue a thread with the recipient?
     * Block beats everything; unknown privacy values behave as 'everyone'.
     *
     * @return array{ok:bool,reason:?string}
     */
    public static function canMessage(string $privacy, bool $recipientFollowsSender, bool $blockedEitherWay): array
    {
        if ($blockedEitherWay) {
            return ['ok' => false, 'reason' => 'blocked'];
        }
        if ($privacy === 'nobody') {
            return ['ok' => false, 'reason' => 'closed'];
        }
        if ($privacy === 'following' && !$recipientFollowsSender) {
            return ['ok' => false, 'reason' => 'not_followed'];
        }
        return ['ok' => true, 'reason' => null];
    }

    /** A thread is unread for me when the other side sent after my last read. */
    public static function isUnread(?string $lastMessageAt, ?string $myReadAt, int $lastSenderId, int $myId): bool
    {
        if ($lastMessageAt === null || $lastSenderId === $myId) {
            return false;
        }
        return $myReadAt === null || strcmp($lastMessageAt, $myReadAt) > 0;
    }

    /**
     * Trim + bound the (already entity-decoded) body.
     *
     * @return array{ok:bool,body:string,reason:?string}
     */
    public static function validBody(string $decoded): array
    {
        $body = trim($decoded);
        if ($body === '') {
            return ['ok' => false, 'body' => '', 'reason' => 'empty'];
        }
        if (mb_strlen($body) > self::MAX_BODY_CHARS) {
            return ['ok' => false, 'body' => $body, 'reason' => 'too_long'];
        }
        return ['ok' => true, 'body' => $body, 'reason' => null];
    }
}
