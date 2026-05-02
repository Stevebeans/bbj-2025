<?php

namespace BigBrotherJunkies\Data\Comments;

/**
 * Handles user avatar uploads
 * - Crops to square (center crop)
 * - Resizes to 200x200
 * - Converts to WebP for performance
 * - Stores in dedicated avatars directory
 */
class AvatarUploader
{
    /**
     * Upload directory relative to wp-content/uploads
     */
    public const UPLOAD_DIR = 'bbj-avatars';

    /**
     * Avatar dimensions (square)
     */
    public const AVATAR_SIZE = 200;

    /**
     * Max file size in bytes (2MB)
     */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * WebP compression quality (0-100)
     */
    public const WEBP_QUALITY = 85;

    /**
     * Allowed MIME types
     */
    public const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Upload and process an avatar
     *
     * @param array $file $_FILES array element
     * @param int $userId User uploading the avatar
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

        // Generate filename (user ID based for easy replacement)
        $filename = "avatar_{$userId}.webp";
        $filepath = $uploadDir['path'] . '/' . $filename;
        $fileurl = $uploadDir['url'] . '/' . $filename;

        // Delete existing avatar file if exists
        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        // Process image: crop to square, resize, convert to WebP
        $processResult = self::processImage($file['tmp_name'], $filepath, $file['type']);
        if (is_wp_error($processResult)) {
            return $processResult;
        }

        $finalSize = filesize($filepath);

