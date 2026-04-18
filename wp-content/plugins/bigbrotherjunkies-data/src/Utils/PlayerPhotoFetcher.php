<?php

namespace BigBrotherJunkies\Data\Utils;

use WP_Error;

/**
 * Fetch, process, and store player profile photos
 * Uses Google Custom Search API to find images
 */
class PlayerPhotoFetcher
{
    private const UPLOAD_DIR = 'bbj-player-photos';
    private const PHOTO_SIZE = 375;
    private const WEBP_QUALITY = 85;

    /**
     * Search Google for player photos
     *
     * @param string $playerName Player's full name
     * @param string $seasonName Season name (e.g., "Big Brother 27")
     * @return array|WP_Error Array of image results or error
     */
    public static function searchPhotos(string $playerName, string $seasonName): array|WP_Error
    {
        $apiKey = defined('GOOGLE_CUSTOM_SEARCH_API_KEY') ? GOOGLE_CUSTOM_SEARCH_API_KEY : '';
        $engineId = defined('GOOGLE_CUSTOM_SEARCH_ENGINE_ID') ? GOOGLE_CUSTOM_SEARCH_ENGINE_ID : '';

        if (empty($apiKey) || empty($engineId)) {
            return new WP_Error('missing_config', 'Google Custom Search API credentials not configured');
        }

        // Build search query
        $searchTerm = "{$playerName} {$seasonName} profile picture headshot";

        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => $apiKey,
            'cx' => $engineId,
            'q' => $searchTerm,
            'searchType' => 'image',
            'num' => 3,
            'imgSize' => 'medium',
            'safe' => 'active',
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($statusCode !== 200) {
            $message = $data['error']['message'] ?? 'Google API error';
            return new WP_Error('api_error', $message, ['status' => $statusCode]);
        }

        // Parse results
        $photos = [];
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $photos[] = [
                    'url' => $item['link'] ?? '',
                    'thumbnail' => $item['image']['thumbnailLink'] ?? $item['link'] ?? '',
                    'width' => (int) ($item['image']['width'] ?? 0),
                    'height' => (int) ($item['image']['height'] ?? 0),
                    'context' => $item['image']['contextLink'] ?? '',
                ];
            }
        }

        return $photos;
    }

    /**
     * Download an image, process it, and save as player's profile photo
     *
     * @param int $playerId The player's ID
     * @param string $imageUrl URL of the image to download
     * @return array|WP_Error Success data or error
     */
    public static function downloadAndSave(int $playerId, string $imageUrl): array|WP_Error
    {
        // Download the image
        $response = wp_remote_get($imageUrl, [
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('download_failed', 'Failed to download image: ' . $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return new WP_Error('download_failed', "Failed to download image (HTTP {$statusCode})");
        }

        $imageData = wp_remote_retrieve_body($response);
        if (empty($imageData)) {
            return new WP_Error('empty_image', 'Downloaded image is empty');
        }

        // Create temp file
        $tmpFile = wp_tempnam('player_photo_');
        file_put_contents($tmpFile, $imageData);

        // Validate image type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpFile);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedTypes)) {
            unlink($tmpFile);
            return new WP_Error('invalid_type', "Invalid image type: {$mimeType}");
        }

        // Process the image
        $processedImage = self::processImage($tmpFile, $mimeType);
        unlink($tmpFile);

        if (is_wp_error($processedImage)) {
            return $processedImage;
        }

        // Save to upload directory
        $savedFile = self::saveToUploadDir($playerId, $processedImage);

        if (is_wp_error($savedFile)) {
            return $savedFile;
        }

        // Update player's profile picture
        $updated = self::updatePlayerPhoto($playerId, $savedFile['url']);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return [
            'success' => true,
            'url' => $savedFile['url'],
            'path' => $savedFile['path'],
            'width' => self::PHOTO_SIZE,
            'height' => self::PHOTO_SIZE,
        ];
    }

    /**
     * Process image: crop to square, resize, convert to WebP
     */
    private static function processImage(string $tmpFile, string $mimeType): string|WP_Error
    {
        // Load image based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($tmpFile);
                break;
            case 'image/png':
                $source = imagecreatefrompng($tmpFile);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($tmpFile);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($tmpFile);
                break;
            default:
                return new WP_Error('unsupported_type', 'Unsupported image type');
        }

        if (!$source) {
            return new WP_Error('load_failed', 'Failed to load image');
        }

        // Get original dimensions
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        // Calculate crop dimensions (center crop to square)
        $cropSize = min($origWidth, $origHeight);
        $cropX = ($origWidth - $cropSize) / 2;
        $cropY = ($origHeight - $cropSize) / 2;

        // Create destination image
        $dest = imagecreatetruecolor(self::PHOTO_SIZE, self::PHOTO_SIZE);

        // Preserve transparency for PNG
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, self::PHOTO_SIZE, self::PHOTO_SIZE, $transparent);

        // Crop and resize
        imagecopyresampled(
            $dest,
            $source,
            0, 0,           // Destination X, Y
            (int) $cropX,   // Source X
            (int) $cropY,   // Source Y
            self::PHOTO_SIZE, self::PHOTO_SIZE, // Destination W, H
            $cropSize, $cropSize                // Source W, H
        );

        // Save as WebP to temp file
        $outputFile = wp_tempnam('player_photo_processed_') . '.webp';
        $success = imagewebp($dest, $outputFile, self::WEBP_QUALITY);

        // Clean up
        imagedestroy($source);
        imagedestroy($dest);

        if (!$success) {
            return new WP_Error('process_failed', 'Failed to process image');
        }

        return $outputFile;
    }

    /**
     * Save processed image to upload directory
     */
    private static function saveToUploadDir(int $playerId, string $processedFile): array|WP_Error
    {
        $uploadDir = wp_upload_dir();
        $targetDir = $uploadDir['basedir'] . '/' . self::UPLOAD_DIR;

        // Create directory if it doesn't exist
        if (!file_exists($targetDir)) {
            wp_mkdir_p($targetDir);

            // Create security files
            file_put_contents($targetDir . '/index.php', '<?php // Silence is golden');
            file_put_contents($targetDir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>");
        }

        // Generate filename
        $filename = "player_{$playerId}.webp";
        $targetPath = $targetDir . '/' . $filename;
        $targetUrl = $uploadDir['baseurl'] . '/' . self::UPLOAD_DIR . '/' . $filename;

        // Move file (overwrite if exists)
        if (!rename($processedFile, $targetPath)) {
            // Try copy if rename fails (cross-device)
            if (!copy($processedFile, $targetPath)) {
                unlink($processedFile);
                return new WP_Error('save_failed', 'Failed to save processed image');
            }
            unlink($processedFile);
        }

        // Add cache-busting timestamp
        $targetUrl .= '?v=' . time();

        return [
            'path' => $targetPath,
            'url' => $targetUrl,
        ];
    }

    /**
     * Update player's profile picture in database
     */
    private static function updatePlayerPhoto(int $playerId, string $photoUrl): bool|WP_Error
    {
        global $wpdb;

        // Get the player to find the post_id
        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}bbj_players WHERE id = %d",
            $playerId
        ));

        if (!$player) {
            return new WP_Error('player_not_found', 'Player not found');
        }

        // Store URL directly in custom field (since we're not using media library)
        // We'll store the URL without the cache-busting query param for consistency
        $cleanUrl = preg_replace('/\?v=\d+$/', '', $photoUrl);

        // Update the player's profile picture URL in post meta
        update_post_meta($player->post_id, 'bbj_player_photo_url', $cleanUrl);

        // Also store in bbj_players table if there's a column for it
        // For now, we'll use post meta which is more flexible

        return true;
    }

    /**
     * Get player's current photo URL
     */
    public static function getPlayerPhotoUrl(int $playerId): ?string
    {
        global $wpdb;

        $player = $wpdb->get_row($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}bbj_players WHERE id = %d",
            $playerId
        ));

        if (!$player) {
            return null;
        }

        $photoUrl = get_post_meta($player->post_id, 'bbj_player_photo_url', true);

        if ($photoUrl) {
            // Add cache-busting
            return $photoUrl . '?v=' . filemtime(self::urlToPath($photoUrl));
        }

        return null;
    }

    /**
     * Convert URL to file path
     */
    private static function urlToPath(string $url): string
    {
        $uploadDir = wp_upload_dir();
        return str_replace($uploadDir['baseurl'], $uploadDir['basedir'], $url);
    }
}
