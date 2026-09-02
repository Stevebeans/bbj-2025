<?php

namespace BigBrotherJunkies\Data\Stories\Sources;

/** RSS 2.0 + Atom (YouTube channel feeds are Atom). SimpleXML, no SimplePie. */
class RssAdapter implements SourceAdapter
{
    private const MAX_ITEMS = 40;

    public function fetch(array $source): array
    {
        $res = wp_remote_get($source['key'], [
            'timeout'    => 15,
            'user-agent' => 'BigBrotherJunkiesStories/1.0 (+https://bigbrotherjunkies.com)',
        ]);
        if (is_wp_error($res)) {
            return ['items' => [], 'error' => 'rss ' . $source['key'] . ': ' . $res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code !== 200) {
            return ['items' => [], 'error' => 'rss ' . $source['key'] . ': HTTP ' . $code];
        }
        $items = self::parse((string) wp_remote_retrieve_body($res), $source);
        return ['items' => $items, 'error' => $items === [] ? 'rss ' . $source['key'] . ': no items parsed' : null];
    }

    /** @return array<int,array<string,mixed>> */
    public static function parse(string $xml, array $source): array
    {
        if (trim($xml) === '') {
            return [];
        }
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            return [];
        }
        $name = $doc->getName();
        if ($name === 'rss') {
            return self::parseRss($doc, $source);
        }
        if ($name === 'feed') {
            return self::parseAtom($doc, $source);
        }
        return [];
    }

    private static function parseRss(\SimpleXMLElement $doc, array $source): array
    {
        $out = [];
        foreach ($doc->channel->item ?? [] as $item) {
            $url = trim((string) $item->link);
            if ($url === '') {
                continue;
            }
            $dc    = $item->children('http://purl.org/dc/elements/1.1/');
            $media = $item->children('http://search.yahoo.com/mrss/');
            $thumb = '';
            if (isset($media->thumbnail)) {
                $thumb = (string) $media->thumbnail->attributes()->url;
            } elseif (isset($item->enclosure) && str_starts_with((string) $item->enclosure->attributes()->type, 'image/')) {
                $thumb = (string) $item->enclosure->attributes()->url;
            }
            $out[] = self::item($source, [
                'url'           => $url,
                'title'         => (string) $item->title,
                'excerpt'       => (string) $item->description,
                'author'        => (string) ($dc->creator ?? $item->author ?? ''),
                'published'     => (string) $item->pubDate,
                'media_type'    => $thumb !== '' ? 'image' : '',
                'thumbnail_url' => $thumb,
            ]);
            if (count($out) >= self::MAX_ITEMS) {
                break;
            }
        }
        return $out;
    }

    private static function parseAtom(\SimpleXMLElement $doc, array $source): array
    {
        $out = [];
        foreach ($doc->entry ?? [] as $entry) {
            $url = '';
            foreach ($entry->link as $link) {
                $rel = (string) $link->attributes()->rel;
                if ($rel === '' || $rel === 'alternate') {
                    $url = (string) $link->attributes()->href;
                    break;
                }
            }
            if ($url === '') {
                continue;
            }
            $media   = $entry->children('http://search.yahoo.com/mrss/');
            $excerpt = (string) ($entry->summary ?? $entry->content ?? '');
            $thumb   = '';
            $isVideo = false;
            if (isset($media->group)) {
                $g       = $media->group;
                $excerpt = (string) ($g->description ?? $excerpt);
                $thumb   = isset($g->thumbnail) ? (string) $g->thumbnail->attributes()->url : '';
                $isVideo = str_contains($url, 'youtube.com/watch') || str_contains($url, 'youtu.be/');
            }
            $out[] = self::item($source, [
                'url'           => $url,
                'title'         => (string) $entry->title,
                'excerpt'       => $excerpt,
                'author'        => (string) ($entry->author->name ?? ''),
                'published'     => (string) ($entry->published ?? $entry->updated ?? ''),
                'media_type'    => $isVideo ? 'video' : ($thumb !== '' ? 'image' : ''),
                'thumbnail_url' => $thumb,
            ]);
            if (count($out) >= self::MAX_ITEMS) {
                break;
            }
        }
        return $out;
    }

    private static function item(array $source, array $f): array
    {
        $ts = strtotime($f['published']);
        return [
            'show'          => $source['show'],
            'source'        => 'rss',
            'source_key'    => $source['key'],
            'url'           => $f['url'],
            'title'         => self::clean($f['title'], 255),
            'excerpt'       => self::clean($f['excerpt'], 1200),
            'author'        => self::clean($f['author'], 190),
            'media_type'    => $f['media_type'],
            'media_url'     => '',
            'thumbnail_url' => $f['thumbnail_url'],
            'published_at'  => gmdate('Y-m-d H:i:s', $ts !== false ? $ts : time()),
            'trust'         => $source['trust'] ?? 'standard',
            'score'         => 0,
        ];
    }

    private static function clean(string $s, int $max): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        return mb_substr($s, 0, $max);
    }
}
