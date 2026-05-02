<?php

namespace BigBrotherJunkies\Data\Comments;

/**
 * Handles media uploads for comments
 * Supports JPG/PNG/GIF, 2MB max, 1 per comment
 * Compresses and converts to WebP for performance
 */
class MediaUploader
{
    /**
     * Upload directory relative to wp-content/uploads
     */
    public const UPLOAD_DIR = 'bbj-comments';

    /**
     * Max file size in bytes (2MB)
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * WebP compression quality (0-100)
     * 80 gives good balance of quality vs file size
     */
    public const WEBP_QUALITY = 80;

    /**
     * Allowed MIME types
     */
    public const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * Upload a media file
     *
     * @param array $file $_FILES array element
     * @param int $userId User uploading the file
     * @return array|WP_Error Upload result or error
     */
    public static function upload(array $file, int $userId): array|\WP_Error
    {
        // Validate file
        $validation = self::validateFile($file);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get upload directory
        $uploadDir = self::getUploadDir();
        if (is_wp_error($uploadDir)) {
            return $uploadDir;
        }

        $originalExtension = self::ALLOWED_TYPES[$file['type']];
        $isGif = $originalExtension === 'gif';

        // GIFs stay as GIF (to preserve animation), others convert to WebP
        $finalExtension = $isGif ? 'gif' : 'webp';
        $filename = self::generateFilename($userId, $finalExtension);
        $filepath = $uploadDir['path'] . '/' . $filename;
        $fileurl = $uploadDir['url'] . '/' . $filename;

        if ($isGif) {
            // Move GIF as-is (preserve animation)
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return new \WP_Error('upload_failed', 'Failed to save uploaded file');
            }
            $finalSize = filesize($filepath);
            $mimeType = 'image/gif';
        } else {
            // Convert JPG/PNG to compressed WebP
            $convertResult = self::convertToWebP($file['tmp_name'], $filepath, $file['type']);
            if (is_wp_error($convertResult)) {
                return $convertResult;
            }
            $finalSize = filesize($filepath);
            $mimeType = 'image/webp';
        }

        // Get image dimensions
        $dimensions = @getimagesize($filepath);
        $width = $dimensions ? $dimensions[0] : null;
        $height = $dimensions ? $dimensions[1] : null;

