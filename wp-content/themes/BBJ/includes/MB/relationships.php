<?php
add_action("mb_relationships_init", "BBJ_Relationships");

function BBJ_Relationships()
{
  MB_Relationships_API::register([
    "id" => "player-to-season",
    "from" => [
      "object_type" => "post",
      "post_type" => "bigbrother-players",
      "meta_box" => [
        "title" => "Select Season",
      ],
    ],
    "to" => [
      "object_type" => "post",
      "post_type" => "bigbrother-seasons",
      "meta_box" => [
        "title" => "Select Player",
      ],
    ],
  ]);
}
