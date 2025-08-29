<?php 


// Create wordpress API route called create-player 

add_action('rest_api_init', function () {
  register_rest_route('bbj/v1', '/create-player', array(
    'methods' => 'POST',
    'callback' => 'create_bigbrother_player',
  ));

  register_rest_route('bbj/v1', '/get-players', array(
    'methods' => 'GET',
    'callback' => 'get_player_list',
  ));

  register_rest_route('bbj/v1', '/get-seasons', array(
    'methods' => 'GET',
    'callback' => 'get_season_list',
  ));

  register_rest_route('bbj/v1', '/create-season', array(
    'methods' => 'POST',
    'callback' => 'create_bigbrother_season',
  ));

  // register_rest_route('bbj/v1', '/feed-update', array(
  //   'methods' => 'POST',
  //   'callback' => 'update_feed',
  //   'permission_callback' => function () {
  //     return current_user_can('edit_others_posts') || current_user_can('updater');
  //   }
  // ));

  
  register_rest_route( 'bbj/v1', '/get-season/(?P<seasonId>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_season_lookup',
  ) );


  register_rest_route( 'bbj/v1', '/get-player/(?P<playerId>\d+)', array(
    'methods' => 'GET',
    'callback' => 'get_player_lookup',
  ) );

  register_rest_route( 'bbj/v1', 'add-player-to-season/', array(
    'methods' => 'POST',
    'callback' => 'add_player_to_season',
  ) );

  register_rest_route( 'bbj/v1', 'remove-player-from-season', array(
    'methods' => 'POST',
    'callback' => 'remove_player_season',
  ) );

  register_rest_route( 'bbj/v1', 'add_comment', [
    'methods' => 'POST',
    'callback' => 'bbj_add_comment',
    'permission_callback' => '__return_true',
  ] );
  
  register_rest_route( 'bbj/v1', 'add_feed_update_rating', [
    'methods' => 'POST',
    'callback' => 'bbj_add_feed_update_rating',
    'permission_callback' => '__return_true',
  ] );

  register_rest_route( 'bbj/v1', 'update-season-players', [
    'methods' => 'POST',
    'callback' => 'bbj_update_season_players',
    'permission_callback' => '__return_true',
  ] );

  register_rest_route( 'bbj/v1', 'get-season-players/(?P<seasonId>\d+)', [
    'methods' => 'GET',
    'callback' => 'bbj_get_season_players',
    'permission_callback' => '__return_true',
  ] );
  
}, 20);


function bbj_get_season_players(WP_REST_Request $request) {
  global $wpdb;

  $season_id = $request['seasonId'];


  $table_name =  $wpdb->prefix . 'bbj_play_season_rel';

  // get all players for the season

  $query = "SELECT * FROM $table_name WHERE season_id = $season_id";

  $players = $wpdb->get_results($query, ARRAY_A);


  return new WP_REST_Response($players, 200);

}


function bbj_update_season_players(WP_REST_Request $request) {
  global $wpdb;

  $data = $request->get_json_params();

  bbj_log2(print_r($data, true));
  $table_name = $wpdb->prefix . 'bbj_play_season_rel';

  $response = [
      'status' => 'success',
      'message' => 'Data processed successfully.'
  ];

  try {
      foreach ($data as $item) {
          $player_id = $item['playerID'];
          $season_id = $item['seasonID'];

          // Check if the row exists for player_id and season_id using wpdb 
          $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE player_id = %d AND season_id = %d", $player_id, $season_id));

          $update_data = [];

          foreach ($item as $key => $value) {
              if ($key != 'playerID' && $key != 'seasonID') {
                  $update_data[$key] = $value;
              }
          }

          if ($exists) {
              $result = $wpdb->update($table_name, $update_data, ['player_id' => $player_id, 'season_id' => $season_id]);

              if ($result === false) {
                  bbj_log2(print_r('last error query', true));
                  bbj_log2(print_r($wpdb->last_error, true));
                  throw new Exception("Failed to update data for player_id: $player_id and season_id: $season_id");
              }
          } else {
              $result = $wpdb->insert($table_name, array_merge($update_data, ['player_id' => $player_id, 'season_id' => $season_id]));

              if (!$result) {
                  bbj_log2(print_r('last error query', true));
                  bbj_log2(print_r($wpdb->last_error, true));
                  throw new Exception("Failed to insert data for player_id: $player_id and season_id: $season_id");
              }
          }
      }
  } catch (\Throwable $th) {

    bbj_log2(print_r($th->getMessage(), true));
      return new WP_REST_Response([
          'status' => 'error',
          'message' => $th->getMessage()
      ], 400); // 400 indicates a bad request error
  }

  return new WP_REST_Response($response, 200); // 200 indicates success
}



