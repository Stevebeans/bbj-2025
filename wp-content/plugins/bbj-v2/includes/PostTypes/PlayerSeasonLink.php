<?php

add_action( 'init', 'register_season_player_link' );
function register_season_player_link() {
	$labels = [
		'name'                     => esc_html__( 'Player Season Links', 'your-textdomain' ),
		'singular_name'            => esc_html__( 'Player Season Link', 'your-textdomain' ),
		'add_new'                  => esc_html__( 'Add New', 'your-textdomain' ),
		'add_new_item'             => esc_html__( 'Add New Player Season Link', 'your-textdomain' ),
		'edit_item'                => esc_html__( 'Edit Player Season Link', 'your-textdomain' ),
		'new_item'                 => esc_html__( 'New Player Season Link', 'your-textdomain' ),
		'view_item'                => esc_html__( 'View Player Season Link', 'your-textdomain' ),
		'view_items'               => esc_html__( 'View Player Season Links', 'your-textdomain' ),
		'search_items'             => esc_html__( 'Search Player Season Links', 'your-textdomain' ),
		'not_found'                => esc_html__( 'No player season links found.', 'your-textdomain' ),
		'not_found_in_trash'       => esc_html__( 'No player season links found in Trash.', 'your-textdomain' ),
		'parent_item_colon'        => esc_html__( 'Parent Player Season Link:', 'your-textdomain' ),
		'all_items'                => esc_html__( 'All Player Season Links', 'your-textdomain' ),
		'archives'                 => esc_html__( 'Player Season Link Archives', 'your-textdomain' ),
		'attributes'               => esc_html__( 'Player Season Link Attributes', 'your-textdomain' ),
		'insert_into_item'         => esc_html__( 'Insert into player season link', 'your-textdomain' ),
		'uploaded_to_this_item'    => esc_html__( 'Uploaded to this player season link', 'your-textdomain' ),
		'featured_image'           => esc_html__( 'Featured image', 'your-textdomain' ),
		'set_featured_image'       => esc_html__( 'Set featured image', 'your-textdomain' ),
		'remove_featured_image'    => esc_html__( 'Remove featured image', 'your-textdomain' ),
		'use_featured_image'       => esc_html__( 'Use as featured image', 'your-textdomain' ),
		'menu_name'                => esc_html__( 'Player Season Links', 'your-textdomain' ),
		'filter_items_list'        => esc_html__( 'Filter player season links list', 'your-textdomain' ),
		'filter_by_date'           => esc_html__( '', 'your-textdomain' ),
		'items_list_navigation'    => esc_html__( 'Player Season Links list navigation', 'your-textdomain' ),
		'items_list'               => esc_html__( 'Player Season Links list', 'your-textdomain' ),
		'item_published'           => esc_html__( 'Player Season Link published.', 'your-textdomain' ),
		'item_published_privately' => esc_html__( 'Player Season Link published privately.', 'your-textdomain' ),
		'item_reverted_to_draft'   => esc_html__( 'Player Season Link reverted to draft.', 'your-textdomain' ),
		'item_scheduled'           => esc_html__( 'Player Season Link scheduled.', 'your-textdomain' ),
		'item_updated'             => esc_html__( 'Player Season Link updated.', 'your-textdomain' ),
	];
	$args = [
		'label'               => esc_html__( 'Player Season Links', 'your-textdomain' ),
		'labels'              => $labels,
		'description'         => '',
		'public'              => true,
		'hierarchical'        => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'show_in_rest'        => true,
		'query_var'           => true,
		'can_export'          => true,
		'delete_with_user'    => true,
		'has_archive'         => true,
		'rest_base'           => '',
		'show_in_menu'        => true,
		'menu_position'       => 101,
		'menu_icon'           => 'dashicons-admin-generic',
		'capability_type'     => 'post',
		'supports'            => ['title', 'editor'],
		'taxonomies'          => [],
		'rewrite'             => [
			'with_front' => false,
		],
	];

	register_post_type( 'player-season-link', $args );
}


// Register custom fields for Player Season Link

add_filter( 'rwmb_meta_boxes', 'bbj_player_season_link_meta_boxes' );
function bbj_player_season_link_meta_boxes( $meta_boxes ) { 
  $prefix = 'bbj_';

  $meta_boxes[] = [
    'title'      => esc_html__( 'Player Season Link Info', 'your-textdomain' ),
    'id'         => 'player-season-link-info',
    'post_types' => ['player-season-link'],
    "storage_type"  => "custom_table",
    "table"         => "wp_bbj_v2_player_season",
    'fields'     => [
      [
        'name' => esc_html__( 'Season', 'your-textdomain' ),
        'id'   => $prefix . 'season',
        'type' => 'post',
        'post_type' => 'bigbrother-seasons',
        'field_type' => 'select_advanced',
        'required'  => true,
      ],
      [
        'name' => esc_html__( 'Player', 'your-textdomain' ),
        'id'   => $prefix . 'player',
        'type' => 'post',
        'post_type' => 'bigbrother-players',
        'field_type' => 'select_advanced',
        'required'  => true,
      ],
      // evicted date
      [
        'name' => esc_html__( 'Evicted Date', 'your-textdomain' ),
        'id'   => $prefix . 'evicted_date',
        'type' => 'date',
        'required'  => false,
      ],
      // total hoh 
      [
        'name' => esc_html__( 'Total HOH', 'your-textdomain' ),
        'id'   => $prefix . 'total_hoh',
        'type' => 'number',
        'required'  => false,
      ],
      // total pov
      [
        'name' => esc_html__( 'Total POV', 'your-textdomain' ),
        'id'   => $prefix . 'total_pov',
        'type' => 'number',
        'required'  => false,
      ],
      // total times nominated
      [
        'name' => esc_html__( 'Total Times Nominated', 'your-textdomain' ),
        'id'   => $prefix . 'total_nom',
        'type' => 'number',
        'required'  => false,
      ],
      // votes received
      [
        'name' => esc_html__( 'Votes Received', 'your-textdomain' ),
        'id'   => $prefix . 'votes_received',
        'type' => 'number',
        'required'  => false,
      ],
      // veto played 
      [
        'name' => esc_html__( 'Veto Played', 'your-textdomain' ),
        'id'   => $prefix . 'veto_played',
        'type' => 'number',
        'required'  => false,
      ],
       // Current pointers (many-to-many)
            [
                'name'      => __( 'Current HOHs',            'bbj-tools' ),
                'id'        => $prefix . 'current_hoh',
                'type'      => 'post',
                'post_type' => [ 'bigbrother-players' ],
                'multiple'  => true,
            ],
            [
                'name'      => __( 'Current POVs',            'bbj-tools' ),
                'id'        => $prefix . 'current_pov',
                'type'      => 'post',
                'post_type' => [ 'bigbrother-players' ],
                'multiple'  => true,
            ],
     ],
     
    ];

  return $meta_boxes;
}