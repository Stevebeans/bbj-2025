<?php




add_action("init", "register_feeds");
function register_feeds()
{
  $labels = [
    "name" => esc_html__("Feed Updates", "your-textdomain"),
    "singular_name" => esc_html__("Feed Update", "your-textdomain"),
    "add_new" => esc_html__("Add New", "your-textdomain"),
    "add_new_item" => esc_html__("Add new feed update", "your-textdomain"),
    "edit_item" => esc_html__("Edit Feed Update", "your-textdomain"),
    "new_item" => esc_html__("New Feed Update", "your-textdomain"),
    "view_item" => esc_html__("View Feed Update", "your-textdomain"),
    "view_items" => esc_html__("View Feed Updates", "your-textdomain"),
    "search_items" => esc_html__("Search Feed Updates", "your-textdomain"),
    "not_found" => esc_html__("No feed updates found", "your-textdomain"),
    "not_found_in_trash" => esc_html__("No feed updates found in Trash", "your-textdomain"),
    "parent_item_colon" => esc_html__("Parent Feed Update:", "your-textdomain"),
    "all_items" => esc_html__("All Feed Updates", "your-textdomain"),
    "archives" => esc_html__("Feed Update Archives", "your-textdomain"),
    "attributes" => esc_html__("Feed Update Attributes", "your-textdomain"),
    "insert_into_item" => esc_html__("Insert into feed update", "your-textdomain"),
    "uploaded_to_this_item" => esc_html__("Uploaded to this feed update", "your-textdomain"),
    "featured_image" => esc_html__("Featured image", "your-textdomain"),
    "set_featured_image" => esc_html__("Set featured image", "your-textdomain"),
    "remove_featured_image" => esc_html__("Remove featured image", "your-textdomain"),
    "use_featured_image" => esc_html__("Use as featured image", "your-textdomain"),
    "menu_name" => esc_html__("Feed Updates", "your-textdomain"),
    "filter_items_list" => esc_html__("Filter feed updates list", "your-textdomain"),
    "filter_by_date" => esc_html__("", "your-textdomain"),
    "items_list_navigation" => esc_html__("Feed updates list navigation", "your-textdomain"),
    "items_list" => esc_html__("Feed Updates list", "your-textdomain"),
    "item_published" => esc_html__("Feed Update published", "your-textdomain"),
    "item_published_privately" => esc_html__("Feed update published privately", "your-textdomain"),
    "item_reverted_to_draft" => esc_html__("Feed update reverted to draft", "your-textdomain"),
    "item_scheduled" => esc_html__("Feed Update scheduled", "your-textdomain"),
    "item_updated" => esc_html__("Feed Update updated", "your-textdomain"),
  ];
  $args = [
    "label" => esc_html__("Feed Updates", "your-textdomain"),
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
    'show_in_graphql' => true,
    'graphql_single_name' => 'feedUpdate',
    'graphql_plural_name' => 'feedUpdates',
    "query_var" => true,
    "can_export" => true,
    "delete_with_user" => false,
    "has_archive" => true,
    "rest_base" => "",
    "show_in_menu" => true,
    "menu_position" => 2,
    "menu_icon" => "dashicons-video-alt",
    "capability_type" => "post",
    "supports" => ["title", "editor", "thumbnail", "custom-fields", "comments"],
    "taxonomies" => [],
    "rewrite" => [
      "with_front" => false,
    ],
  ];

  register_post_type("live-feed-updates", $args);
}
add_action("init", "your_prefix_register_post_type");
function your_prefix_register_post_type()
{
  $labels = [
    "name" => esc_html__("Live Feed Pages", "your-textdomain"),
    "singular_name" => esc_html__("Live Feed Page", "your-textdomain"),
    "add_new" => esc_html__("Add New", "your-textdomain"),
    "add_new_item" => esc_html__("Add new live feed page", "your-textdomain"),
    "edit_item" => esc_html__("Edit Live Feed Page", "your-textdomain"),
    "new_item" => esc_html__("New Live Feed Page", "your-textdomain"),
    "view_item" => esc_html__("View Live Feed Page", "your-textdomain"),
    "view_items" => esc_html__("View Live Feed Pages", "your-textdomain"),
    "search_items" => esc_html__("Search Live Feed Pages", "your-textdomain"),
    "not_found" => esc_html__("No live feed pages found", "your-textdomain"),
    "not_found_in_trash" => esc_html__("No live feed pages found in Trash", "your-textdomain"),
    "parent_item_colon" => esc_html__("Parent Live Feed Page:", "your-textdomain"),
    "all_items" => esc_html__("All Live Feed Pages", "your-textdomain"),
    "archives" => esc_html__("Live Feed Page Archives", "your-textdomain"),
    "attributes" => esc_html__("Live Feed Page Attributes", "your-textdomain"),
    "insert_into_item" => esc_html__("Insert into live feed page", "your-textdomain"),
    "uploaded_to_this_item" => esc_html__("Uploaded to this live feed page", "your-textdomain"),
    "featured_image" => esc_html__("Featured image", "your-textdomain"),
    "set_featured_image" => esc_html__("Set featured image", "your-textdomain"),
    "remove_featured_image" => esc_html__("Remove featured image", "your-textdomain"),
    "use_featured_image" => esc_html__("Use as featured image", "your-textdomain"),
    "menu_name" => esc_html__("Live Feed Pages", "your-textdomain"),
    "filter_items_list" => esc_html__("Filter live feed pages list", "your-textdomain"),
    "filter_by_date" => esc_html__("", "your-textdomain"),
    "items_list_navigation" => esc_html__("Live feed pages list navigation", "your-textdomain"),
    "items_list" => esc_html__("Live Feed Pages list", "your-textdomain"),
    "item_published" => esc_html__("Live Feed Page published", "your-textdomain"),
    "item_published_privately" => esc_html__("Live feed page published privately", "your-textdomain"),
    "item_reverted_to_draft" => esc_html__("Live feed page reverted to draft", "your-textdomain"),
    "item_scheduled" => esc_html__("Live Feed Page scheduled", "your-textdomain"),
    "item_updated" => esc_html__("Live Feed Page updated", "your-textdomain"),
    "text_domain" => esc_html__("your-textdomain", "your-textdomain"),
  ];
  $args = [
    "label" => esc_html__("Live Feed Pages", "your-textdomain"),
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
    "menu_position" => 6,
    "menu_icon" => "dashicons-media-text",
    "capability_type" => "post",
    "supports" => ["title", "editor", "thumbnail", "excerpt", "author", "custom-fields", "comments"],
    "taxonomies" => [],
    "rewrite" => [
      "with_front" => false,
    ],
  ];

  register_post_type("live-feed-archives", $args);
}

