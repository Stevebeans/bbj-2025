<?php 

function bbj_is_admin_user() {
    return is_user_logged_in() && current_user_can( 'administrator' );
}

add_action( 'init', function() {
    // declare your global
    global $bbj_is_admin;
    // now it's safe to call the WP functions
    $bbj_is_admin = bbj_is_admin_user();
} );