<?php
/**
 * Git deployment webhook
 * Triggers git pull when called with correct key
 */

// Prevent timeout
set_time_limit(300);

// Secret key for security
$secret = isset($_GET['key']) ? $_GET['key'] : '';
$expected_key = 'bbj_deploy_2025';

if ($secret !== $expected_key) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

// Change to the web root
chdir(__DIR__);

// Run git commands
$output = [];
$return = 0;

// Fetch and reset to origin
exec('git fetch origin 2>&1', $output, $return);
exec('git reset --hard origin/staging 2>&1', $output, $return);

echo json_encode([
    'success' => $return === 0,
    'output' => $output,
    'time' => date('Y-m-d H:i:s')
]);
