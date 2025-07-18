<?php
add_filter("rwmb_meta_boxes", "bbj_geo");

function bbj_geo($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("GeoLocation", "your-text-domain"),
    "id" => "geolocation",
    "post_types" => ["bigbrother-players"],
    "context" => "side",
    "storage_type" => "custom_table",
    "table" => "wp_bbj_geo",
    "geo" => [
      "api_key" => "AIzaSyC_shPNS0EYeHXEIKFKhZDLhzpgoUphjts",
      "types" => "(cities)",
    ],
    "fields" => [
      [
        "name" => __("Address", "your-text-domain"),
        "id" => $prefix . "address_street",
        "type" => "text",
      ],
      [
        "name" => __("City", "your-text-domain"),
        "id" => $prefix . "locality",
        "type" => "text",
      ],
      [
        "name" => __("State", "your-text-domain"),
        "id" => $prefix . "administrative_area_level_1",
        "type" => "text",
      ],
      [
        "name" => __("Lng", "your-text-domain"),
        "id" => $prefix . "lng",
        "type" => "text",
      ],
      [
        "name" => __("Lat", "your-text-domain"),
        "id" => $prefix . "lat",
        "type" => "text",
      ],
      [
        "name" => __("Map", "your-text-domain"),
        "id" => $prefix . "address_map",
        "type" => "map",
        "api_key" => "AIzaSyC_shPNS0EYeHXEIKFKhZDLhzpgoUphjts",
        "address_field" => "address_street",
      ],
    ],
  ];

  return $meta_boxes;
}
