<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure window/parse math for FB batch drafts. No DB, no WP functions,
 * unit-tested (tests/Social/DraftBatchLogicTest.php).
 */
class DraftBatchLogic
{
    /** First-ever batch (no cursor) covers this many hours back. */
    public const DEFAULT_HOURS = 6;

    /** A stale cursor never pulls more than this many hours of backlog. */
    public const MAX_HOURS = 24;

    /** Hard cap on posts per batch, whatever the model returns. */
    public const MAX_POSTS = 5;

    /**
     * UTC window for a batch given the stored cursor (last covered moment).
     * Empty/invalid cursor => last DEFAULT_HOURS. Cursor older than
     * MAX_HOURS => clamped to last MAX_HOURS.
     *
     * @return array{from:string,to:string} 'Y-m-d H:i:s' UTC
     */
    public static function windowFor(?string $cursorUtc, \DateTimeImmutable $nowUtc): array
    {
        $utc = new \DateTimeZone('UTC');
        $now = $nowUtc->setTimezone($utc);

        $floor = $now->sub(new \DateInterval('PT' . self::MAX_HOURS . 'H'));
        $from  = $now->sub(new \DateInterval('PT' . self::DEFAULT_HOURS . 'H'));

        if (is_string($cursorUtc) && $cursorUtc !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $cursorUtc, $utc);
            if ($parsed instanceof \DateTimeImmutable) {
                $from = $parsed < $floor ? $floor : $parsed;
            }
        }

        return [
            'from' => $from->format('Y-m-d H:i:s'),
            'to'   => $now->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse the model's batch JSON: {"posts":[{"covers":"...","text":"..."}]}.
     * Markdown fences tolerated. Posts missing/blank text are dropped; more
     * than MAX_POSTS are truncated. Zero usable posts => error.
     *
     * @return array{posts: list<array{covers:string,text:string}>}|array{error:string}
     */
    public static function parseModelJson(string $raw): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);

        $decoded = json_decode((string) $text, true);
        if (!is_array($decoded) || !isset($decoded['posts']) || !is_array($decoded['posts'])) {
            return ['error' => 'not a {"posts":[...]} object'];
        }

        $posts = [];
        foreach ($decoded['posts'] as $post) {
            if (!is_array($post)) {
                continue;
            }
            $body = trim((string) ($post['text'] ?? ''));
            if ($body === '') {
                continue;
            }
            $posts[] = [
                'covers' => trim((string) ($post['covers'] ?? '')),
                'text'   => $body,
            ];
            if (count($posts) === self::MAX_POSTS) {
                break;
            }
        }

        if ($posts === []) {
            return ['error' => 'no usable posts in model output'];
        }

        return ['posts' => $posts];
    }
}
