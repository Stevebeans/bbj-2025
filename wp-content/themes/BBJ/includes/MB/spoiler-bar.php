<?php
add_filter("rwmb_meta_boxes", "spoiler_bar");

function spoiler_bar($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("BB Season Player Spoiler Bar", "your-text-domain"),
    "id" => "bb-season-player-relationship",
    "post_types" => ["bigbrother-seasons"],
    "storage_type" => "custom_table",
    "table" => "wp_bbj_player_season_new",
    "fields" => [
      [
        "name" => __("Players", "your-text-domain"),
        "id" => $prefix . "player_list2",
        "type" => "group",
        "clone" => true,
        "sort_clone" => true,
        "clone_as_multiple" => true,
        "fields" => [
          [
            "name" => __("Name", "your-text-domain"),
            "id" => $prefix . "player_id",
            "type" => "post",
            "post_type" => ["bigbrother-players"],
            "field_type" => "select_advanced",
            "columns" => 2,
          ],
          [
            "name" => __("HoH", "your-text-domain"),
            "id" => $prefix . "current_hoh",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("PoV", "your-text-domain"),
            "id" => $prefix . "current_pov",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Jury", "your-text-domain"),
            "id" => $prefix . "current_jury",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Nom", "your-text-domain"),
            "id" => $prefix . "current_nom",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Nom 2", "your-text-domain"),
            "id" => $prefix . "current_nom2",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Nom 3", "your-text-domain"),
            "id" => $prefix . "current_nom3",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Evicted", "your-text-domain"),
            "id" => $prefix . "current_evic",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Winner", "your-text-domain"),
            "id" => $prefix . "current_winner",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("Second", "your-text-domain"),
            "id" => $prefix . "current_second",
            "type" => "checkbox",
            "columns" => 1,
          ],
          [
            "name" => __("AFP", "your-text-domain"),
            "id" => $prefix . "current_afp",
            "type" => "checkbox",
            "columns" => 1,
          ],
        ],
      ],
      [
        "type" => "divider",
      ],
    ],
  ];

  return $meta_boxes;
}
