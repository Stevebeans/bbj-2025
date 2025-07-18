<?php
add_filter("rwmb_meta_boxes", "bbj_seasons");

function bbj_seasons($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("BB Seasons", "your-text-domain"),
    "id" => "bb-seasons",
    "post_types" => ["bigbrother-seasons"],
    "storage_type" => "custom_table",
    "table" => "wp_bbj_seasons",
    "fields" => [
      [
        "name" => __("Full Name", "your-text-domain"),
        "id" => $prefix . "full_name",
        "type" => "text",
      ],
      [
        "name" => __("Start Date", "your-text-domain"),
        "id" => $prefix . "start_date",
        "type" => "date",
        "columns" => 4,
      ],
      [
        "name" => __("End Date", "your-text-domain"),
        "id" => $prefix . "end_date",
        "type" => "date",
        "columns" => 4,
      ],
      [
        "name" => __("Abbreviation", "your-text-domain"),
        "id" => $prefix . "abbreviation",
        "type" => "text",
        "columns" => 1,
      ],
      [
        "type" => "divider",
      ],
      [
        "name" => __("Season Number", "your-text-domain"),
        "id" => $prefix . "season_number",
        "type" => "text",
        "label_description" => __("This is just the number (ie 23) that could be used in certain places", "your-text-domain"),
        "columns" => 3,
      ],
      [
        "name" => __("Season Picture", "your-text-domain"),
        "id" => $prefix . "season_picture",
        "type" => "single_image",
        "label_description" => __("Small thumbnail", "your-text-domain"),
        "image_size" => "profile-picture",
        "columns" => 4,
      ],
      [
        "name" => __("Season Banner Image", "your-text-domain"),
        "id" => $prefix . "season_banner_image",
        "type" => "single_image",
        "label_description" => __("Large Banner", "your-text-domain"),
        "image_size" => "player-banner",
        "columns" => 5,
      ],
    ],
  ];

  return $meta_boxes;
}
