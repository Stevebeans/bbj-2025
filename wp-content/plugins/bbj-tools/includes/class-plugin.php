<?php 

namespace BBJ\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static $instance;  

    public static function instance() {
        if ( ! self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}