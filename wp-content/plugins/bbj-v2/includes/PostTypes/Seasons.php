<?php 

add_action("init", "register_seasons");
function register_seasons()
{
  $labels = [
    "name" => esc_html__("BB Seasons", "your-textdomain"),
    "singular_name" => esc_html__("BB Season", "your-textdomain"),
    "add_new" => esc_html__("Add New", "your-textdomain"),
    "add_new_item" => esc_html__("Add new bb season", "your-textdomain"),
    "edit_item" => esc_html__("Edit BB Season", "your-textdomain"),
    "new_item" => esc_html__("New BB Season", "your-textdomain"),
    "view_item" => esc_html__("View BB Season", "your-textdomain"),
    "view_items" => esc_html__("View BB Seasons", "your-textdomain"),
    "search_items" => esc_html__("Search BB Seasons", "your-textdomain"),
    "not_found" => esc_html__("No bb seasons found", "your-textdomain"),
    "not_found_in_trash" => esc_html__("No bb seasons found in Trash", "your-textdomain"),
    "parent_item_colon" => esc_html__("Parent BB Season:", "your-textdomain"),
    "all_items" => esc_html__("All BB Seasons", "your-textdomain"),
    "archives" => esc_html__("BB Season Archives", "your-textdomain"),
    "attributes" => esc_html__("BB Season Attributes", "your-textdomain"),
    "insert_into_item" => esc_html__("Insert into bb season", "your-textdomain"),
    "uploaded_to_this_item" => esc_html__("Uploaded to this bb season", "your-textdomain"),
    "featured_image" => esc_html__("Featured image", "your-textdomain"),
    "set_featured_image" => esc_html__("Set featured image", "your-textdomain"),
    "remove_featured_image" => esc_html__("Remove featured image", "your-textdomain"),
    "use_featured_image" => esc_html__("Use as featured image", "your-textdomain"),
    "menu_name" => esc_html__("BB Seasons", "your-textdomain"),
    "filter_items_list" => esc_html__("Filter bb seasons list", "your-textdomain"),
    "filter_by_date" => esc_html__("", "your-textdomain"),
    "items_list_navigation" => esc_html__("Bb seasons list navigation", "your-textdomain"),
    "items_list" => esc_html__("BB Seasons list", "your-textdomain"),
    "item_published" => esc_html__("BB Season published", "your-textdomain"),
    "item_published_privately" => esc_html__("Bb season published privately", "your-textdomain"),
    "item_reverted_to_draft" => esc_html__("Bb season reverted to draft", "your-textdomain"),
    "item_scheduled" => esc_html__("BB Season scheduled", "your-textdomain"),
    "item_updated" => esc_html__("BB Season updated", "your-textdomain"),
  ];
  $args = [
    "label" => esc_html__("BB Seasons", "your-textdomain"),
    "labels" => $labels,
    "description" => "",
    "public" => true,
    "hierarchical" => true,
    "exclude_from_search" => false,
    "publicly_queryable" => true,
    "show_ui" => true,
    "show_in_nav_menus" => true,
    "show_in_admin_bar" => true,
    "show_in_rest" => true,
    "query_var" => true,
    "can_export" => true,
    "delete_with_user" => true,
    "has_archive" => true,
    "rest_base" => "",
    "show_in_menu" => true,
    "menu_position" => 7,
    "menu_icon" => "dashicons-admin-multisite",
    "capability_type" => "post",
    "supports" => ["title", "editor", "thumbnail", "custom-fields"],
    "taxonomies" => [],
    "rewrite" => [
      "with_front" => false,
    ],
  ];

  register_post_type("bigbrother-seasons", $args);
}

add_filter( 'rwmb_meta_boxes', 'bbj_seasons' );
function bbj_seasons( $meta_boxes ) {
    $prefix = '';

    $meta_boxes[] = [
        'title'        => __( 'BB Seasons',              'bbj-tools' ),
        'id'           => 'bb-seasons',
        'post_types'   => [ 'bigbrother-seasons' ],
        'storage_type' => 'custom_table',
        'table'        => 'wp_bbj_seasons',
        'fields'       => [
            // Basic info
            [
                'name' => __( 'Full Name',   'bbj-tools' ),
                'id'   => $prefix . 'full_name',
                'type' => 'text',
            ],
            [
                'name'    => __( 'Start Date', 'bbj-tools' ),
                'id'      => $prefix . 'start_date',
                'type'    => 'date',
                'columns' => 4,
            ],
            [
                'name'    => __( 'End Date',   'bbj-tools' ),
                'id'      => $prefix . 'end_date',
                'type'    => 'date',
                'columns' => 4,
            ],
            [
                'name'    => __( 'Abbreviation','bbj-tools' ),
                'id'      => $prefix . 'abbreviation',
                'type'    => 'text',
                'columns' => 1,
            ],

            [ 'type' => 'divider' ],

            [
                'name'              => __( 'Season Number',          'bbj-tools' ),
                'id'                => $prefix . 'season_number',
                'type'              => 'text',
                'label_description' => __( 'Just the numeric part (e.g. “23”).','bbj-tools' ),
                'columns'           => 3,
            ],
            [
                'name'              => __( 'Season Picture',         'bbj-tools' ),
                'id'                => $prefix . 'season_picture',
                'type'              => 'single_image',
                'label_description' => __( 'Small thumbnail',        'bbj-tools' ),
                'image_size'        => 'profile-picture',
                'columns'           => 4,
            ],
            [
                'name'              => __( 'Season Banner Image',    'bbj-tools' ),
                'id'                => $prefix . 'season_banner_image',
                'type'              => 'single_image',
                'label_description' => __( 'Large banner',           'bbj-tools' ),
                'image_size'        => 'player-banner',
                'columns'           => 5,
            ],

            [ 'type' => 'divider' ],

           

            // Unique single pointers
            [
                'name'      => __( 'Winner',                  'bbj-tools' ),
                'id'        => $prefix . 'season_winner',
                'type'      => 'post',
                'post_type' => [ 'bigbrother-players' ],
            ],
            [
                'name'      => __( 'Runner Up',               'bbj-tools' ),
                'id'        => $prefix . 'runner_up',
                'type'      => 'post',
                'post_type' => [ 'bigbrother-players' ],
            ],
            [
                'name'      => __( 'AFP (Fan Favorite)',       'bbj-tools' ),
                'id'        => $prefix . 'afp',
                'type'      => 'post',
                'post_type' => [ 'bigbrother-players' ],
            ],
        ],
    ];

    return $meta_boxes;
}