// Register CPT for player-season-link 

// add_action("init", "register_player_season_link");
// function register_player_season_link()
// {
//   $labels = [
//     "name" => esc_html__("Player Season Links", "your-textdomain"),
//     "singular_name" => esc_html__("Player Season Link", "your-textdomain"),
//     "add_new" => esc_html__("Add New", "your-textdomain"),
//     "add_new_item" => esc_html__("Add new player season link", "your-textdomain"),
//     "edit_item" => esc_html__("Edit Player Season Link", "your-textdomain"),
//     "new_item" => esc_html__("New Player Season Link", "your-textdomain"),
//     "view_item" => esc_html__("View Player Season Link", "your-textdomain"),
//     "view_items" => esc_html__("View Player Season Links", "your-textdomain"),
//     "search_items" => esc_html__("Search Player Season Links", "your-textdomain"),
//     "not_found" => esc_html__("No player season links found", "your-textdomain"),
//     "not_found_in_trash" => esc_html__("No player season links found in Trash", "your-textdomain"),
//     "parent_item_colon" => esc_html__("Parent Player Season Link:", "your-textdomain"),
//     "all_items" => esc_html__("All Player Season Links", "your-textdomain"),
//     "archives" => esc_html__("Player Season Link Archives", "your-textdomain"),
//     "attributes" => esc_html__("Player Season Link Attributes", "your-textdomain"),
//     "insert_into_item" => esc_html__("Insert into player season link", "your-textdomain"),
//     "uploaded_to_this_item" => esc_html__("Uploaded to this player season link", "your-textdomain"),
//     "featured_image" => esc_html__("Featured image", "your-textdomain"),
//     "set_featured_image" => esc_html__("Set featured image", "your-textdomain"),
//     "remove_featured_image" => esc_html__("Remove featured image", "your-textdomain"),
//     "use_featured_image" => esc_html__("Use as featured image", "your-textdomain"),
//     "menu_name" => esc_html__("Player Season Links", "your-textdomain"),
//     "filter_items_list" => esc_html__("Filter player season links list", "your-textdomain"),
//     "filter_by_date" => esc_html__("", "your-textdomain"),
//     "items_list_navigation" => esc_html__("Player season links list navigation", "your-textdomain"),
//     "items_list" => esc_html__("Player Season Links list", "your-textdomain"),
//     "item_published" => esc_html__("Player Season Link published", "your-textdomain")
//   ];
//   $args = [
//     "label" => esc_html__("Player Season Links", "your-textdomain"),
//     "labels" => $labels,
//     "description" => "",
//     "public" => true,
//     "hierarchical" => false,
//     "exclude_from_search" => false,
//     "publicly_queryable" => true,
//     "show_ui" => true,
//     "show_in_nav_menus" => true,
//     "show_in_admin_bar" => true,
//     "show_in_rest" => true,
//     "query_var" => true,
//     "can_export" => true,
//     "delete_with_user" => false,
//     "has_archive" => false,
//     "rest_base" => "",
//     "show_in_menu" => true,
//     "menu_position" => 5,
//     "menu_icon" => "dashicons-admin-links",
//     "capability_type" => "post",
//     "supports" => ["title", "custom-fields"],
//     "taxonomies" => [],
//     "rewrite" => [
//       "with_front" => false,
//       'slug' => 'player-season-link',
//       'pages' => false,
//       'feeds' => false
//     ],
//   ];
//   register_post_type("player-season-link", $args);
// }
    
