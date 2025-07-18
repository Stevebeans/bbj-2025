<?php 


function boilerplate_add_support()
{
  add_theme_support("title-tag");
  add_theme_support("post-thumbnails");
  add_theme_support("block-templates");

  add_image_size("featured-thumbnail", 400, 200, true);
  add_image_size("featured-image-header", 1600, 500, true);
  add_image_size("player-banner", 1200, 350, true);
  add_image_size("profile-picture", 200, 200, true);
  add_image_size("prof-pic-lg", 375, 375, true);
  add_image_size("tiny", 50, 50, true);
}

add_action("after_setup_theme", "boilerplate_add_support");