function bbj_add_feed_update_rating (WP_REST_Request $request) {

  $post_id = $request['feed_update_id'];
  $rating = $request['rating'];

  if ($rating == 'up') {
    $rating = 1;
  } else if ($rating == 'down') {
    $rating = -1;
  } else {
    return new WP_Error('invalid_rating', 'Invalid rating', array('status' => 400));
  }


  // Check if logged in.  If logged in, get the ID.  Else return error.

  if (!is_user_logged_in()) {
    return new WP_Error('not_logged_in', 'You must be logged in to rate a post', array('status' => 401));
  }
  
  // get user ID 
  $user_id = get_current_user_id();

  // get user IP address
  $ip_address = $_SERVER['REMOTE_ADDR'];

  // check if user has already rated this post
  global $wpdb;
  $table_name = $wpdb->prefix . 'bbj_feed_ratings';
  $query = "SELECT * FROM $table_name WHERE update_id = $post_id AND user_id = $user_id";
  $result = $wpdb->get_results($query);

  if (count($result) > 0) {
    // user has already rated this post
    // update the rating
    $wpdb->update(
      $table_name,
      array(
        'rating' => $rating,
        'ip_address' => $ip_address
      ),
      array(
        'update_id' => $post_id,
        'user_id' => $user_id
      )
    );
  } else {
    // user has not rated this post yet
    // insert a new rating
    $wpdb->insert(
      $table_name,
      array(
        'update_id' => $post_id,
        'user_id' => $user_id,
        'rating' => $rating,
        'ip_address' => $ip_address
      )
    );
  }

  
  // get the total rating for this post
  $query = "SELECT SUM(rating) AS total_rating FROM $table_name WHERE update_id = $post_id";

  $result = $wpdb->get_results($query);

  $total_rating = $result[0]->total_rating;

  // update the post meta

  update_post_meta($post_id, 'total_rating', $total_rating);

  return new WP_REST_Response(array(
    'message' => 'Rating added successfully',
    'total_rating' => $total_rating
  ), 200);




}

function bbj_add_comment (WP_REST_Request $request) {
  bbj_log2(print_r($request, true));

  $post_id = $request['post_id'];
  $comment = $request['comment_text'];

  if (empty($post_id) || empty($comment)) {
    return new WP_Error('missing_parameters', 'Missing post ID or comment text', array('status' => 400));
  }

  if (!get_post($post_id)) {
    return new WP_Error('invalid_post', 'Invalid Post ID', array('status' => 400));
  }

  $commentdata = array(
    'comment_post_ID' => $post_id,
    'comment_content' => $comment,
    'comment_type' => '', // Leave empty for a standard comment
    'comment_parent' => 0, // If it's a reply, pass the parent comment ID here
    'user_id' => get_current_user_id(), // Pass the current user ID here
  );

  $comment_id = wp_new_comment($commentdata);

  // Check for errors
  if (is_wp_error($comment_id)) {
    return $comment_id;
  }

  return new WP_REST_Response('Comment added successfully', 200);

}



function remove_player_season (WP_REST_Request $request) {
  global $wpdb;

  $playerID = $request['player_id'];
  $seasonID = $request['season_id'];

  bbj_log2(print_r($playerID, true));
  bbj_log2(print_r($seasonID, true));

  $table = 'wp_bbj_play_season_rel';

  $success = $wpdb->delete($table, array('player_id' => $playerID, 'season_id' => $seasonID));

  if($success){
    return new WP_REST_Response(array('message' => 'Player removed from season successfully', 'player_id' => $playerID), 200);
  } else {
    return new WP_REST_Response('Failed to remove player from season', 500);
  }
}


