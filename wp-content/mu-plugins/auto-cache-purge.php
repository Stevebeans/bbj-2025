<?php
/**
 * Plugin Name: Auto Cache Purge (Breeze + Cloudways)
 * Description: Purges Breeze/Varnish and Cloudways Dynamic Cache on save.
 */

// 1) Hook into *publish* of your CPT only:
add_action( 'publish_live-feed-updates', 'sbj_purge_all_caches', 20, 2 );

function sbj_purge_all_caches( $post_id, $post ) {
    // 2) Just in case
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    // 3) Breeze (file + Varnish)
    if ( function_exists( 'breeze_flush_cache' ) ) {
        breeze_flush_cache();
        error_log( "[SBJ] Breeze flushed for post {$post_id}" );
    }

    // 4) Cloudways Dynamic Cache
    $app_id  = '4998725';                 // from your URL
    $email   = 'stevebeans@gmail.com';      // your Cloudways login email
    $api_key = 'JzCqGWWNOvWk=JYZ1Rk12IGxzeFInd';     // from Account → API

    $endpoint = "https://api.cloudways.com/api/v1/applications/{$app_id}/purge_all_cache";
    $args = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( "{$email}:{$api_key}" ),
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 20,
    ];

    // 5) Send the request *and* capture it:
    $response = wp_remote_post( $endpoint, $args );

    if ( is_wp_error( $response ) ) {
        error_log( "[SBJ] Cloudways purge ERROR: " . $response->get_error_message() );
    } else {
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        error_log( "[SBJ] Cloudways purge RESP: HTTP {$code} – {$body}" );
    }
}
