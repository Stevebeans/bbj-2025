<?php

add_action("rest_api_init", "bbj_routes");

function bbj_routes()
{
  register_rest_route("bbj/v1", "player_info", [
    "methods" => "GET",
    "callback" => "player_info",
    "permission_callback" => "__return_true",
  ]);

  register_rest_route('bbj/v1', 'next_spoiler_bar', [
    'methods' => 'GET',
    'callback' => 'next_spoiler_bar',
    'args' => array(
      'season' => array(
        'validate_callback' => function ($param, $request, $key) {
          return is_numeric($param);
        },
        'sanitize_callback' => 'absint',
        'default' => 0, // default value if not provided
      ),
    ),
  ]);

  register_rest_route('bbj/v1', 'comment_vote', array(
    'methods' => 'POST',
    'callback' => 'comment_vote',
    'permission_callback' => function () {
      return is_user_logged_in(); 
    }
  ));
  // register public route for feed updates and posts for the front end

  register_rest_route("bbj/v1", "main_feed", [
    "methods" => "GET",
    "callback" => "bbj_feed"
  ]);

  register_rest_route('bbj/v1', 'feed_updates', array(
    'methods' => 'GET',
    'callback' => 'bbj_feed_updates',
    'args' => array(
        'per_page' => array(
            'validate_callback' => function ($param, $request, $key) {
                return is_numeric($param);
            },
            'sanitize_callback' => 'absint',
            'default' => 10, // default value if not provided
        ),
        'offset' => array(
            'validate_callback' => function ($param, $request, $key) {
                return is_numeric($param);
            },
            'sanitize_callback' => 'absint',
            'default' => 0, // default value if not provided
        ),
    ),
));

// get blog posts for front end


  register_rest_route('bbj/v1', 'blog_posts', array(
    'methods' => 'GET',
    'callback' => 'bbj_blog_posts',
    'args' => array(
        'per_page' => array(
            'validate_callback' => function ($param, $request, $key) {
                return is_numeric($param);
            },
            'sanitize_callback' => 'absint',
            'default' => 10, // default value if not provided
        ),
        'offset' => array(
            'validate_callback' => function ($param, $request, $key) {
                return is_numeric($param);
            },
            'sanitize_callback' => 'absint',
            'default' => 0, // default value if not provided
        ),
    ),
  ));


  // get single page / post information 

  register_rest_route('bbj/v1', 'single_page', array(
    'methods' => 'GET',
    'callback' => 'bbj_single_page',
    'args' => array(
        'slug' => array(
            'validate_callback' => function ($param, $request, $key) {
                return is_string($param);
            },
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '', // default value if not provided
        ),
    ),
  ));

  register_rest_route('bbj/v1', 'single_posts', array(
    'methods' => 'GET',
    'callback' => 'bbj_single_posts',    
  ));


  // get comment information for a single page / post

  register_rest_route('bbj/v1', 'bbj_comments', array(
    'methods' => 'GET',
    'callback' => 'bbj_comments',
    'args' => array(
        'post_id' => array(
            'validate_callback' => function ($param, $request, $key) {
                return is_numeric($param);
            },
            'sanitize_callback' => 'absint',
            'default' => 0, // default value if not provided
        ),
    ),
    
  ));  // create route for posting a comment 



  register_rest_route('bbj/v1', '/post_comment', array(
    'methods' => 'POST',
    'callback' => 'post_comment',
    'permission_callback' => 'validate_comment_permissions',
    'args' => array(
        'post_id' => array(
            'validate_callback' => 'is_numeric',
            'sanitize_callback' => 'absint',
            'required' => true,
        ),
        'parent_id' => array(
            'validate_callback' => 'is_numeric',
            'sanitize_callback' => 'absint',
            'required' => true,
        ),
        'comment_content' => array(
            'validate_callback' => function($param, $request, $key) {
                return is_string($param);
            },
            'sanitize_callback' => 'sanitize_text_field',
            'required' => true,
        ),
        'user_id' => array(
            'validate_callback' => 'is_numeric',
            'sanitize_callback' => 'absint',
            'required' => true,
        ),
    ),
));

}