function add_player_to_season(WP_REST_Request $request) {
  global $wpdb;

  $playerID = $request['player_id'];
  $seasonID = $request['season_id'];

  bbj_log2(print_r($playerID, true));
  bbj_log2(print_r($seasonID, true));

  $table = 'wp_bbj_play_season_rel';

  $data = array(
    'player_id' => $playerID,
    'season_id' => $seasonID
  );

  $format = array('%d', '%d'); // Assuming both IDs are integers

  $success = $wpdb->insert($table, $data, $format);

  if($success){
    return new WP_REST_Response('Player added to season successfully', 200);
  } else {
    return new WP_REST_Response('Failed to add player to season', 500);
  }
}

function get_season_list (WP_REST_Request $request) {
  global $wpdb; 

  $query = "SELECT * FROM wp_bbj_seasons";

  $seasons = $wpdb->get_results($query, ARRAY_A);

  $response = new WP_REST_Response($seasons);

  $response->set_status(200);

  return $response;
}

function get_player_list (WP_REST_Request $request) {
  global $wpdb;

 // bbj_log2(print_r('player list', true));

  // Query to get players and related season IDs
  $query = "
    SELECT 
      p.*, 
      geo.address_street AS good_addy,
      geo.locality AS good_city,
      geo.administrative_area_level_1 AS good_state,
      geo.lng AS good_lng,
      geo.lat AS good_lat,
      GROUP_CONCAT(rel.season_id) as season_ids
    FROM 
      wp_bbj_players p
    LEFT JOIN 
      wp_bbj_play_season_rel rel ON p.ID = rel.player_id
    LEFT JOIN
      wp_bbj_geo geo ON p.ID = geo.ID
    GROUP BY 
      p.ID
  ";

  // Execute the query
  $players = $wpdb->get_results($query, ARRAY_A);

  

 // bbj_log2(print_r($players, true));

  // Loop to convert comma-separated season_ids to array
  foreach ($players as &$player) {
    if (isset($player['season_ids'])) {
      $player['season_ids'] = explode(',', $player['season_ids']);
    }

    // If the player has an image ID in the 'player_banner' field
    if (isset($player['ID'])) {
      $featured_image_id = get_post_thumbnail_id($player['ID']);
      if ($featured_image_id) {
        $player['featured_image_url'] = wp_get_attachment_url($featured_image_id);
      }
    }
  }

  // Prepare and send the response
  $response = new WP_REST_Response($players);
  $response->set_status(200);

  return $response;
}



function get_player_lookup (WP_REST_Request $request) {

  bbj_log2(print_r('player lookup', true));

  global $wpdb;
  
  $playerID = $request['playerId'];

  if( empty( $playerID ) ) {
    return new WP_Error( 'empty_player_id', 'a player id is required', array('status' => 404) );
  }

  $playerID = intval($playerID);

  $player = $wpdb->get_row($wpdb->prepare(
    "SELECT a.ID, a.first_name, a.last_name, a.official_nickname, a.date_of_birth, a.occupation, a.facebook, a.twitter, a.instagram, a.tiktok, a.profile_picture, b.locality, b.administrative_area_level_1, b.lat, b.lng, b.address_map
    FROM wp_bbj_players  AS a
    LEFT JOIN wp_bbj_geo AS b ON a.ID = b.ID 
    WHERE a.ID = %d", 
    $playerID), ARRAY_A);
  


  if( empty($player) ) {
    return new WP_Error( 'empty_player', 'the player does not exist', array('status' => 404) );
  }



  $response = new WP_REST_Response(array(
    'first_name' => $player['first_name'],
    'last_name' => $player['last_name'],
    'official_nickname' => $player['official_nickname'],
    'date_of_birth' => $player['date_of_birth'],
    'occupation' => $player['occupation'],
    'facebook' => $player['facebook'],
    'twitter' => $player['twitter'],
    'instagram' => $player['instagram'],
    'tiktok' => $player['tiktok'],
    'profile_picture' => $player['profile_picture'],
    'city' => $player['locality'],
    'state' => $player['administrative_area_level_1'],
    'lat' => $player['lat'],
    'lng' => $player['lng'],
    'address_map' => $player['address_map'],


    
));

  $response->set_status(200);

  return $response;





}


