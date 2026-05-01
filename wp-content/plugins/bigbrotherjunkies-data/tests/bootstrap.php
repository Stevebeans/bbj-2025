<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
require_once __DIR__ . '/../vendor/autoload.php';

// PSR-4 only autoloads classes; explicit require makes the global-namespace
// procedural functions (bbjd_cookie_or_jwt_permission etc.) available to tests.
require_once __DIR__ . '/../src/Auth/CookieOrJwtAuth.php';
