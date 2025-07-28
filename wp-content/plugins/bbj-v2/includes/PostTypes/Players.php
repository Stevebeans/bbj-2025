<?php

add_action("init", "register_players");
function register_players()
{
  $labels = [
    "name" => esc_html__("BB Players", "your-textdomain"),
    "singular_name" => esc_html__("BB Player", "your-textdomain"),
    "add_new" => esc_html__("Add New", "your-textdomain"),
    "add_new_item" => esc_html__("Add new bb player", "your-textdomain"),
    "edit_item" => esc_html__("Edit BB Player", "your-textdomain"),
    "new_item" => esc_html__("New BB Player", "your-textdomain"),
    "view_item" => esc_html__("View BB Player", "your-textdomain"),
    "view_items" => esc_html__("View BB Players", "your-textdomain"),
    "search_items" => esc_html__("Search BB Players", "your-textdomain"),
    "not_found" => esc_html__("No bb players found", "your-textdomain"),
    "not_found_in_trash" => esc_html__("No bb players found in Trash", "your-textdomain"),
    "parent_item_colon" => esc_html__("Parent BB Player:", "your-textdomain"),
    "all_items" => esc_html__("All BB Players", "your-textdomain"),
    "archives" => esc_html__("BB Player Archives", "your-textdomain"),
    "attributes" => esc_html__("BB Player Attributes", "your-textdomain"),
    "insert_into_item" => esc_html__("Insert into bb player", "your-textdomain"),
    "uploaded_to_this_item" => esc_html__("Uploaded to this bb player", "your-textdomain"),
    "featured_image" => esc_html__("Featured image", "your-textdomain"),
    "set_featured_image" => esc_html__("Set featured image", "your-textdomain"),
    "remove_featured_image" => esc_html__("Remove featured image", "your-textdomain"),
    "use_featured_image" => esc_html__("Use as featured image", "your-textdomain"),
    "menu_name" => esc_html__("BB Players", "your-textdomain"),
    "filter_items_list" => esc_html__("Filter bb players list", "your-textdomain"),
    "filter_by_date" => esc_html__("", "your-textdomain"),
    "items_list_navigation" => esc_html__("Bb players list navigation", "your-textdomain"),
    "items_list" => esc_html__("BB Players list", "your-textdomain"),
    "item_published" => esc_html__("BB Player published", "your-textdomain"),
    "item_published_privately" => esc_html__("Bb player published privately", "your-textdomain"),
    "item_reverted_to_draft" => esc_html__("Bb player reverted to draft", "your-textdomain"),
    "item_scheduled" => esc_html__("BB Player scheduled", "your-textdomain"),
    "item_updated" => esc_html__("BB Player updated", "your-textdomain"),
  ];
  $args = [
    "label" => esc_html__("BB Players", "your-textdomain"),
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
    "menu_icon" => "dashicons-admin-users",
    "capability_type" => "post",
    "supports" => ["title", "editor", "thumbnail", "custom-fields"],
    "taxonomies" => [],
    "rewrite" => [
      "with_front" => false,
    ],
  ];

  register_post_type("bigbrother-players", $args);
}



add_filter("rwmb_meta_boxes", "bbj_players");

function bbj_players($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("Player Info", "your-text-domain"),
    "id" => "bb-players",
    "post_types" => ["bigbrother-players"],
    "storage_type" => "custom_table",
    "table" => "wp_bbj_players",
    "geo" => [
      "api_key" => "AIzaSyC_shPNS0EYeHXEIKFKhZDLhzpgoUphjts",
    ],
    "fields" => [
      [
        "name" => __("First Name", "your-text-domain"),
        "id" => $prefix . "first_name",
        "type" => "text",
        "columns" => 4,
      ],
      [
        "name" => __("Last Name", "your-text-domain"),
        "id" => $prefix . "last_name",
        "type" => "text",
        "columns" => 4,
      ],
      [
        "name" => __("Official Nickname", "your-text-domain"),
        "id" => $prefix . "official_nickname",
        "type" => "text",
        "columns" => 4,
      ],
      [
        "name" => __("Gender", "your-text-domain"),
        "id" => $prefix . "player_gender",
        "type" => "select_advanced",
        "options" => [
          "male" => __("Male", "your-text-domain"),
          "female" => __("Female", "your-text-domain"),
        ],
        "std" => "Please Select",
        "required" => true,
      ],
      [
        "name" => __("Profile Picture", "your-text-domain"),
        "id" => $prefix . "profile_picture",
        "type" => "single_image",
        "columns" => 4,
      ],
      [
        "name" => __("Player Banner", "your-text-domain"),
        "id" => $prefix . "player_banner",
        "type" => "single_image",
        "columns" => 4,
      ],
      [
        "name" => __("Date of birth", "your-text-domain"),
        "id" => $prefix . "date_of_birth",
        "type" => "date",
        "columns" => 6,
      ],
      [
        "name" => __("Occupation", "your-text-domain"),
        "id" => $prefix . "occupation",
        "type" => "text",
        "columns" => 6,
      ],
      [
        "name" => __("Facebook", "your-text-domain"),
        "id" => $prefix . "facebook",
        "type" => "url",
        "columns" => 3,
      ],
      [
        "name" => __("Instagram", "your-text-domain"),
        "id" => $prefix . "instagram",
        "type" => "url",
        "columns" => 3,
      ],
      [
        "name" => __("Twitter", "your-text-domain"),
        "id" => $prefix . "twitter",
        "type" => "url",
        "columns" => 3,
      ],
      [
        "name" => __("Tiktok", "your-text-domain"),
        "id" => $prefix . "tiktok",
        "type" => "url",
        "columns" => 3,
      ],
    ],
  ];

  return $meta_boxes;
}
