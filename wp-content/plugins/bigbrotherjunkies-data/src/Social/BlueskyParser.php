<?php

namespace BigBrotherJunkies\Data\Social;

/**
 * Pure Bluesky JSON -> normalized row parsing. No WordPress dependencies.
 */
class BlueskyParser
{
    public static function fromSearch(array $json): array
    {
        $rows = [];
        foreach ($json['posts'] ?? [] as $post) {
            $row = self::normalize(is_array($post) ? $post : []);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public static function fromAuthorFeed(array $json): array
    {
        $rows = [];
        foreach ($json['feed'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $reasonType = $entry['reason']['$type'] ?? '';
            if (is_string($reasonType) && strpos($reasonType, 'reasonRepost') !== false) {
                continue;
            }

            $post = $entry['post'] ?? null;
            if (!is_array($post)) {
                continue;
            }

            $row = self::normalize($post);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public static function toUtc(string $iso8601): string
    {
        if (trim($iso8601) === '') {
            return '';
        }

        try {
            $dt = new \DateTimeImmutable($iso8601);
        } catch (\Exception $e) {
            return '';
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function normalize(array $post): ?array
    {
        $uri = $post['uri'] ?? null;
        $handle = $post['author']['handle'] ?? null;
        $text = $post['record']['text'] ?? null;
        $createdAt = $post['record']['createdAt'] ?? null;

        if (!is_string($uri) || $uri === ''
            || !is_string($handle) || $handle === ''
            || !is_string($text) || $text === ''
            || !is_string($createdAt) || $createdAt === ''
        ) {
            return null;
        }

        $postedAt = self::toUtc($createdAt);
        if ($postedAt === '') {
            return null;
        }

        return [
            'uri' => $uri,
            'cid' => is_string($post['cid'] ?? null) ? $post['cid'] : '',
            'handle' => $handle,
            'display_name' => is_string($post['author']['displayName'] ?? null) ? $post['author']['displayName'] : '',
            'text' => $text,
            'posted_at' => $postedAt,
        ];
    }
}