function comment_vote(WP_REST_Request $request) {
  global $wpdb;


  $user_id = strval($request->get_param('user_id'));
  // convert user_id to string 
  $comment_id = $request->get_param('comment_id');
  $vote_type = $request->get_param('vote');
  $post_id = $request->get_param('post_id');
  $date = $request->get_param('date');

  // Determine the vote type value


  // Check if the user has already voted on this comment
  $existing_vote = $wpdb->get_row(
      $wpdb->prepare(
          "SELECT * FROM wp_wc_users_voted WHERE user_id = %d AND comment_id = %d",
          $user_id, $comment_id
      )
  );

  if ($existing_vote) {
      // Update the existing vote
      $wpdb->update(
          'wp_wc_users_voted',
          array(
              'vote_type' => $vote_type,
              'date' => $date
          ),
          array(
              'user_id' => $user_id,
              'comment_id' => $comment_id
          ),
          array(
              '%d',
              '%d'
          ),
          array(
              '%d',
              '%d'
          )
      );
  } else {
      // Insert a new vote
      $wpdb->insert(
          'wp_wc_users_voted',
          array(
              'user_id' => $user_id,
              'comment_id' => $comment_id,
              'vote_type' => $vote_type,
              'post_id' => $post_id,
              'date' => $date
          ),
          array(
              '%d',
              '%d',
              '%d',
              '%d',
              '%d'
          )
      );
  }


  // Get all votes for this comment
  $votes = $wpdb->get_results(
      $wpdb->prepare(
          "SELECT user_id, vote_type, date FROM wp_wc_users_voted WHERE comment_id = %d",
          $comment_id
      )
  );

  return new WP_REST_Response(['message' => 'Vote processed successfully', 'votes' => $votes], 200);
}




function post_comment(WP_REST_Request $request) {
  $post_id = $request->get_param('post_id');
  $parent_id = $request->get_param('parent_id');
  $comment_content = $request->get_param('comment_content');
  $user_id = $request->get_param('user_id');

  // bbj_log3("Raw Parameters:");
  // bbj_log3("post_id: " . $post_id);
  // bbj_log3("parent_id: " . $parent_id);
  // bbj_log3("comment_content: " . $comment_content);
  // bbj_log3("user_id: " . $user_id);

  // Sanitize and validate parameters
  $post_id = absint($post_id);
  $parent_id = absint($parent_id);
  $comment_content = sanitize_text_field($comment_content);
  $user_id = absint($user_id);

  // bbj_log3("Sanitized Parameters:");
  // bbj_log3("post_id: " . $post_id);
  // bbj_log3("parent_id: " . $parent_id);
  // bbj_log3("comment_content: " . $comment_content);
  // bbj_log3("user_id: " . $user_id);

  if (empty($post_id) || empty($parent_id) || empty($comment_content) || empty($user_id)) {
      return new WP_REST_Response(array('message' => 'Missing or invalid parameters'), 400);
  }

  $commentdata = array(
      'comment_post_ID' => $post_id,
      'comment_parent' => $parent_id,
      'comment_content' => $comment_content,
      'user_id' => $user_id,
  );

  // bbj_log3("Comment Data:");
  // bbj_log3(print_r($commentdata, true));

  // Insert the comment into the database
  //$comment_id = wp_insert_comment($commentdata);

  if ($comment_id) {
      return new WP_REST_Response(array('message' => 'Comment posted successfully'), 200);
  } else {
      return new WP_REST_Response(array('message' => 'Failed to post comment'), 500);
  }
}


