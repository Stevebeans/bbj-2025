<?php
add_filter("rwmb_meta_boxes", "season_results");

function season_results($meta_boxes)
{
  $prefix = "";

  $meta_boxes[] = [
    "title" => __("BB Season Player Spoiler Bar", "Season Results"),
    "id" => "bb-season-player-relationship",
    "post_types" => ["bigbrother-seasons"],
    "storage_type" => "custom_table",
    "table" => "wp_bbj_player_season_new",
    "fields" => [
      [
        "name" => __("Players", "Season Results"),
        "id" => $prefix . "player_list2",
        "type" => "group",
        "clone" => true,
        "sort_clone" => true,
        "clone_as_multiple" => true,
        "fields" => [
          [
            "name" => __("Name", "Season Results"),
            "id" => $prefix . "player_id",
            "type" => "post",
            "post_type" => ["bigbrother-players"],
            "field_type" => "select_advanced",
            "columns" => 2,
          ],
          [
            "name" => __("Current House Status", "Season Results"),
            "id" => $prefix . "current_house_status",
            "type" => "select_advanced",
            "options" => [
              "hoh" => __("Head of Household", "Season Results"),
              "pov" => __("Power of Veto", "Season Results"),
              "jury" => __("Jury Member", "Season Results"),
              "nom" => __("Nominee", "Season Results"),
              "evic" => __("Evicted", "Season Results"),
              "winner" => __("Winner", "Season Results"),
              "second" => __("Runner-Up", "Season Results"),
              "afp" => __('America\'s Favorite', "Season Results"),
            ],
            "multiple" => true,
            "columns" => 10,
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