        // Insert or update database record
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_AVATARS);

        // Check if user already has an avatar record
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE user_id = %d",
            $userId
        ));

        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table,
                [
                    'file_path' => $filepath,
                    'file_url' => $fileurl,
                    'original_filename' => $file['name'],
                    'file_size' => $finalSize,
                    'uploaded_at' => current_time('mysql'),
                ],
                ['user_id' => $userId],
                ['%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new record
            $result = $wpdb->insert($table, [
                'user_id' => $userId,
                'file_path' => $filepath,
                'file_url' => $fileurl,
                'original_filename' => $file['name'],
                'file_size' => $finalSize,
            ], ['%d', '%s', '%s', '%s', '%d']);
        }

        if ($result === false) {
            @unlink($filepath);
            return new \WP_Error('db_error', 'Failed to save avatar record');
        }

        // Clear any cached avatar URLs
        clean_user_cache($userId);

        return [
            'url' => $fileurl,
            'size' => $finalSize,
        ];
    }

    /**
     * Process image: crop to square center, resize, convert to WebP
     *
     * @param string $sourcePath Path to source image
     * @param string $destPath Path for WebP output
     * @param string $mimeType Original MIME type
     * @return true|WP_Error
     */
    private static function processImage(string $sourcePath, string $destPath, string $mimeType): bool|\WP_Error
    {
        // Check if required functions exist
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            return new \WP_Error('gd_unsupported', 'GD library with WebP support is required');
        }

        // Load source image
        $source = self::loadImage($sourcePath, $mimeType);
        if (is_wp_error($source)) {
            return $source;
        }

        // Get original dimensions
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        // Calculate crop dimensions (square from center)
        $cropSize = min($origWidth, $origHeight);
        $cropX = (int) (($origWidth - $cropSize) / 2);
        $cropY = (int) (($origHeight - $cropSize) / 2);

        // Create destination image
        $dest = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        if (!$dest) {
            imagedestroy($source);
            return new \WP_Error('image_create_failed', 'Failed to create destination image');
        }

        // Preserve transparency for PNG/WebP
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefill($dest, 0, 0, $transparent);

        // Crop and resize in one operation
        $resizeResult = imagecopyresampled(
            $dest,
            $source,
            0, 0,                           // Destination X, Y
            $cropX, $cropY,                 // Source X, Y (crop position)
            self::AVATAR_SIZE, self::AVATAR_SIZE, // Destination width, height
            $cropSize, $cropSize            // Source width, height (crop size)
        );

        imagedestroy($source);

        if (!$resizeResult) {
            imagedestroy($dest);
            return new \WP_Error('resize_failed', 'Failed to resize image');
        }

        // Save as WebP
        $saveResult = imagewebp($dest, $destPath, self::WEBP_QUALITY);
        imagedestroy($dest);

        if (!$saveResult) {
            return new \WP_Error('webp_failed', 'Failed to save WebP image');
        }

        return true;
    }

    /**
     * Load an image from file based on MIME type
     *
     * @param string $path File path
     * @param string $mimeType MIME type
     * @return \GdImage|WP_Error
     */
    private static function loadImage(string $path, string $mimeType): \GdImage|\WP_Error
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (!$image) {
            return new \WP_Error('image_load_failed', 'Failed to load image');
        }

        return $image;
    }

    /**
     * Get avatar URL for a user
     * Falls back to Gravatar if no custom avatar
     *
     * @param int $userId
     * @param int $size Size in pixels (will be scaled from 200px)
     * @return string
     */
    public static function getAvatarUrl(int $userId, int $size = 64): string
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_AVATARS);

        $avatar = $wpdb->get_row($wpdb->prepare(
            "SELECT file_url, uploaded_at FROM {$table} WHERE user_id = %d",
            $userId
        ));

        if ($avatar && $avatar->file_url) {
            // Add cache-buster timestamp
            $timestamp = strtotime($avatar->uploaded_at);
            return $avatar->file_url . '?v=' . $timestamp;
        }

        // Fallback to Gravatar
        $user = get_user_by('ID', $userId);
        if ($user) {
            return get_avatar_url($user->user_email, ['size' => $size, 'default' => 'mp']);
        }

        // Ultimate fallback
        return get_avatar_url('', ['size' => $size, 'default' => 'mp', 'force_default' => true]);
    }

    /**
     * Check if user has a custom avatar
     *
     * @param int $userId
     * @return bool
     */
    public static function hasCustomAvatar(int $userId): bool
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_AVATARS);

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE user_id = %d",
            $userId
        ));
    }

    /**
     * Delete user's avatar
     *
     * @param int $userId
     * @return bool|WP_Error
     */
    public static function delete(int $userId): bool|\WP_Error
    {
        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_AVATARS);

        // Get current avatar
        $avatar = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $userId
        ));

        if (!$avatar) {
            return new \WP_Error('not_found', 'No avatar found for user');
        }

        // Delete file
        if (file_exists($avatar->file_path)) {
            @unlink($avatar->file_path);
        }

        // Delete database record
        $wpdb->delete($table, ['user_id' => $userId], ['%d']);

        // Clear cache
        clean_user_cache($userId);

        return true;
    }

    /**
     * Validate uploaded file
     *
     * @param array &$file $_FILES element
     * @return true|WP_Error
     */
    private static function validateFile(array &$file): bool|\WP_Error
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
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

        // Verify MIME type (don't trust browser)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($actualType, self::ALLOWED_TYPES)) {
            return new \WP_Error('invalid_type', 'Only JPG, PNG, GIF, and WebP images are allowed');
        }

        // Update to actual type
        $file['type'] = $actualType;

        // Validate it's actually an image with valid dimensions
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo || $imageInfo[0] < 50 || $imageInfo[1] < 50) {
            return new \WP_Error('invalid_image', 'Invalid image or image too small (minimum 50x50)');
        }

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

        // Create directory if it doesn't exist
        if (!file_exists($path)) {
            if (!wp_mkdir_p($path)) {
                return new \WP_Error('mkdir_failed', 'Failed to create avatar directory');
            }

            // Add index.php for security
            file_put_contents($path . '/index.php', '<?php // Silence is golden');

            // Add .htaccess to prevent PHP execution
            file_put_contents($path . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>");
        }

        return [
            'path' => $path,
            'url' => $url,
        ];
    }
}