function next_spoiler_bar($data) {

  global $wpdb;
 $currentSeason = rwmb_meta("current_season", ["object_type" => "setting"], "bbj_settings");


      $players = $wpdb->get_results(
        'SELECT sn.*, s.full_name FROM wp_bbj_player_season_new AS sn
      LEFT JOIN wp_bbj_seasons s ON s.ID = sn.ID
        WHERE sn.ID = "' .
          $currentSeason .
          '"'
      );


         $name_map = [
            "afp" => "AFP",
            "winner" => "Winner",
            "hoh" => "HoH",
            "second" => "2ND",
            "pov" => "Veto",
            "jury" => "Jury",
            "nom" => "Nom",
            "evic" => "Evicted",
          ];

$playerList = [];

foreach ($players as $player):
  $unserializedPlayer = unserialize($player->player_list2);

  foreach ($unserializedPlayer as $innerArray):
      $playerId = (int) $innerArray["player_id"];
      
      // Update query to join wp_posts and get the slug
      $playerData = $wpdb->get_row($wpdb->prepare(
          "SELECT p.profile_picture, p.first_name, p.last_name, p.official_nickname AS nick, wp.post_name AS slug 
           FROM wp_bbj_players p 
           LEFT JOIN wp_posts wp ON p.ID = wp.ID 
           WHERE p.ID = %d", 
          $playerId
      ));
      
      $imgUrl = wp_get_attachment_image_src($playerData->profile_picture, "profile-picture");
      $firstName = $playerData->first_name;

      $permaLink = get_permalink($playerId);

      if (is_array($innerArray) && isset($innerArray["current_house_status"])) {
        foreach ($innerArray["current_house_status"] as $status) {
            if (isset($name_map[$status])) {
                $new_value = $name_map[$status];
            } else {
                $new_value = $status;
            }
            $new_names[] = $new_value;
        }
    } else {
        $new_names[] = "Active";
    }

     $houseStatus = $innerArray["current_house_status"] ?? [];


      $playerList[] = [
          'player_id' => $playerId,
          'image' => $imgUrl[0],
          'first_name' => $playerData->first_name,
          'last_name' => $playerData->last_name,
          'nick' => $playerData->nick,
          'slug' => $playerData->slug,  // Add slug to the player list array
          'display_name' => $playerData->first_name,
          'status' => $houseStatus,
          'link' => "/bigbrother-players/" . $playerData->slug
      ];

  endforeach;

endforeach;




      return $playerList;
}

function bbj_comments($data) {
  global $wpdb;

  $data = $data->get_params();
  $post_id = isset($data['post_id']) ? $data['post_id'] : 0;
  $per_page = isset($data['per_page']) ? $data['per_page'] : 20;

  // Get top-level comments for a post
  $comments = get_comments([
      'post_id' => $post_id,
      'status' => 'approve',
      'parent' => 0,
      'orderby' => 'comment_date_gmt',
      'order' => 'ASC',
      'number' => $per_page
  ]);



  if (empty($comments)) {
      return [];
  }

  $user_ids = [];

  // Loop through comments to collect user IDs
  foreach ($comments as $comment) {
      $user_id = $comment->user_id;
      if ($user_id) {
          $user_ids[] = $user_id;
      }
  }

  $user_ids = array_unique($user_ids);

  if (empty($user_ids)) {
      return $comments;
  }

 
  // Map total comment counts to each comment
  $comments = array_map(function($comment)  {
     
      return format_comment($comment);
  }, $comments);

  return $comments;
}



function transfer_data($user_id) {
  global $wpdb;


  $total_comments = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $wpdb->comments WHERE user_id = %d AND comment_approved = '1'",
    $user_id
  ));


  get_google_db_connection();


}


