<?php
/**
 * Cache clearing endpoint for deployment
 * Called by GitHub Actions after deploy
 */

// Secret key to prevent unauthorized access
$secret = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'bbj_cache_clear_2025'; // We'll move this to a secret later

if ($secret !== $expected_key) {
    http_response_code(403);
    die('Unauthorized');
}

$results = [];

// Clear Breeze cache
if (function_exists('breeze_clear_all_cache')) {
    do_action('breeze_clear_all_cache');
    $results[] = 'Breeze cache cleared';
} elseif (is_dir(__DIR__ . '/wp-content/cache/breeze')) {
    array_map('unlink', glob(__DIR__ . '/wp-content/cache/breeze/*'));
    $results[] = 'Breeze cache directory cleared';
}

// Clear Redis/Object cache
if (function_exists('wp_cache_flush')) {
    require_once(__DIR__ . '/wp-load.php');
    wp_cache_flush();
    $results[] = 'Object cache flushed';
}

// Clear WP Super Cache if present
if (function_exists('wp_cache_clear_cache')) {
    wp_cache_clear_cache();
    $results[] = 'WP Super Cache cleared';
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'results' => $results,
    'time' => date('Y-m-d H:i:s')
]);
