<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
require_once __DIR__ . '/../vendor/autoload.php';

// Load files that contain procedural functions (not autoloaded by PSR-4).
require_once __DIR__ . '/../src/Auth/CookieOrJwtAuth.php';
