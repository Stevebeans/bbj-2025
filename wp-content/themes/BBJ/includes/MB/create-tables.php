<?php

add_action("init", "prefix_create_table");
function prefix_create_table()
{
  if (!class_exists("MB_Custom_Table_API")) {
    return;
  }

  MB_Custom_Table_API::create("wp_bbj_custom_blocks", [
    "posts_per_page" => "INT(20) NOT NULL",
  ]);
}
