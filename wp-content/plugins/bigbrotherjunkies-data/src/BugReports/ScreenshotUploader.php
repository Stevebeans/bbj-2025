<?php

namespace BigBrotherJunkies\Data\BugReports;

/**
 * Handles screenshot uploads for bug reports
 */
class ScreenshotUploader
{
    private const UPLOAD_DIR = 'bbj-bug-reports';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Upload a screenshot file
     *
     * @return array{success: bool, url?: string, error?: string}
     */
    public static function upload(array $file, int $userId): array
    {
        // Validate file
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error'];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File too large. Maximum 5MB allowed'];
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!isset(self::ALLOWED_TYPES[$mimeType])) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, WebP'];
        }

        $ext = self::ALLOWED_TYPES[$mimeType];

        // Build upload path: wp-content/uploads/bbj-bug-reports/YYYY/MM/
        $uploadDir = wp_upload_dir();
        $year = date('Y');
        $month = date('m');
        $targetDir = $uploadDir['basedir'] . '/' . self::UPLOAD_DIR . '/' . $year . '/' . $month;

        if (!file_exists($targetDir)) {
            wp_mkdir_p($targetDir);
        }

        // Generate filename
        $filename = sprintf('bugreport_%d_%d.%s', $userId, time(), $ext);
        $targetPath = $targetDir . '/' . $filename;

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }

        // Build URL
        $url = $uploadDir['baseurl'] . '/' . self::UPLOAD_DIR . '/' . $year . '/' . $month . '/' . $filename;

        return [
            'success' => true,
            'url' => $url,
        ];
    }
}
