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

    /** Hard cap on posts per batch, whatever the model returns. Raised from 5
     * on 8/22 with the single-story split (typical full day = 6-12 posts). */
    public const MAX_POSTS = 15;

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
            // Last resort: the model sometimes emits UNESCAPED double quotes
            // inside post text (confirmed 7/17: "lines drawn", "dicey"),
            // which no JSON decoder can survive. Exploit the known envelope
            // instead: covers/text pairs delimited by the "} that closes each
            // post — a sequence prose never contains.
            $list = self::salvagePosts((string) $text);
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
                // A/B opener label, normalized — the model occasionally
                // capitalizes or answers with the style letter, and a strict
                // compare was silently blanking those (8/26: a whole batch
                // queued untagged, invisible to the A/B export join).
                'opener' => self::normalizeOpener((string) ($post['opener'] ?? '')),
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
     * Map whatever the model put in "opener" to a clean A/B label.
     * Recognizes the labels themselves in any casing/padding plus the
     * prompt's style letters ("A"/"Style A" = tease, "B" = recap).
     * Unrecognizable input maps to '' so the experiment stays uncontaminated:
     * a missing label just drops the post from the A/B join, a wrong one
     * would poison it.
     */
    public static function normalizeOpener(string $raw): string
    {
        $v = strtolower(trim($raw));
        if ($v === '') {
            return '';
        }
        if (strpos($v, 'tease') !== false || $v === 'a' || $v === 'style a') {
            return 'tease';
        }
        if (strpos($v, 'recap') !== false || $v === 'b' || $v === 'style b') {
            return 'recap';
        }
        return '';
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
            self::repairCommonJsonFaults(self::escapeControlCharsInStrings($text)),
        ];
        foreach ([['{', '}'], ['[', ']']] as [$open, $close]) {
            $slice = self::sliceBetween($text, $open, $close);
            if ($slice !== null) {
                $attempts[] = self::repairCommonJsonFaults(self::escapeControlCharsInStrings($slice));
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

    /**
     * Late-stage repairs for model-typical JSON faults (applied only after
     * stricter attempts fail): trailing commas before } or ], and invalid
     * escape sequences like \' or \— (backslash kept, escaped as data).
     */
    private static function repairCommonJsonFaults(string $json): string
    {
        $json = (string) preg_replace('/,\s*([\]}])/', '$1', $json);
        $json = (string) preg_replace('/\\\\(?!["\\\\\/bfnrtu])/', '\\\\\\\\', $json);
        return $json;
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

    /**
     * Schema-aware extraction for JSON that decoders reject (unescaped inner
     * quotes). Each post is matched structurally: `{"covers":".."`, then the
     * optional `"opener":".."`, then `"text":"..` up to the `"}` that is
     * followed by `,` or `]`. Text may contain raw quotes freely; JSON
     * escapes (\n, \", \\) are then decoded by hand.
     *
     * @return list<array{covers:string,opener:string,text:string}>|null null when no post matches
     */
    private static function salvagePosts(string $text): ?array
    {
        $matched = preg_match_all(
            // The "opener" group MUST stay optional: it landed in the envelope
            // 8/21 but not here, so the salvage matched nothing until 9/5.
            '/\{\s*"covers"\s*:\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*(?:"opener"\s*:\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*)?"text"\s*:\s*"(.*?)"\s*\}\s*(?=,|\])/s',
            $text,
            $m,
            PREG_SET_ORDER
        );
        if (!$matched) {
            return null;
        }
        $posts = [];
        foreach ($m as $hit) {
            $posts[] = [
                'covers' => self::unescapeJsonString($hit[1]),
                'opener' => self::unescapeJsonString($hit[2]),
                'text'   => self::unescapeJsonString($hit[3]),
            ];
        }
        return $posts;
    }

    /** Decode JSON string escapes by hand (\n, \", \\, \t, \r, \/). */
    private static function unescapeJsonString(string $s): string
    {
        $map = ['n' => "\n", 'r' => "\r", 't' => "\t", '"' => '"', '\\' => '\\', '/' => '/'];
        return (string) preg_replace_callback(
            '/\\\\(.)/s',
            static fn (array $m): string => $map[$m[1]] ?? $m[1],
            $s
        );
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
