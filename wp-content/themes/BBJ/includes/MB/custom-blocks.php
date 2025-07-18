<?php

add_filter("rwmb_meta_boxes", function ($meta_boxes) {
  $ranNum = rand(0000000001, 9999999999);
  $ranNum2 = rand(0000000001, 9999999999);

  $meta_boxes[] = [
    "title" => "Feed Updates",
    "id" => "feed-updates",
    "description" => "Show the feed updates",
    "type" => "block",
    "icon" => "media-document",
    "category" => "kadence-blocks",
    "context" => "side",
    "render_template" => get_template_directory() . "/includes/blocks/feed-updates/template.php",
    "enqueue_style" => get_template_directory_uri() . "/includes/blocks/feed-updates/style.css",
    "supports" => [
      "align" => ["wide", "full"],
    ],

    // Block fields.
    "fields" => [
      [
        "name" => __("Feed Date", "your-text-domain"),
        "id" => "feed_date",
        "type" => "date",
        "required" => true,
      ],
      [
        "name" => "ID",
        "id" => "random_id",
        "type" => "number",
        "label_description" => __("Any 10 digits", "your-text-domain"),
        "size" => 10,
        "required" => false,
        "std" => $ranNum,
      ],
      [
        "name" => "Cache ID",
        "id" => "cache_ID",
        "type" => "number",
        "label_description" => __("Any 10 digits", "your-text-domain"),
        "size" => 10,
        "required" => false,
        "std" => $ranNum2,
      ],
      [
        "type" => "number",
        "id" => "posts_per_page",
        "name" => "Posts Per Page",
        "std" => 15,
      ],
      [
        "name" => "Category",
        "id" => "customPT",
        "type" => "select",
        "options" => [
          "live-feed-updates" => __("Feed Updates,", "your-text-domain"),
          "bigbrother-players" => __("Players,", "your-text-domain"),
          "bigbrother-seasons" => __("Seasons,", "your-text-domain"),
          "page" => __("Pages,", "your-text-domain"),
          "post" => __("Posts", "your-text-domain"),
        ],
        "std" => "live-feed-updates",
        "multiple" => true,
        "required" => true,
      ],
    ],
  ];
  return $meta_boxes;
});