/* 
These are the old comments that I was planning on using google db for.  I still likely will but I'll just eventually run this script for a season then convert it to pulling the data from google next season. 

function bbj_comments($data) {
  global $wpdb;

  $data = $data->get_params();
  $post_id = $data['post_id'];
  $per_page = isset($data['per_page']) ? $data['per_page'] : 20;

  // Get top-level comments for a post
  $comments = get_comments([
    'post_id' => $post_id,
    'status' => 'approve',
    'parent' => 0,
    'orderby' => 'comment_date_gmt',
    'order' => 'ASC',
    'number' => $per_page
  ]);

  $user_ids = [];


  $usersVotedTable = 'wp_wc_users_voted';

  // Loop through comments to collect user IDs
  foreach ($comments as $comment) {
      $user_id = $comment->user_id;
      if ($user_id) {
          $user_ids[] = $user_id;
      }
  }
  $connection = get_google_db_connection();
  $user_ids = array_unique($user_ids);

  // Create an array to store the total comment count for each user
  $user_comment_counts = [];
  $user_like_counts = [];

  foreach ($user_ids as $user_id) {
    $total_comments = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->comments WHERE user_id = %d AND comment_approved = '1'",
        $user_id
    ));
    
    $user_comment_counts[$user_id] = $total_comments;

    $total_upvotes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $usersVotedTable WHERE meta_key = 'like' AND comment_id IN (SELECT comment_ID FROM $wpdb->comments WHERE user_id = %d AND comment_approved = '1')",
        $user_id
    ));

    bbj_log3(print_r($wpdb->last_query, true));

    $user_like_counts[$user_id] = $total_upvotes;


    // Check if the user_id exists in the Google Cloud DB
    $check_stmt = $connection->prepare("SELECT COUNT(*) FROM next_user_list WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_stmt->bind_result($exists);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($exists > 0) {
      // If user_id exists, update the row
      $update_stmt = $connection->prepare("UPDATE next_user_list SET comment_count = ? WHERE user_id = ?");
      $update_stmt->bind_param("ii", $total_comments, $user_id);
      $update_stmt->execute();
      $update_stmt->close();
    } else {
      // If user_id does not exist, insert a new row
      $insert_stmt = $connection->prepare("INSERT INTO next_user_list (user_id, comment_count) VALUES (?, ?)");
      $insert_stmt->bind_param("ii", $user_id, $total_comments);
      $insert_stmt->execute();
      $insert_stmt->close();
    }
  }

  // Close the connection to the Google Cloud DB
  $connection->close();

  // Process comments to include additional data if needed
  $comments = array_map(function($comment) use ($user_comment_counts, $user_like_counts) {
    $user_id = $comment->user_id;
    $comment->total_comments = $user_comment_counts[$user_id];
    $comment->total_likes = $user_like_counts[$user_id];
    $comment_data = format_comment($comment);
    // You can add more data to $comment_data here if needed
    return $comment_data;
  }, $comments);

  return $comments;
} */





function format_comment($comment, $depth = 0) {
  // Limit recursion depth to prevent potential issues with deeply nested comments
  if ($depth > 10) {
      return [];
  }

  $author_id = $comment->user_id;

  if ($author_id == 0) {
      // Attempt to find user by email
      $user = get_user_by('email', $comment->comment_author_email);
      if ($user) {
          $author_id = $user->ID;
      }
  }

  $author_name = get_the_author_meta('display_name', $author_id);
  $author_avatar = get_avatar_url($author_id);
  $likes = getVoteCount($comment->comment_ID, "like");
  $dislikes = getVoteCount($comment->comment_ID, "dislike");
  $allVotes = getAllVotes($comment->comment_ID);

  $comment_ID = $comment->comment_ID;


  
  

  // Get first-level replies
  $replies = get_comments([
      'parent' => $comment->comment_ID,
      'status' => 'approve',
      'orderby' => 'comment_date_gmt',
      'order' => 'ASC'
  ]);

  // Recursively format replies
  $formatted_replies = array_map(function($reply) use ($depth) {
      return format_comment($reply, $depth + 1);
  }, $replies);

  return [
      'comment_ID' => $comment->comment_ID,
      'post_ID' => $comment->comment_post_ID,
      'comment_author' => $comment->comment_author,
      'author_ID' => $author_id,
      'comment_author_avatar' => $author_avatar,
      'comment_date' => $comment->comment_date,
      'comment_content' => $comment->comment_content,
      'comment_parent' => $comment->comment_parent,
      'comment_likes' => $likes,
      'comment_dislikes' => $dislikes,
      'replies' => $formatted_replies,
      'total_comments' => get_user_meta($author_id, 'bbj_total_comments', true),
      'post_likes' => get_user_meta($author_id, 'bbj_total_likes', true),
      'post_dislikes' => get_user_meta($author_id, 'bbj_total_dislikes', true),
      'total_votes' => get_user_meta($author_id, 'bbj_total_votes', true),
      'user_rank' => get_user_meta($author_id, 'bbj_user_rank', true),
      'special_rank' => get_user_meta($author_id, 'bbj_special_rank', true),
      'all_votes' => $allVotes
  ];
}

