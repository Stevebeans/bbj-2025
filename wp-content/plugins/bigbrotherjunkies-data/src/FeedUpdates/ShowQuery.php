<?php

namespace BigBrotherJunkies\Data\FeedUpdates;

/**
 * tax_query fragments for the bbj_show taxonomy. The invariant every BB
 * surface relies on: NO bbj_show term = a Big Brother update. All legacy
 * content satisfies it for free.
 */
class ShowQuery
{
    public const TAXONOMY = 'bbj_show';

    public static function excludeAll(): array
    {
        return ['taxonomy' => self::TAXONOMY, 'operator' => 'NOT EXISTS'];
    }

    public static function anyShow(): array
    {
        return ['taxonomy' => self::TAXONOMY, 'operator' => 'EXISTS'];
    }

    public static function only(string $slug): array
    {
        return ['taxonomy' => self::TAXONOMY, 'field' => 'slug', 'terms' => sanitize_title($slug)];
    }

    public static function forParam(string $show): array
    {
        if ($show === 'any') {
            return self::anyShow();
        }
        if ($show !== '') {
            return self::only($show);
        }
        return self::excludeAll();
    }

    public static function slugFor(int $postId): ?string
    {
        $terms = get_the_terms($postId, self::TAXONOMY);
        if (!is_array($terms) || $terms === []) {
            return null;
        }
        return $terms[0]->slug;
    }
}