        // Insert into database
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);

        $result = $wpdb->insert($table, [
            'user_id' => $userId,
            'media_type' => $isGif ? 'gif' : 'image',
            'file_name' => $filename,
            'file_path' => $filepath,
            'file_url' => $fileurl,
            'file_size' => $finalSize,
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
            'storage_type' => 'local',
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s']);

        if (!$result) {
            // Clean up file if DB insert failed
            @unlink($filepath);
            return new \WP_Error('db_error', 'Failed to save media record');
        }

        $mediaId = $wpdb->insert_id;

        return [
            'id' => $mediaId,
            'url' => $fileurl,
            'filename' => $filename,
            'type' => $isGif ? 'gif' : 'image',
            'size' => $finalSize,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Convert image to WebP format with compression
     *
     * @param string $sourcePath Path to source image
     * @param string $destPath Path for WebP output
     * @param string $mimeType Original MIME type
     * @return true|WP_Error
     */
    private static function convertToWebP(string $sourcePath, string $destPath, string $mimeType): bool|\WP_Error
    {
        // Check if WebP is supported
        if (!function_exists('imagewebp')) {
            return new \WP_Error('webp_unsupported', 'WebP conversion is not supported on this server');
        }

        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($sourcePath);
                // Preserve transparency
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            default:
                return new \WP_Error('invalid_type', 'Cannot convert this image type');
        }

        if (!$image) {
            return new \WP_Error('image_load_failed', 'Failed to load image for conversion');
        }

        // Convert to WebP with compression
        $result = imagewebp($image, $destPath, self::WEBP_QUALITY);
        imagedestroy($image);

        if (!$result) {
            return new \WP_Error('webp_failed', 'Failed to convert image to WebP');
        }

        return true;
    }

    /**
     * Attach media to a comment
     *
     * @param int $mediaId Media ID
     * @param int $commentId Comment ID
     * @param int $userId User ID (for permission check)
     * @return bool|WP_Error
     */
    public static function attachToComment(int $mediaId, int $commentId, int $userId): bool|\WP_Error
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);

        // Verify ownership
        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d AND comment_id IS NULL",
            $mediaId,
            $userId
        ));

        if (!$media) {
            return new \WP_Error('invalid_media', 'Media not found or already attached');
        }

        // Check if comment already has media
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE comment_id = %d",
            $commentId
        ));

        if ($existing) {
            return new \WP_Error('media_exists', 'Comment already has media attached');
        }

        // Attach media to comment
        $result = $wpdb->update(
            $table,
            ['comment_id' => $commentId],
            ['id' => $mediaId],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get media for a comment
     *
     * @param int $commentId
     * @return array|null
     */
    public static function getCommentMedia(int $commentId): ?array
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);

        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE comment_id = %d",
            $commentId
        ), ARRAY_A);

        if (!$media) {
            return null;
        }

        return [
            'id' => (int) $media['id'],
            'url' => $media['file_url'],
            'type' => $media['media_type'],
            'width' => $media['width'] ? (int) $media['width'] : null,
            'height' => $media['height'] ? (int) $media['height'] : null,
            'giphy_id' => $media['giphy_id'],
        ];
    }

    /**
     * Delete media
     *
     * @param int $mediaId
     * @param int $userId For permission check
     * @return bool|WP_Error
     */
    public static function delete(int $mediaId, int $userId): bool|\WP_Error
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);

        // Get media record
        $media = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $mediaId
        ));

        if (!$media) {
            return new \WP_Error('not_found', 'Media not found');
        }

        // Check permission (owner or moderator)
        if ((int) $media->user_id !== $userId && !current_user_can('moderate_comments')) {
            return new \WP_Error('forbidden', 'You cannot delete this media');
        }

        // Delete file if local
        if ($media->storage_type === 'local' && file_exists($media->file_path)) {
            @unlink($media->file_path);
        }

        // Delete from database
        $wpdb->delete($table, ['id' => $mediaId], ['%d']);

        return true;
    }

    /**
     * Store a Giphy GIF reference
     *
     * @param string $giphyId Giphy ID
     * @param string $url Giphy URL
     * @param int $userId
     * @param int|null $width
     * @param int|null $height
     * @return array|WP_Error
     */
    public static function storeGiphy(string $giphyId, string $url, int $userId, ?int $width = null, ?int $height = null): array|\WP_Error
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);

        $result = $wpdb->insert($table, [
            'user_id' => $userId,
            'media_type' => 'giphy',
            'file_name' => $giphyId,
            'file_path' => '',
            'file_url' => $url,
            'file_size' => 0,
            'mime_type' => 'image/gif',
            'width' => $width,
            'height' => $height,
            'giphy_id' => $giphyId,
            'storage_type' => 'external',
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s']);

        if (!$result) {
            return new \WP_Error('db_error', 'Failed to save Giphy record');
        }

        return [
            'id' => $wpdb->insert_id,
            'url' => $url,
            'type' => 'giphy',
            'giphy_id' => $giphyId,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Clean up orphaned media (uploaded but never attached to a comment)
     * Should be run via cron
     *
     * @param int $olderThanHours Delete media older than X hours
     * @return int Number of items deleted
     */
    public static function cleanupOrphans(int $olderThanHours = 24): int
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);

        // Get orphaned media
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$olderThanHours} hours"));

        $orphans = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE comment_id IS NULL AND created_at < %s",
            $cutoff
        ));

        $deleted = 0;
        foreach ($orphans as $media) {
            // Delete file if local
            if ($media->storage_type === 'local' && file_exists($media->file_path)) {
                @unlink($media->file_path);
            }

            // Delete from database
            $wpdb->delete($table, ['id' => $media->id], ['%d']);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Validate uploaded file
     *
     * @param array &$file $_FILES element (passed by reference to update type)
     * @return true|WP_Error
     */
    private static function validateFile(array &$file): bool|\WP_Error
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            ];
            $message = $errors[$file['error']] ?? 'Unknown upload error';
            return new \WP_Error('upload_error', $message);
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return new \WP_Error('file_too_large', 'File size exceeds 2MB limit');
        }

        // Verify MIME type (don't trust the browser's Content-Type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($actualType, self::ALLOWED_TYPES)) {
            return new \WP_Error('invalid_type', 'Only JPG, PNG and GIF files are allowed');
        }

        // Update file type to actual detected type (passed by reference)
        $file['type'] = $actualType;

        return true;
    }

    /**
     * Get or create upload directory
     *
     * @return array|WP_Error
     */
    private static function getUploadDir(): array|\WP_Error
    {
        $uploadDir = wp_upload_dir();

        if ($uploadDir['error']) {
            return new \WP_Error('upload_dir_error', $uploadDir['error']);
        }

        $path = $uploadDir['basedir'] . '/' . self::UPLOAD_DIR;
        $url = $uploadDir['baseurl'] . '/' . self::UPLOAD_DIR;

        // Add year/month subdirectory
        $subdir = date('Y/m');
        $path .= '/' . $subdir;
        $url .= '/' . $subdir;

        // Create directory if it doesn't exist
        if (!file_exists($path)) {
            if (!wp_mkdir_p($path)) {
                return new \WP_Error('mkdir_failed', 'Failed to create upload directory');
            }

            // Add index.php for security
            file_put_contents($path . '/index.php', '<?php // Silence is golden');
        }

        return [
            'path' => $path,
            'url' => $url,
        ];
    }

    /**
     * Generate a unique filename
     *
     * @param int $userId
     * @param string $extension
     * @return string
     */
    private static function generateFilename(int $userId, string $extension): string
    {
        $hash = wp_generate_password(12, false);
        $timestamp = time();
        return "comment_{$userId}_{$timestamp}_{$hash}.{$extension}";
    }
}
