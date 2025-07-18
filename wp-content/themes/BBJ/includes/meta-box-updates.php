<?php
add_filter( 'rwmb_meta_boxes', 'bbj_feed_updates' );

function bbj_feed_updates( $meta_boxes ) {
    $prefix = '';

    $meta_boxes[] = [
        'title'        => __( 'Feed Updates', 'your-text-domain' ),
        'id'           => 'feed-updates',
        'post_types'   => ['feed_update'],
        'storage_type' => 'custom_table',
        'table'        => 'wp_bbj_feedupdates',
        'fields'       => [
            [
                'name' => __( 'Time', 'your-text-domain' ),
                'id'   => $prefix . 'feed_time',
                'type' => 'datetime',
            ],
            [
                'name' => __( 'Wysiwyg', 'your-text-domain' ),
                'id'   => $prefix . 'feed_update',
                'type' => 'wysiwyg',
            ],
        ],
    ];

    return $meta_boxes;
}