function get_season_lookup(WP_REST_Request $request ) {
  global $wpdb;

  // Log for debugging

  // Extract seasonId from the request
  $seasonId = $request['seasonId'];

  // Check if seasonId is available
  if( empty( $seasonId ) ) {
    return new WP_Error( 'empty_season_id', 'a season id is required', array('status' => 404) );
  }

  // Sanitize and validate seasonId before using it in query
  $seasonId = intval($seasonId);
  
  // Query to get the season data
  $season = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_bbj_seasons WHERE ID = %d", $seasonId), ARRAY_A);

  // Log for debugging

  // Check if season exists
  if( empty($season) ) {
    return new WP_Error( 'empty_season', 'the season does not exist', array('status' => 404) );
  }

  // pull the featured image from the post
  $season['season_picture'] = get_the_post_thumbnail_url($seasonId);

  // Prepare the response
  $response = new WP_REST_Response(array(
    'full_name' => $season['full_name'],
    'start_date' => $season['start_date'],
    'end_date' => $season['end_date'],
    'abbrv' => $season['abbreviation'],
    'season_num' => $season['season_number'],
    'featured_image' => $season['season_picture'],
    
));

  $response->set_status(200);

  return $response;
}






// function update_feed(WP_REST_Request $request) {

//   bbj_log3(print_r('UPDATE', true));
  
//   $title = $request->get_param('title');
//   $content = $request->get_param('content');
//   $image = $request->get_file_params()['image'];

//   $post_id = wp_insert_post([
//     'post_title'    => $title,
//     'post_content'  => $content,
//     'post_status'   => 'publish',
//     'post_type'     => 'live-feed-updates',
//   ]);

  

//   if ($post_id) {
//     // Handle the image upload
//     require_once(ABSPATH . 'wp-admin/includes/file.php');
//     require_once(ABSPATH . 'wp-admin/includes/image.php');
//     require_once(ABSPATH . 'wp-admin/includes/media.php');

   

//     $upload_overrides = ['test_form' => false];
//         // see exactly what file-array you got
//     $upload = wp_handle_upload( $image, $upload_overrides );
//        // see success or WP_Error/‘error’

//     if ($upload && !isset($upload['error'])) {
//       $filetype = wp_check_filetype(basename($upload['file']), null);
//       $attachment = [
//         'post_mime_type' => $filetype['type'],
//         'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
//         'post_content'   => '',
//         'post_status'    => 'inherit'
//       ];

//       $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

//       $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);

//       wp_update_attachment_metadata($attach_id, $attach_data);

//       set_post_thumbnail($post_id, $attach_id);


       
//     }
//           /**
//            * This action clears Breeze's internal cache and also
//            * purges Varnish, exactly like clicking "Purge All Cache".
//            */
//    bbj_log3(print_r('clearing BREEZE', true));
//     do_action( 'breeze_clear_all_cache' );
     
//     bbj_log3(print_r('D', true));

//     return new WP_REST_Response("Post created with ID: $post_id", 200);
//   } else {
//     return new WP_Error('create_error', 'Could not create post.', ['status' => 500]);
//   }
// }


