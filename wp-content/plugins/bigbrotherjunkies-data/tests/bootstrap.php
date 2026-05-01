<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
require_once __DIR__ . '/../vendor/autoload.php';

// PSR-4 only autoloads classes; explicit require makes the global-namespace
// procedural functions (bbjd_cookie_or_jwt_permission etc.) available to tests.
require_once __DIR__ . '/../src/Auth/CookieOrJwtAuth.php';

// Minimal WP class stubs for unit tests (no WP loaded in test environment).
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private int $status;
        public function __construct($data = null, int $status = 200) {
            $this->data   = $data;
            $this->status = $status;
        }
        public function get_data() { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}
