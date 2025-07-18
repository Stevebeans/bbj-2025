<?php
function bbj_register_feed_updates_endpoint()
{
  register_rest_route("bbj/v1", "feed-updates", [
    "methods" => "GET",
    "callback" => "bbj_get_feed_updates",
    "permission_callback" => "__return_true",
    "args" => [
      "mode" => [
        "required" => false,
        "default" => "getInitial",
      ],
    ],
  ]);
}

function bbj_get_feed_updates($request)
{
  $mode = $request->get_param("mode");
  $original_post = intval($request->get_param("originalPost"));
  $latest_post = intval($request->get_param("latestPost"));

  switch ($mode) {
    case "checkLatest":
      return checkLatest();
      break;
    case "getCount":
      return getCount($original_post, $latest_post);
      break;
    default:
      return checkLast();
      break;
  }
}

function getCount($original_post, $latest_post)
{
  global $wpdb;
  $post_ids = $wpdb->get_col(
    $wpdb->prepare(
      "
  SELECT ID FROM $wpdb->posts
  WHERE ID BETWEEN %d AND %d
  AND post_type = 'live-feed-updates'
  AND post_status = 'publish'
  ORDER BY ID ASC
",
      $original_post,
      $latest_post
    )
  );
  $count = count($post_ids);

  $postID = 45040;

  return rest_ensure_response($count - 1);
}

function checkLatest()
{
  $args = [
    "post_type" => "live-feed-updates",
    "posts_per_page" => 1,
    "orderby" => "date",
    "order" => "DESC",
  ];

  $query = new WP_Query($args);

  if ($query->have_posts()) {
    while ($query->have_posts()):
      $query->the_post();
      $latest_post = get_the_ID();
    endwhile;
  }

  wp_reset_postdata();

  return rest_ensure_response($latest_post);
}

function checkLast()
{
  $args = [
    "post_type" => "live-feed-updates",
    "posts_per_page" => 15,
    "orderby" => "date",
    "order" => "DESC",
  ];

  $posts = get_posts($args);

  return rest_ensure_response($posts);
}

add_action("rest_api_init", "bbj_register_feed_updates_endpoint");