function create_bigbrother_season(WP_REST_Request $request) {
 global $wpdb;

  $fullName = $request['full_name'];
  $startDate = $request['start_date'];
  $endDate = $request['end_date'];
  $abbrv = $request['abbrv'];
  $seasonNum = $request['season_num'];
  $featuredImage = $request->get_file_params()['featured_image'];

  
  $postName = sanitize_title_with_dashes($fullName);

  $existingSeasonPost = $wpdb->get_row("SELECT * FROM wp_posts WHERE post_name = '$postName'");

  if ($existingSeasonPost) {
    $postID = $existingSeasonPost->ID;
  } else {
    $postData = array(
      'post_author'    => get_current_user_id(),
      'post_date'      => current_time('mysql'),
      'post_date_gmt'  => current_time('mysql', 1),
      'post_title'     => $fullName,
      'post_status'    => 'publish',
      'comment_status' => 'closed',
      'ping_status'    => 'closed',
      'post_name'      => $postName,
      'post_modified'  => current_time('mysql'),
      'post_modified_gmt' => current_time('mysql', 1),
      'post_type'      => 'bigbrother-seasons'
    );

    $postID = wp_insert_post($postData);
  }

  $existingSeason = $wpdb->get_row("SELECT * FROM wp_bbj_seasons WHERE ID = '$postID'");

  $dataToUpdate = array(
    'start_date' => $startDate,
    'end_date' => $endDate,
    'abbreviation' => $abbrv,
    'season_number' => $seasonNum,
    'full_name' => $fullName,
  );

  $datatoAdd = array(
    'ID' => $postID,
    'start_date' => $startDate,
    'end_date' => $endDate,
    'abbreviation' => $abbrv,
    'season_number' => $seasonNum,
    'full_name' => $fullName,
  );

  $attach_id = null;
  if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    $attach_id = media_handle_upload('featured_image', $postID);
  
    if (is_numeric($attach_id)) {
      set_post_thumbnail($postID, $attach_id);
      // Add the attachment ID as post meta information
      update_post_meta($postID, 'season_picture', $attach_id);
    }
  }
  
  // Add 'season_picture' to the array for custom table, assuming the column exists
  $dataToUpdate['season_picture'] = $attach_id;
  $datatoAdd['season_picture'] = $attach_id;
  
  if ($existingSeason) {
    $wpdb->update(
      'wp_bbj_seasons',
      $dataToUpdate,
      array('ID' => $postID)
    );
  } else {
    $wpdb->insert(
      'wp_bbj_seasons',
        $datatoAdd
    );
  }

  if ($postID) {

    $img_url = wp_get_attachment_url($attach_id);


    $response = new WP_REST_Response(array(
      'status' => 'success',
      'message' => $existingSeason ? 'Season updated' : 'Season created',
      'season_id' => $postID,
      'featured_image' => $img_url
    ));
    $response->set_status(200);
    return $response;
  } else {
    $response = new WP_REST_Response(array(
      'status' => 'error',
      'message' => 'Season not created'
    ));
    $response->set_status(500);
    return $response;
  }


}

