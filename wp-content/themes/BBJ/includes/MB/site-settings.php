<?php
add_filter("rwmb_meta_boxes", "site_settings");

function site_settings($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("Site Settings", "your-text-domain"),
    "id" => "site-settings",
    "settings_pages" => ["bbj-settings"],
    "fields" => [
      [
        "name" => __("Current Category", "your-text-domain"),
        "id" => $prefix . "current_category",
        "type" => "taxonomy",
        "taxonomy" => ["category"],
        "field_type" => "select_advanced",
      ],
      [
        "name" => __("Current Season", "your-text-domain"),
        "id" => $prefix . "current_season",
        "type" => "post",
        "post_type" => ["bigbrother-seasons"],
        "field_type" => "select_advanced",
      ],
    ],
  ];

  return $meta_boxes;
}
