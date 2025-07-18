<?php
add_filter( 'rwmb_meta_boxes', 'relationship_table' );

function relationship_table( $meta_boxes ) {
    $prefix = '';

    $meta_boxes[] = [
        'title'        => __( 'Player - Season Relationship', 'your-text-domain' ),
        'post_types'   => ['bigbrother-seasons'],
        'priority'     => 'low',
        'storage_type' => 'custom_table',
        'table'        => 'wp_bbj_play_season_rel',
        'fields'       => [
            [
                'name' => __( 'Player', 'your-text-domain' ),
                'id'   => $prefix . 'player',
                'type' => 'select',
            ],
            [
                'name' => __( 'Season', 'your-text-domain' ),
                'id'   => $prefix . 'season',
                'type' => 'select',
            ],
            [
                'name'    => __( 'Winner', 'your-text-domain' ),
                'id'      => $prefix . 'winner',
                'type'    => 'checkbox',
                'columns' => 1,
            ],
            [
                'name'    => __( 'Runner Up', 'your-text-domain' ),
                'id'      => $prefix . 'runner_up',
                'type'    => 'checkbox',
                'columns' => 1,
            ],
            [
                'name'    => __( 'AFP', 'your-text-domain' ),
                'id'      => $prefix . 'afp',
                'type'    => 'checkbox',
                'columns' => 1,
            ],
            [
                'name'    => __( 'Evicted', 'your-text-domain' ),
                'id'      => $prefix . 'evicted',
                'type'    => 'checkbox',
                'columns' => 1,
            ],
            [
                'name'    => __( 'Jury', 'your-text-domain' ),
                'id'      => $prefix . 'jury',
                'type'    => 'checkbox',
                'columns' => 1,
            ],
        ],
    ];

    return $meta_boxes;
}