function add_user_meta_info ($user_id) {


  $args = array(
    'user_id' => $user_id,
    'count' => true
  );
  $comment_count = get_comments($args);


  $post_likes = get_likes_dislikes($user_id, "like");
  $post_dislikes = get_likes_dislikes($user_id, "dislike");
  $total_votes = $post_likes + $post_dislikes;

  $user_rank = assign_user_rank($user_id, $total_votes);
  $special_rank = assign_special_rank($user_id, $total_votes, $post_likes, $post_dislikes);
  

  update_user_meta($user_id, 'bbj_total_comments', $comment_count);
  update_user_meta($user_id, 'bbj_total_likes', $post_likes);
  update_user_meta($user_id, 'bbj_total_dislikes', $post_dislikes);
  update_user_meta($user_id, 'bbj_total_votes', $total_votes);
  update_user_meta($user_id, 'bbj_user_rank', $user_rank);
  update_user_meta($user_id, 'bbj_special_rank', $special_rank);

}

function getVoteCount ($comment_id, $vote_type) {
  global $wpdb;

  //bbj_log3(print_r('get vote count', true));

  $tableName = $wpdb->prefix . "wc_users_voted";

  $voteQuery = "`vote_type` != 0";
  if ($vote_type == "like") {
      $voteQuery = "`vote_type` = 1";
  } else if ($vote_type == "dislike") {
      $voteQuery = "`vote_type` = -1";
  }

  $sql = $wpdb->prepare("SELECT COUNT(`id`) AS `count` FROM $tableName WHERE `comment_id` = %d AND " . $voteQuery . " ORDER BY `id` DESC", $comment_id);

//bbj_log3(print_r($wpdb->last_query, true));
  return $wpdb->get_var($sql);

  

}

function getAllVotes($comment_id) {
  global $wpdb;

  $tableName = $wpdb->prefix . "wc_users_voted";
 

  $sql = $wpdb->prepare("SELECT user_id, vote_type, post_id, comment_id, date FROM $tableName WHERE `comment_id` = %d ORDER BY `id` DESC", $comment_id);

  $votes = $wpdb->get_results($sql, ARRAY_A);


  return $wpdb->get_results($sql, ARRAY_A);

}


// public function getVotes($id, $type, $limit, $offset = 0) {
//   $voteQuery = "`vote_type` != 0";
//   if ($type == "like") {
//       $voteQuery = "`vote_type` = 1";
//   } else if ($type == "dislike") {
//       $voteQuery = "`vote_type` = -1";
//   }
//   $sql = $this->dbm->prepare("SELECT `user_id`, `vote_type` FROM `" . $this->voted . "` WHERE `is_guest` = 0 AND `comment_id` = %d AND " . $voteQuery . " ORDER BY `id` DESC LIMIT %d OFFSET %d", $id, $limit, $offset);
//   return $this->dbm->get_results($sql, ARRAY_A);
// }

// public function getVotesCount($id, $voteType) {
//   $voteQuery = "`vote_type` != 0";
//   if ($voteType == "like") {
//       $voteQuery = "`vote_type` = 1";
//   } else if ($voteType == "dislike") {
//       $voteQuery = "`vote_type` = -1";
//   }
//   $sql = $this->dbm->prepare("SELECT COUNT(`id`) AS `count` FROM `" . $this->voted . "` WHERE `is_guest` = 0 AND `comment_id` = %d AND " . $voteQuery . " ORDER BY `id` DESC", $id);
//   return $this->dbm->get_var($sql);
// }


