<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package bigbrotherjunkies
 */

get_header(); ?>


<?php
$mainQuery = new WP_Query([
  "post_type" => ["bigbrother-players", "bigbrother-seasons", "feed_update", "post"],
  "posts_per_page" => 50,
]);

$results = [
  "players" => [],
  "seasons" => [],
  "feed" => [],
  "general" => [],
];

while ($mainQuery->have_posts()):
  $mainQuery->the_post();
  $profilePic = rwmb_meta("profile_picture", ["size" => "tiny"]);

  if (get_post_type() == "bigbrother-players"):
    get_the_title();

    array_push($results["players"], [
      "title" => get_the_title(),
      "permalink" => get_the_permalink(),
      "postType" => get_post_type(),
      "thumbnail" => $profilePic["url"],
      "first_name" => rwmb_meta("first_name"),
      "last_name" => rwmb_meta("last_name"),
    ]);
  endif;

  if (get_post_type() == "bigbrother-seasons"):
    array_push($results["seasons"], [
      "title" => get_the_title(),
      "permalink" => get_the_permalink(),
    ]);
  endif;

  if (get_post_type() == "feed_update"):
    array_push($results["feed"], [
      "title" => get_the_title(),
      "permalink" => get_the_permalink(),
    ]);
  endif;

  if (get_post_type() == "post"):
    $description = null;
    if (has_excerpt()) {
      $description = get_the_excerpt();
    } else {
      $description = wp_trim_words(get_the_content(), 18);
    }

    array_push($results["general"], [
      "title" => get_the_title(),
      "permalink" => get_the_permalink(),
      "thumb" => get_the_post_thumbnail_url($page->ID, "tiny"),
      "post" => $description,
    ]);
  endif;
endwhile;

echo "<pre>", print_r($results, 1), "</pre>";
?>



<?php get_footer();
