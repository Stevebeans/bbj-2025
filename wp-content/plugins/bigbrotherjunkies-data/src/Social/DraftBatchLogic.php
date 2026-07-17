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
     *
     * Tolerant by design, because the model's output is only "mostly JSON":
     * markdown fences, a prose sentence wrapped around the object, a bare
     * [...] array instead of the {"posts":...} envelope, and — the big one —
     * LITERAL newlines inside string values (FB posts carry the 4-blank-line
     * fold, and PHP's json_decode rejects raw control chars in strings). Each
     * is repaired before we give up. Posts missing/blank text are dropped;
     * more than MAX_POSTS are truncated. Zero usable posts => error.
     *
     * @return array{posts: list<array{covers:string,text:string}>}|array{error:string}
     */
    public static function parseModelJson(string $raw): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);

        $decoded = self::decodeFlexible((string) $text);

        $list = null;
        if (is_array($decoded)) {
            if (isset($decoded['posts']) && is_array($decoded['posts'])) {
                $list = $decoded['posts'];
            } elseif (self::isList($decoded)) {
                $list = $decoded; // model returned a bare [...] array of posts
            }
        }

        if ($list === null) {
            // Log enough to tell the failure modes apart next time (truncation
            // ends mid-string; prose wraps the JSON; refusal is plain text).
            $flat = preg_replace('/\s+/', ' ', $raw);
            error_log(sprintf(
                '[BBJ Social] batch parse failed (len=%d, json_err=%s) head=%s | tail=%s',
                strlen($raw),
                json_last_error_msg(),
                substr($flat, 0, 200),
                substr($flat, -80)
            ));
            return ['error' => 'not a {"posts":[...]} object'];
        }

        $posts = [];
        foreach ($list as $post) {
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

    /**
     * Decode JSON that may be wrapped in prose or carry literal control chars
     * inside strings. Tries, in order: as-is; with control chars in string
     * literals re-escaped; the outermost {...} slice repaired; the outermost
     * [...] slice repaired. Null when nothing decodes (e.g. truncated output).
     *
     * @return array<mixed>|null
     */
    private static function decodeFlexible(string $text): ?array
    {
        $attempts = [
            $text,
            self::escapeControlCharsInStrings($text),
        ];
        foreach ([['{', '}'], ['[', ']']] as [$open, $close]) {
            $slice = self::sliceBetween($text, $open, $close);
            if ($slice !== null) {
                $attempts[] = self::escapeControlCharsInStrings($slice);
            }
        }
        foreach ($attempts as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    /** Escape raw newlines/tabs/etc. that sit INSIDE JSON string literals. */
    private static function escapeControlCharsInStrings(string $json): string
    {
        $out = preg_replace_callback(
            '/"(?:\\\\.|[^"\\\\])*"/s',
            static function (array $m): string {
                return str_replace(
                    ["\n", "\r", "\t", "\x08", "\x0C"],
                    ['\\n', '\\r', '\\t', '\\b', '\\f'],
                    $m[0]
                );
            },
            $json
        );
        return $out ?? $json;
    }

    /** Outermost $open..$close slice (drops prose around the JSON). */
    private static function sliceBetween(string $text, string $open, string $close): ?string
    {
        $start = strpos($text, $open);
        $end   = strrpos($text, $close);
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        return substr($text, $start, $end - $start + 1);
    }

    /** True for a 0-indexed sequential array (array_is_list, PHP 7.4-safe). */
    private static function isList(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