function bbj_single_posts($data) {


$data = $data->get_params();

$slug = $data['slug'];

// query for a single post 
$query_single_post = new WP_Query([
  "name" => $slug,
  "post_type" => "post",
  "post_status" => "publish"
]);

$single_post = array_map(function($post) {

  // Get the featured thumbnail URL
  $thumbnail_id = get_post_thumbnail_id($post->ID);
  $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'featured-image-header');

  // insert category name and link
  $categories = get_the_category($post->ID);
  $category = $categories[0]->name;
  $category_link = get_category_link($categories[0]->term_id);

  // insert comment counts
  $comment_count = get_comments_number($post->ID);

  // insert author and author avatar
  $author_id = $post->post_author;
  $author_name = get_the_author_meta('display_name', $author_id);
  $author_avatar = get_avatar_url($author_id);


  $formattedSlug = '/' . $post->post_name;

  $post_title = html_entity_decode($post->post_title);

  // Generate custom excerpt
  $post_content = strip_shortcodes($post->post_content);
  $post_content = wp_strip_all_tags($post_content);
  $words = explode(' ', $post_content, 75 + 1);
  if (count($words) > 75) {
    array_pop($words);
    $post_excerpt = implode(' ', $words) . '...';
  } else {
    $post_excerpt = implode(' ', $words);
  }

  return [
    'ID' => $post->ID,
    'slug' => $formattedSlug, 
    'post_title' => $post_title,
    'post_excerpt' => $post_excerpt,
    'content' => $post->post_content,
    'post_date' => $post->post_date,
    'post_modified' => $post->post_modified,
    'thumbnail_url' => $thumbnail_url,
    'post_url' => get_permalink($post->ID),
    'category' => $category,
    'category_link' => $category_link,
    'comment_count' => $comment_count,
    'author_name' => $author_name,
    'author_avatar' => $author_avatar
  ];
}, $query_single_post->posts);

return $single_post;
}


function bbj_single_page($data) {
  global $wpdb;

  $data = $data->get_params();

  $slug = $data['slug'];

 
  $query_single_page = new WP_Query([
    "name" => $slug,
    "post_type" => "post",
    "post_status" => "publish"
  ]);

  $single_page = array_map(function($page) {

    // Get the featured thumbnail URL
    $thumbnail_id = get_post_thumbnail_id($page->ID);

    $imageInfo = wp_get_attachment_metadata($thumbnail_id);
    

    // get thumbnail and full size 
    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
    $page_header = wp_get_attachment_image_url($thumbnail_id, 'featured-image-header');

    // get author name and avatar 
    $author_id = $page->post_author;
    $author_name = get_the_author_meta('display_name', $author_id);
    $author_avatar = get_avatar_url($author_id);

    // get comment count 
    $comment_count = get_comments_number($page->ID);

    // get page or post type 
    $post_type = get_post_type($page->ID);





   

    return [
      'ID' => $page->ID,
      'post_title' => $page->post_title,
      'post_content' => $page->post_content,
      'post_excerpt' => $page->post_excerpt,
      'post_date' => $page->post_date,
      'post_modified' => $page->post_modified,
      'thumbnail_url' => $thumbnail_url,
      'page_header' => $page_header,
      'author_name' => $author_name,
      'author_avatar' => $author_avatar,
      'comment_count' => $comment_count,
      'next_post_type' => $post_type
    ];
  }, $query_single_page->posts);


  return $single_page;
}


