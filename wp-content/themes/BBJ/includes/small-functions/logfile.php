<?php


add_filter('jwt_auth_token_before_sign', 'add_custom_fields_to_jwt', 10, 2);

function add_custom_fields_to_jwt($token, $user) {

  global $wpdb;
  // get some basic information on the user to use later 

  // get total comment count 
  $args = array(
    'user_id' => $user->ID,
    'count' => true
  );
  $comment_count = get_comments($args);

  // get total post likes 

  $post_likes = get_likes_dislikes($user->ID, "like");
  $post_dislikes = get_likes_dislikes($user->ID, "dislike");
 




  // update meta info for the user

  $total_votes = $post_likes + $post_dislikes;


  update_user_meta($user->ID, 'bbj_total_comments', $comment_count);
  update_user_meta($user->ID, 'bbj_total_likes', $post_likes);
  update_user_meta($user->ID, 'bbj_total_dislikes', $post_dislikes);
  update_user_meta($user->ID, 'bbj_total_votes', $total_votes);
  
  // Get the assigned ranks
  $user_rank = assign_user_rank($user->ID, $total_votes);
  $special_rank = assign_special_rank($user->ID, $total_votes, $post_likes, $post_dislikes);

  // Update user meta with the assigned ranks
  update_user_meta($user->ID, 'bbj_user_rank', $user_rank);
  update_user_meta($user->ID, 'bbj_special_rank', $special_rank);


  $token['nonce'] = wp_create_nonce('wp_rest');
  $token['user_id'] = $user->ID;
  $token['user_email'] = $user->user_email;
  $token['user_nicename'] = $user->user_nicename;
  $token['user_display_name'] = $user->display_name;
  $token['user_roles'] = $user->roles; // Include all roles as an array
  $token['user_avatar'] = get_avatar_url($user->ID); // Get avatar URL,
  $token['comment_count'] = $comment_count; // Get total comment count
  $token['post_likes'] = $post_likes; // Get total post likes
  $token['post_dislikes'] = $post_dislikes; // Get total post dislikes
  $token['total_votes'] = $total_votes; // Get total post votes
  $token['user_rank'] = $user_rank; // Get user rank
  $token['special_rank'] = $special_rank; // Get special rank
    return $token;
}



function assign_user_rank($user_id, $comment_count) {
  // Initialize the rank with a default value

  $rank = "Newbie";

  // Check the conditions using an if-else ladder
  if ($user_id == 1 || $user_id == 9654) {
      $rank = "Big Boss";
  } elseif ($user_id == 28456 || $user_id == 48545 || $user_id == 46398){
      $rank = "Admin";
  }   
  elseif ($comment_count >= 0 && $comment_count <= 99) {
      $rank = "Newbie";
  } elseif ($comment_count >= 100 && $comment_count <= 499) {
      $rank = "Bronze Contributor";
  } elseif ($comment_count >= 500 && $comment_count <= 999) {
      $rank = "Silver Contributor";
  } elseif ($comment_count >= 1000 && $comment_count <= 4999) {
      $rank = "Gold Contributor";
  } elseif ($comment_count >= 5000 && $comment_count <= 9999) {
      $rank = "Platinum Contributor";
  } elseif ($comment_count >= 10000) {
      $rank = "Diamond Contributor";
  }


  // Return the assigned rank
  return $rank;
}


function assign_special_rank($user_id, $comment_count, $likes, $dislikes) {


  // Initialize the rank with a default value
  $special_rank = "Quiet";

  // Check if the user has at least 100 reactions
  if ($comment_count < 100) {
      $special_rank = "Quiet";
  } else {
      // Calculate the like percentage
      $like_percentage = ($likes / $comment_count) * 100;

      // Determine the rank based on the like percentage
      if ($like_percentage >= 0 && $like_percentage <= 50) {
          $special_rank = "Trollin";
      } elseif ($like_percentage >= 51 && $like_percentage <= 60) {
          $special_rank = "Neutral";
      } elseif ($like_percentage >= 61 && $like_percentage <= 70) {
          $special_rank = "Likeable";
      } elseif ($like_percentage >= 71 && $like_percentage <= 80) {
          $special_rank = "Loved";
      } elseif ($like_percentage >= 81 && $like_percentage <= 90) {
          $special_rank = "Adored";
      } elseif ($like_percentage >= 91 && $like_percentage <= 100) {
          $special_rank = "Worshipped";
      }
  }


  // Return the assigned rank
  return $special_rank;
}



function get_likes_dislikes($user_id, $type = "like") {
  global $wpdb;
  $post_like_table = $wpdb->prefix . 'wc_users_voted';
  $voteQuery = "`vote_type` != 0";
  if ($type == "like") {
      $voteQuery = "`vote_type` = 1";
  } else if ($type == "dislike") {
      $voteQuery = "`vote_type` = -1";
  }
  // Ensure user_id is treated as a string
  $sql = $wpdb->prepare("SELECT COUNT(*) FROM $post_like_table WHERE `user_id` = %s AND $voteQuery", $user_id);
  return $wpdb->get_var($sql);
}


function bbj_log($log_msg)
{
  $log_filename = $_SERVER["DOCUMENT_ROOT"] . "/wp-content/themes/BBJ/logs";
  if (!file_exists($log_filename)) {
    // create directory/folder uploads.
    mkdir($log_filename, 0777, true);
  }
  $log_file_data = $log_filename . "/log_" . date("d-M-Y") . ".log";
  file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
}
