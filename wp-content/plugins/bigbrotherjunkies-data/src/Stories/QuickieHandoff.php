<?php

namespace BigBrotherJunkies\Data\Stories;

use BigBrotherJunkies\Data\Database\Migrator;
use BigBrotherJunkies\Data\Database\Schema as DbSchema;
use BigBrotherJunkies\Data\Social\QueueBridge;
use BigBrotherJunkies\Data\Stories\Sources\InstagramAdapter;

/**
 * Inbox item → existing FB content queue. Media is pulled into the media
 * library ONLY here, for the one item Steve picked. The caption must carry
 * the credit handle or the insert is refused.
 */
class QuickieHandoff
{
    public const QUEUE_SOURCE = 'stories_inbox';

    public static function prefill(int $itemId): array
    {
        $item = ItemStore::get($itemId);
        if (!$item) {
            return ['success' => false, 'message' => 'Item not found.'];
        }
        return [
            'success' => true,
            'item'    => $item,
            'caption' => QuickieHandoffLogic::defaultCaption($item),
            'credit'  => QuickieHandoffLogic::creditLine($item),
        ];
    }

    /** @param array{page_id:string,page_name?:string,caption:string,scheduled_at?:string} $args */
    public static function enqueue(int $itemId, array $args): array
    {
        $item = ItemStore::get($itemId);
        if (!$item) {
            return ['success' => false, 'message' => 'Item not found.'];
        }
        $caption = trim((string) ($args['caption'] ?? ''));
        $pageId  = trim((string) ($args['page_id'] ?? ''));
        if ($pageId === '' || $caption === '') {
            return ['success' => false, 'message' => 'page_id and caption are required.'];
        }
        if (!QuickieHandoffLogic::hasCredit($caption, $item)) {
            return ['success' => false, 'message' => 'Caption must credit ' . QuickieHandoffLogic::creditHandle($item) . '.'];
        }

        $imageUrl     = null;
        $attachmentId = null;
        if (in_array($item['media_type'], ['image', 'video', 'carousel'], true) && ($item['media_url'] !== '' || $item['thumbnail_url'] !== '')) {
            $pull = self::pullMedia($item);
            if ($pull['error'] !== null && $item['source'] === 'instagram') {
                return ['success' => false, 'message' => 'Media pull failed: ' . $pull['error']];
            }
            $imageUrl     = $pull['url'];
            $attachmentId = $pull['attachment_id'];
        }

        Migrator::maybeWidenQueueSource();
        global $wpdb;
        $table = DbSchema::table(DbSchema::TABLE_CONTENT_QUEUE);
        $slot  = QueueBridge::humanizeSlot(QueueBridge::resolveSlot((string) ($args['scheduled_at'] ?? '')));
        $ok = $wpdb->insert($table, [
            'status'           => 'scheduled',
            'source'           => self::QUEUE_SOURCE,
            'content_type'     => 'facebook_post',
            'title'            => mb_substr('Inbox: ' . $item['title'], 0, 255),
            'body'             => wp_encode_emoji($caption),
            'image_url'        => $imageUrl,
            'target_page'      => $pageId,
            'target_page_name' => (string) ($args['page_name'] ?? ''),
            'scheduled_at'     => $slot,
            'source_url'       => $item['url'],
            'author_id'        => get_current_user_id(),
        ]);
        if ($ok === false) {
            return ['success' => false, 'message' => 'Queue insert failed' . ($wpdb->last_error ? ': ' . $wpdb->last_error : '')];
        }
        $queueId = (int) $wpdb->insert_id;
        ItemStore::setInboxStatus($itemId, 'used', $queueId);
        return ['success' => true, 'queue_id' => $queueId, 'attachment_id' => $attachmentId, 'scheduled_at' => $slot];
    }

    /**
     * Sideload the item's image (videos/carousels use thumbnail) into the media
     * library with credit meta. Re-fetches an expired IG CDN link once.
     * @return array{attachment_id:?int,url:?string,error:?string}
     */
    public static function pullMedia(array $item): array
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $src = $item['media_type'] === 'image' ? ($item['media_url'] ?: $item['thumbnail_url']) : ($item['thumbnail_url'] ?: $item['media_url']);
        $tmp = download_url($src, 30);
        if (is_wp_error($tmp) && $item['source'] === 'instagram') {
            $fresh = InstagramAdapter::mediaFor($item['source_key'], $item['url']);
            if ($fresh['error'] === null) {
                $src = $item['media_type'] === 'image' ? ($fresh['media_url'] ?: $fresh['thumbnail_url']) : ($fresh['thumbnail_url'] ?: $fresh['media_url']);
                $tmp = download_url((string) $src, 30);
            }
        }
        if (is_wp_error($tmp)) {
            return ['attachment_id' => null, 'url' => null, 'error' => $tmp->get_error_message()];
        }
        $ext  = pathinfo(parse_url((string) $src, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg';
        $name = 'stories-' . sanitize_title($item['source_key']) . '-' . substr(md5($item['url']), 0, 8) . '.' . $ext;
        $id   = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], 0, 'Credit: ' . QuickieHandoffLogic::creditHandle($item));
        if (is_wp_error($id)) {
            @unlink($tmp);
            return ['attachment_id' => null, 'url' => null, 'error' => $id->get_error_message()];
        }
        update_post_meta((int) $id, '_story_credit_handle', QuickieHandoffLogic::creditHandle($item));
        update_post_meta((int) $id, '_story_permalink', $item['url']);
        update_post_meta((int) $id, '_story_source', $item['source']);
        return ['attachment_id' => (int) $id, 'url' => wp_get_attachment_url((int) $id) ?: null, 'error' => null];
    }
}
