<?php
add_filter( 'rwmb_meta_boxes', 'bbj_season_stats' );

function bbj_season_stats( $meta_boxes ) {
    $prefix = '';

    $meta_boxes[] = [
        'title'        => __( 'Season Stats', 'your-text-domain' ),
        'id'           => 'player-stats',
        'post_types'   => ['bigbrother-players'],
        'storage_type' => 'custom_table',
        'table'        => 'wp_bbj_player_season_stats',
        'fields'       => [
            [
                'name'    => __( 'HoH Wins', 'your-text-domain' ),
                'id'      => $prefix . 'hohwins',
                'type'    => 'number',
                'columns' => 1,
            ],
            [
                'name'    => __( 'PoV Wins', 'your-text-domain' ),
                'id'      => $prefix . 'povwins',
                'type'    => 'number',
                'columns' => 1,
            ],
            [
                'name'    => __( 'Misc wins', 'your-text-domain' ),
                'id'      => $prefix . 'miscwins',
                'type'    => 'number',
                'columns' => 1,
            ],
            [
                'name'    => __( 'Saved', 'your-text-domain' ),
                'id'      => $prefix . 'saved_block',
                'type'    => 'number',
                'columns' => 1,
            ],
            [
                'name'    => __( 'Nominated', 'your-text-domain' ),
                'id'      => $prefix . 'nominated',
                'type'    => 'number',
                'columns' => 1,
            ],
            [
                'name' => __( 'place', 'your-text-domain' ),
                'id'   => $prefix . 'place_finished',
                'type' => 'text',
            ],
            [
                'name'    => __( 'Evicted Date', 'your-text-domain' ),
                'id'      => $prefix . 'evicted_date',
                'type'    => 'date',
                'columns' => 4,
            ],
        ],
    ];

    return $meta_boxes;
}