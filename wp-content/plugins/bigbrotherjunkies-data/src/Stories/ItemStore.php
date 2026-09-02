<?php

namespace BigBrotherJunkies\Data\Stories;

class ItemStore
{
    /** INSERT IGNORE each item (uniq_url dedupes across sources). Returns rows added. */
    public static function insertItems(array $items): int
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_ITEMS);
        $now = gmdate('Y-m-d H:i:s');
        $added = 0;
        foreach ($items as $it) {
            $url = substr((string) ($it['url'] ?? ''), 0, 255);
            if ($url === '') {
                continue;
            }
            $res = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$t}
                 (show_slug, source, source_key, url, title, excerpt, author, media_type, media_url, thumbnail_url,
                  published_at, fetched_at, trust, score, processed, inbox_status)
                 VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,0,'new')",
                (string) $it['show'],
                (string) $it['source'],
                substr((string) ($it['source_key'] ?? ''), 0, 190),
                $url,
                wp_encode_emoji(substr((string) ($it['title'] ?? ''), 0, 255)),
                wp_encode_emoji((string) ($it['excerpt'] ?? '')),
                substr((string) ($it['author'] ?? ''), 0, 190),
                (string) ($it['media_type'] ?? ''),
                (string) ($it['media_url'] ?? ''),
                (string) ($it['thumbnail_url'] ?? ''),
                (string) $it['published_at'],
                $now,
                (string) ($it['trust'] ?? 'standard'),
                (int) ($it['score'] ?? 0)
            ));
            if ($res === 1) {
                $added++;
            }
        }
        return $added;
    }

    public static function prune(int $days = 14): int
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_ITEMS);
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $n = $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE published_at < %s", $cutoff));
        return is_int($n) ? $n : 0;
    }

    public static function get(int $id): ?array
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_ITEMS);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d", $id), ARRAY_A);
        return $row ? self::decode($row) : null;
    }

    /** @return array{items:array<int,array>,total:int} */
    public static function inbox(array $args): array
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_ITEMS);
        $where  = ['1=1'];
        $params = [];
        $status = (string) ($args['status'] ?? 'new');
        if (in_array($status, ['new', 'used', 'dismissed'], true)) {
            $where[] = 'inbox_status = %s';
            $params[] = $status;
        }
        if (!empty($args['show'])) {
            $where[] = 'show_slug = %s';
            $params[] = (string) $args['show'];
        }
        if (!empty($args['source'])) {
            $where[] = 'source = %s';
            $params[] = (string) $args['source'];
        }
        $days = max(1, min(14, (int) ($args['days'] ?? 7)));
        $where[] = 'published_at >= %s';
        $params[] = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        $order   = (($args['sort'] ?? 'score') === 'time') ? 'published_at DESC' : 'score DESC, published_at DESC';
        $perPage = max(1, min(100, (int) ($args['per_page'] ?? 40)));
        $offset  = (max(1, (int) ($args['page'] ?? 1)) - 1) * $perPage;
        $sqlWhere = implode(' AND ', $where);

        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE {$sqlWhere}", ...$params));
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t} WHERE {$sqlWhere} ORDER BY {$order} LIMIT %d OFFSET %d",
            ...array_merge($params, [$perPage, $offset])
        ), ARRAY_A);
        return ['items' => array_map([self::class, 'decode'], $rows ?: []), 'total' => $total];
    }

    public static function setInboxStatus(int $id, string $status, ?int $queueId = null): void
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_ITEMS);
        $wpdb->update($t, ['inbox_status' => $status, 'used_queue_id' => $queueId], ['id' => $id], ['%s', '%d'], ['%d']);
    }

    public static function unprocessed(string $show, int $limit = 200): array
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_ITEMS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t} WHERE show_slug = %s AND processed = 0 ORDER BY published_at ASC LIMIT %d",
            $show, $limit
        ), ARRAY_A);
        return array_map([self::class, 'decode'], $rows ?: []);
    }

    public static function markProcessed(array $ids, ?int $clusterId = null): void
    {
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return;
        }
        $t  = Schema::table(Schema::TABLE_ITEMS);
        $in = implode(',', $ids);
        $wpdb->query($wpdb->prepare("UPDATE {$t} SET processed = 1, cluster_id = %d WHERE id IN ({$in})", (int) $clusterId));
    }

    public static function recordRun(array $row): int
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_RUNS);
        $wpdb->insert($t, [
            'show_slug'  => (string) ($row['show'] ?? ''),
            'kind'       => (string) ($row['kind'] ?? 'ingest'),
            'started_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function finishRun(int $id, array $counts): void
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_RUNS);
        $wpdb->update($t, [
            'finished_at' => gmdate('Y-m-d H:i:s'),
            'ingested'    => (int) ($counts['ingested'] ?? 0),
            'clusters'    => (int) ($counts['clusters'] ?? 0),
            'published'   => (int) ($counts['published'] ?? 0),
            'queued'      => (int) ($counts['queued'] ?? 0),
            'dropped'     => (int) ($counts['dropped'] ?? 0),
            'errors'      => empty($counts['errors']) ? null : implode("\n", (array) $counts['errors']),
        ], ['id' => $id]);
    }

    public static function runs(int $limit = 40): array
    {
        global $wpdb;
        $t = Schema::table(Schema::TABLE_RUNS);
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A) ?: [];
    }

    private static function decode(array $row): array
    {
        $row['id']      = (int) $row['id'];
        $row['score']   = (int) $row['score'];
        $row['title']   = html_entity_decode((string) $row['title'], ENT_QUOTES, 'UTF-8');
        $row['excerpt'] = html_entity_decode((string) $row['excerpt'], ENT_QUOTES, 'UTF-8');
        return $row;
    }
}