function bbj_blog_posts($data) {
  global $wpdb;

  $data = $data->get_params();

  $per_page = $data['per_page'] ? $data['per_page'] : 20;
  $offset = $data['offset'] ? $data['offset'] : 0;
  $excerpt_length = isset($data['excerpt_length']) ? intval($data['excerpt_length']) : 75;
  //$limit = $data['limit'] ? $data['limit'] : 20;

  $post_query = new WP_Query([
    "post_type" => "post",
    "posts_per_page" => $per_page,
    "orderby" => "modified",
    "order" => "DESC",
    "offset" => $offset,
    "post_status" => "publish"
  ]);


  $total_count = $post_query->found_posts;

  $posts = array_map(function($post) use ($excerpt_length) {
    // Get the featured thumbnail URL
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');

    // insert category name and link
    $categories = get_the_category($post->ID);
    $category = $categories[0]->name;
    $category_link = get_category_link($categories[0]->term_id);

    // insert comment counts
    $comment_count = get_comments_number($post->ID);


    // insert author and author avatar
    $author_id = $post->post_author;
    $author_name = get_the_author_meta('display_name', $author_id);
    $author_avatar = get_avatar_url($author_id);

    $formattedSlug = '/' . $post->post_name;

    $post_title = html_entity_decode($post->post_title);

     // Generate custom excerpt
     $post_content = strip_shortcodes($post->post_content);
     $post_content = wp_strip_all_tags($post_content);
     $words = explode(' ', $post_content, $excerpt_length + 1);
     if (count($words) > $excerpt_length) {
       array_pop($words);
       $post_excerpt = implode(' ', $words) . '...';
     } else {
       $post_excerpt = implode(' ', $words);
     }

   

    return [
      'ID' => $post->ID,
      'slug' => $formattedSlug, 
      'post_title' => $post_title,
      'post_excerpt' => $post_excerpt,
      'post_date' => $post->post_date,
      'post_modified' => $post->post_modified,
      'thumbnail_url' => $thumbnail_url,
      'post_url' => get_permalink($post->ID),
      'category' => $category,
      'category_link' => $category_link,
      'comment_count' => $comment_count,
      'author_name' => $author_name,
      'author_avatar' => $author_avatar
    ];
  }, $post_query->posts);



  return [
    "posts" => $posts,
    "total_count" => $total_count
  ];

}




function bbj_feed_updates($data) {
  global $wpdb;

  $data = $data->get_params();



  $per_page = $data['per_page'] ? $data['per_page'] : 20;
  $offset = $data['offset'] ? $data['offset'] : 0;
  // $limit = $data['limit'] ? $data['limit'] : 20;


  $query_feed_updates = new WP_Query([
    "post_type" => "live-feed-updates",
    "posts_per_page" => $per_page,
    "orderby" => "modified",
    "order" => "DESC",
    "offset" => $offset,
    "post_status" => "publish"
  ]);

  $total_count = $query_feed_updates->found_posts;

///bbj_log3(print_r($query_feed_updates, true));


  $feed_updates = array_map(function($feed_update) {
    // Get the featured thumbnail URL
    $thumbnail_id = get_post_thumbnail_id($feed_update->ID);
    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');

    // get author name and avatar
    $author_id = $feed_update->post_author;
    $author_name = get_the_author_meta('display_name', $author_id);
    $author_avatar = get_avatar_url($author_id);

    // if Hillary, rename her to Hillary  
    if ($author_name == "hillaryd") {
      $author_name = "Hillary";
    }

    // Build Slug 
    $page_slug = "live-feed-updates/" . $feed_update->post_name;

    

    return [
      'ID' => $feed_update->ID,
      'post_title' => $feed_update->post_title,
      'post_content' => $feed_update->post_content,
      'post_excerpt' => $feed_update->post_excerpt,
      'post_date' => $feed_update->post_date,
      'post_modified' => $feed_update->post_modified,
      'thumbnail_url' => $thumbnail_url,
      'author_name' => $author_name,
      'author_avatar' => $author_avatar,
      'post_url' => get_permalink($feed_update->ID),
      'page_slug' => $page_slug
    ];
  }, $query_feed_updates->posts);

  // return feed updates
  return [
    "feed_updates" => $feed_updates,
    "total_count" => $total_count
  ];
}


