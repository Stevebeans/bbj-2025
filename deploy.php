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

$output = [];
$return = 0;

// Check if valid git repo exists
exec('git status 2>&1', $check, $git_status);

if ($git_status !== 0) {
    // Not a valid git repo - remove any .git folder and initialize fresh
    exec('rm -rf .git 2>&1', $output, $return);
    exec('git init 2>&1', $output, $return);
    exec('git remote add origin https://github.com/Stevebeans/bbj-2025.git 2>&1', $output, $return);
    $output[] = "Initialized new git repo";
}

// Fetch and reset to origin/staging
exec('git fetch origin staging 2>&1', $output, $return);
exec('git reset --hard origin/staging 2>&1', $output, $return);

echo json_encode([
    'success' => $return === 0,
    'output' => $output,
    'time' => date('Y-m-d H:i:s')
]);
