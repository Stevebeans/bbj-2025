<?php

namespace BigBrotherJunkies\Data\Stories;

/** Caption + credit helpers. Pure (unit-tested). */
class QuickieHandoffLogic
{
    public static function creditHandle(array $item): string
    {
        if (($item['source'] ?? '') === 'instagram') {
            return '@' . ltrim((string) ($item['source_key'] ?? ''), '@');
        }
        return trim((string) ($item['author'] ?? '')) !== '' ? trim((string) $item['author']) : (string) ($item['source_key'] ?? '');
    }

    public static function creditLine(array $item): string
    {
        $url = (string) ($item['url'] ?? '');
        switch ($item['source'] ?? '') {
            case 'instagram':
                return "📸 " . self::creditHandle($item) . " on Instagram\n" . $url;
            case 'reddit':
                return 'via ' . self::creditHandle($item) . ' on r/' . (string) $item['source_key'] . "\n" . $url;
            case 'bluesky':
                return 'via ' . self::creditHandle($item) . " on Bluesky\n" . $url;
            default:
                return 'via ' . self::creditHandle($item) . "\n" . $url;
        }
    }

    /** Source caption/excerpt, hashtags + "link in bio" stripped, then the credit line. */
    public static function defaultCaption(array $item): string
    {
        $body = trim((string) ($item['excerpt'] ?? ''));
        if ($body === '' || ($item['source'] ?? '') === 'reddit') {
            $body = trim((string) ($item['title'] ?? ''));
        }
        $body = preg_replace('/\s*#\w+/u', '', $body) ?? $body;
        $body = preg_replace('/^.*link in (my )?bio.*$/imu', '', $body) ?? $body;
        $body = trim(preg_replace("/\n{3,}/", "\n\n", $body) ?? $body);
        $body = mb_substr($body, 0, 600);
        return ($body !== '' ? $body . "\n\n" : '') . self::creditLine($item);
    }

    public static function hasCredit(string $caption, array $item): bool
    {
        $handle = self::creditHandle($item);
        return $handle !== '' && mb_stripos($caption, $handle) !== false;
    }
}