function bbj_feed($data) {
  global $wpdb;

  $query_posts = new WP_Query([
    "post_type" => "post",
    "posts_per_page" => 20, // Changed from 10 to 20
    "orderby" => "date",
    "order" => "DESC",
    "post_status" => "publish",
    
  ]);

  $posts = array_map(function($post) {
    // Get the featured thumbnail URL
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');

    return [
      'ID' => $post->ID,
      'post_title' => $post->post_title,
      'post_content' => $post->post_content,
      'post_excerpt' => $post->post_excerpt,
      'post_date' => $post->post_date,
      'post_modified' => $post->post_modified,
      'thumbnail_url' => $thumbnail_url
    ];
  }, $query_posts->posts);

  $query_feed_updates = new WP_Query([
    "post_type" => "live-feed-updates",
    "posts_per_page" => 20,
    "orderby" => "modified",
    "order" => "DESC",
    "post_status" => "publish"
  ]);

  $feed_updates = array_map(function($feed_update) {
    // Get the featured thumbnail URL
    $thumbnail_id = get_post_thumbnail_id($feed_update->ID);
    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');

    return [
      'ID' => $feed_update->ID,
      'post_title' => $feed_update->post_title,
      'post_content' => $feed_update->post_content,
      'post_excerpt' => $feed_update->post_excerpt,
      'post_date' => $feed_update->post_date,
      'post_modified' => $feed_update->post_modified,
      'thumbnail_url' => $thumbnail_url
    ];
  }, $query_feed_updates->posts);

  // return feed updates and posts
  return [
    "feed_updates" => $feed_updates,
    "posts" => $posts
  ];
}



function player_info($data)
{
  global $wpdb;
  $sql = "SELECT
  wwbj.first_name, wwbj.last_name, wwbj.profile_picture, wwbj.ID AS playerID, wwbj.date_of_birth, wwbj.player_gender,
  stats.*, seasons.`ID` AS seasonID, seasons.start_date, seasons.end_date, seasons.full_name, geo.locality
  FROM wp_bbj_players AS wwbj
  LEFT JOIN `wp_mb_relationships` 
      ON (wwbj.`ID` = `wp_mb_relationships`.`from`)
  LEFT JOIN wp_bbj_seasons AS seasons
      ON (seasons.`ID` = `wp_mb_relationships`.`to`)
  LEFT JOIN wp_bbj_player_season_stats AS stats 
      ON (stats.`ID` = wwbj.`ID`)
  LEFT JOIN wp_bbj_geo AS geo 
      ON (geo.`ID` = wwbj.`ID`)
  ORDER BY wwbj.first_name ASC; ";

  $players = $wpdb->get_results($sql);

  $playerTable = [];

  foreach ($players as $p):
    $dob = $p->date_of_birth;
    $showStart = $p->start_date;
    $showEnd = $p->end_date;

    $imgUrl = wp_get_attachment_image_src($p->profile_picture, "profile-picture");

    array_push($playerTable, [
      "profile" => $imgUrl[0],
      "first_name" => $p->first_name,
      "last_name" => $p->last_name,
      "player_link" => get_permalink($p->playerID),
      "season" => $p->full_name,
      "hoh_wins" => $p->hohwins ? $p->hohwins : 0,
      "pov_wins" => $p->povwins ? $p->povwins : 0,
      "misc_wins" => $p->miscwins ? $p->miscwins : 0,
      "nom" => $p->nominated ? $p->nominated : 0,
      "saved" => $p->saved_block ? $p->saved_block : 0,
      "finished" => $p->place_finished ? $p->place_finished : "",
      "current_age" => $dob ? current_age($dob) : "",
      "then_age" => $dob ? show_age($dob, $showStart) : "",
      "start_date" => $p->start_date,
      "end_date" => $p->end_date,
      "location" => $p->locality,
      "gender" => $p->player_gender,
    ]);
  endforeach;

  return $playerTable;
}