function create_bigbrother_player(WP_REST_Request $request) {

  global $wpdb;

  $fullName = $request['full_name'];
  $firstName = $request['first_name'];
  $lastName = $request['last_name'];
  $nickName = $request['nick_name'];
  $dob = $request['dob'];
  $gender = $request ['gender'];
  $occupation = $request['occupation'];
  $facebook = $request['facebook'];
  $twitter = $request['twitter'];
  $instagram = $request['instagram'];
  $tiktok = $request['tiktok'];
  $city = $request['city'];
  $state = $request['state'];
  $lat = $request['lat'];
  $lng = $request['lng'];
  $address_map = $lat . ',' . $lng;



  $postName = sanitize_title_with_dashes($firstName . '-' . $lastName);

  // bbj_log2(print_r($fullName, true));
  // bbj_log2(print_r($firstName, true));
  // bbj_log2(print_r($lastName, true));
  // bbj_log2(print_r($nickName, true));
  // bbj_log2(print_r($dob, true));
  // bbj_log2(print_r($gender, true));
  // bbj_log2(print_r($occupation, true));
  // bbj_log2(print_r($facebook, true));
  // bbj_log2(print_r($twitter, true));
  // bbj_log2(print_r($instagram, true));
  // bbj_log2(print_r($tiktok, true));

  $existingPlayerPost = $wpdb->get_row("SELECT * FROM wp_posts WHERE post_name = '$postName'");

  if ($existingPlayerPost) {
    $postID = $existingPlayerPost->ID;
  } else {
    $postData = array(
      'post_author'    => get_current_user_id(),
      'post_date'      => current_time('mysql'),
      'post_date_gmt'  => current_time('mysql', 1),
      'post_title'     => $fullName,
      'post_status'    => 'publish',
      'comment_status' => 'closed',
      'ping_status'    => 'closed',
      'post_name'      => $postName,
      'post_modified'  => current_time('mysql'),
      'post_modified_gmt' => current_time('mysql', 1),
      'post_type'      => 'bigbrother-players'
    );

    $postID = wp_insert_post($postData);
  }

  $existingPlayer = $wpdb->get_row("SELECT * FROM wp_bbj_players WHERE ID = '$postID'");

  $dataToUpdate = array(
    'first_name' => $firstName,
    'last_name' => $lastName,
    'official_nickname' => $nickName,
    'date_of_birth' => $dob,
    'player_gender' => $gender,
    'occupation' => $occupation,
    'facebook' => $facebook,
    'twitter' => $twitter,
    'instagram' => $instagram,
    'tiktok' => $tiktok,
  );

  $datatoAdd = array(
    'ID' => $postID,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'official_nickname' => $nickName,
    'date_of_birth' => $dob,
    'player_gender' => $gender,
    'occupation' => $occupation,
    'facebook' => $facebook,
    'twitter' => $twitter,
    'instagram' => $instagram,
    'tiktok' => $tiktok,
  );

  $attach_id = null;
  if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    $attach_id = media_handle_upload('featured_image', $postID);
    
    if (is_numeric($attach_id)) {
      set_post_thumbnail($postID, $attach_id);
      // Add the attachment ID as post meta information
      update_post_meta($postID, 'featured_image_id', $attach_id);
    }
  }
  
  // Add 'profile_picture' to the array for custom table
  $dataToUpdate['profile_picture'] = $attach_id;
  $datatoAdd['profile_picture'] = $attach_id;
  

  if ($existingPlayer) {
    $wpdb->update(
      'wp_bbj_players',
      $dataToUpdate,
      array('ID' => $postID)
    );
  } else {
    $wpdb->insert(
      'wp_bbj_players',
        $datatoAdd
    );
  }

  update_or_insert_geo($postID, $city, $state, $lat, $lng);

  if ($postID) {
    $response = new WP_REST_Response(array(
      'status' => 'success',
      'message' => $existingPlayer ? 'Player updated' : 'Player created',
      'player_id' => $postID
    ));
    $response->set_status(200);
    return $response;
  } else {
    $response = new WP_REST_Response(array(
      'status' => 'error',
      'message' => 'Player not created'
    ));
    $response->set_status(500);
    return $response;
  }
}


function update_or_insert_geo($playerID, $city, $state, $lat, $lng) {
  global $wpdb;
  $tableName = 'wp_bbj_geo';

  $existingGeo = $wpdb->get_row("SELECT * FROM $tableName WHERE ID = '$playerID'");

  bbj_log2(print_r($lat, true));
  bbj_log2(print_r($lng, true));

  $address_map = $lat . ',' . $lng;

  bbj_log2(print_r($address_map, true));
  bbj_log2(print_r($playerID, true));

  $geoData = array(
      'ID' => $playerID,
      'locality' => $city,
      'administrative_area_level_1' => $state,
      'lat' => $lat,
      'lng' => $lng,
      'address_map' => $address_map
  );

  if ($existingGeo) {
    $updated = $wpdb->update(
        $tableName,
        $geoData,
        array('ID' => $playerID)
    );
    
    if ( false === $updated ) {
        bbj_log2('Update failed.');
    } else {
        bbj_log2('Update succeeded.');
    }
} else {
    $inserted = $wpdb->insert(
        $tableName,
        $geoData
    );

    if ( false === $inserted ) {
        bbj_log2('Insert failed.');
    } else {
        bbj_log2('Insert succeeded.');
    }
}

if ( $wpdb->last_error ) {
    bbj_log2( "Last error: " . $wpdb->last_error );
}

  bbj_log2(print_r($wpdb->last_query, true));
}
