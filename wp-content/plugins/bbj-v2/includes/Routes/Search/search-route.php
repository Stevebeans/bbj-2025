<?php

add_action("rest_api_init", function () {
  register_rest_route("bbj/v1", "/search", [
    "methods" => "GET",
    "callback" => "bbj_search_results",
    "permission_callback" => "__return_true",
  ]);
});

function bbj_search_results($request)
{
  
  $search_query = $request->get_param("query");

  // bbj_log3(print_r($search_query, true));

  $args = [
    "post_type" => ["post", "page", "bigbrother-players", "bigbrother-seasons"],
    "s" => $search_query,
    "posts_per_page" => 10,
  ];
  $results = [
    "general" => [],
    "players" => [],
    "seasons" => [],
  ];

  $query = new WP_Query($args);

  while ($query->have_posts()):
    $query->the_post();

    if (get_post_type() == "post" || get_post_type() == "page") {
      array_push($results["general"], [
        "title" => get_the_title(),
        "permalink" => get_the_permalink(),
      ]);
    }

    if (get_post_type() == "bigbrother-players") {
      $player_id            = get_the_ID();
      $player_title         = get_the_title();
      $player_permalink     = get_the_permalink();
      $player_image         = rwmb_meta("profile_picture", ["size" => "tiny"], $player_id);
      $abbreviation         = '';
      $player_row_id        = (int) get_post_meta( $player_id, 'player_row_id', true );
      if ( ! $player_row_id ) {
        $player_row_id = (int) $player_id; // fallback if 1:1
      }

      global $wpdb;
      $ps_table = $wpdb->prefix . 'bbj_v2_player_season';
      $s_table  = $wpdb->prefix . 'bbj_seasons';

      // get one season for the player
      $season = $wpdb->get_var(
        $wpdb->prepare(
          "
          SELECT s.abbreviation
          FROM {$ps_table} ps
          JOIN {$s_table}  s ON s.id = ps.bbj_season
          WHERE ps.bbj_player = %d
          ORDER BY s.id DESC
          LIMIT 1
          ",
          $player_row_id
        )
      );

      if (get_post_type() == "bigbrother-seasons") {
      array_push($results["seasons"], [
        "title" => get_the_title(),
        "permalink" => get_the_permalink(),
      ]);
      }
;

      $results['players'][] = [
        'title'        => $player_title,
        'permalink'    => $player_permalink,
        'player_image' => $player_image,
        'ID'           => $player_id,
        'abbreviation' => (string) $abbreviation,
      ];

    }

  endwhile;

  return $results;
}
