<?php
add_filter("rwmb_meta_boxes", "feed_updates");

function feed_updates($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("Feed Updates", "your-text-domain"),
    "id" => "feed-updates",
    "post_types" => ["live-feed-updates"],
    "storage_type" => "custom_table",
    "table" => "wp_bbj_feedupdates",
    "fields" => [],
  ];

  return $meta_boxes;
}
