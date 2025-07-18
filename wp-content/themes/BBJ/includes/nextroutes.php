<?php 

// Page for all the routes that are going to the Next.js app

require_once get_template_directory() . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Function to validate the JWT token
function custom_validate_token($request) {
    $token = $request->get_param('token');
    $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;

        if (!$secret_key) {
        return new WP_Error('jwt_auth_bad_config', 'JWT is not configured properly', array('status' => 403));
    }

    try {
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        $user = get_userdata($decoded->data->user->id);


        if (!$user) {
            return new WP_Error('jwt_auth_invalid_user', 'User does not exist', array('status' => 403));
        }

        return [
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'user_display_name' => $user->display_name,
            'user_nicename' => $user->user_nicename,
            'user_roles' => $user->roles,
        ];
    } catch (Exception $e) {
        return new WP_Error('jwt_auth_invalid_token', $e->getMessage(), array('status' => 403));
    }
}

// Register the custom validate-token route
add_action('rest_api_init', function () {
    register_rest_route('bbj/v3', '/validate-token', array(
        'methods' => 'POST',
        'callback' => 'custom_validate_token',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('bbj/v3', 'pull_comments', array(
        'methods' => 'GET',
        'callback' => 'pull_comments',
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


    // pull comment vote 
    register_rest_route('bbj/v3', 'pull_comment_vote', array(
        'methods' => 'GET',
        'callback' => 'getVotes',
        'args' => array(
            'comment_id' => array(
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                },
                'sanitize_callback' => 'absint',
                'default' => 0, // default value if not provided
            ),
        ),        
    ));  // create route for posting a comment

    // get nonce 
    register_rest_route('bbj/v3', 'get-nonce', array(
        'methods' => 'GET',
        'callback' => 'getNonce',
    ));


   

    register_rest_route('bbj/v3', '/store-update', array(
        'methods' => 'POST',
        'callback' => 'handle_feed_update',
        'permission_callback' => 'check_feed_update_permissions'
    ));

    register_rest_route('bbj/v3', '/get-tables', array(
        'methods' => 'GET',
        'callback' => 'get_db_tables'
    ));

    register_rest_route('bbj/v3', '/create-tables', array(
        'methods' => 'POST',
        'callback' => 'post_db_tables',
        'permission_callback' => function ($request) {
        // Verify the JWT token
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return new WP_REST_Response('Unauthorized', 401);
        }

        return true;
        }
    ));


    register_rest_route( 'bbj/v3', '/add-edit-season', array(
        'methods' => 'POST',
        'callback' => 'add_edit_season',
        'permission_callback' => function ($request) {
            // Verify the JWT token
            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                return new WP_REST_Response('Unauthorized', 401);
            }
    
            return true;
        }
    ));   


    

});





function add_edit_season(WP_REST_Request $request) {
    global $wpdb;

    //bbj_log3(print_r($request, true));


    // Get parameters from the request
    $type = $request->get_param('type');
    $season_id = $request->get_param('season'); // Assuming this is the post ID
    $season_name = sanitize_text_field($request->get_param('full_name'));
    $season_number = sanitize_text_field($request->get_param('season_number'));
    $abbreviation = sanitize_text_field($request->get_param('abbreviation'));
    $start_date = sanitize_text_field($request->get_param('start_date'));
    $end_date = sanitize_text_field($request->get_param('end_date'));

    if ($type === 'editseason' && $season_id) {
        try {
            // Update custom table: bbj_seasons
            $updated = $wpdb->update(
                $wpdb->prefix . 'bbj_seasons',
                array(
                    'full_name' => $season_name,
                    'season_number' => $season_number,
                    'abbreviation' => $abbreviation,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ),
                array('ID' => $season_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            if (false === $updated) {
                throw new Exception('Failed to update custom season table.');
            }

            // Update the wp_posts table to reflect the modification
            $post_update = array(
                'ID' => $season_id, // Post ID is the same as season ID
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1),
            );

            $post_id = wp_update_post($post_update, true);

            if (is_wp_error($post_id)) {
                throw new Exception('Failed to update post table: ' . $post_id->get_error_message());
            }

            return new WP_REST_Response('Season updated successfully', 200);

        } catch (Exception $e) {
            return new WP_Error('database_error', 'Failed to update season: ' . $e->getMessage(), array('status' => 500));
        }
    }

    // If not editseason or season_id not provided
    return new WP_Error('invalid_request', 'Invalid request parameters', array('status' => 400));
}


function getNonce($request) {
    return wp_create_nonce('wp_rest');
}

function post_db_tables(WP_REST_Request $request) {
    global $wpdb;

    // Get table name and sanitize
    $tableName = sanitize_text_field($request->get_param('table'));
    $tableName = $wpdb->prefix . $tableName; // Adds wp_ or the table prefix set in wp-config.php
    $tableData = $request->get_param('data');


    // Check if table exists
    $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") === $tableName;

    if ($tableExists) {
        // If table exists, check and add missing fields
        $existingColumns = $wpdb->get_results("SHOW COLUMNS FROM {$tableName}", ARRAY_A);
        $existingColumnNames = array_column($existingColumns, 'Field');

        // Loop through table data and check each column
        foreach ($tableData as $column) {
            if (!in_array($column['name'], $existingColumnNames)) {
                // If column does not exist, add it
                $columnName = sanitize_text_field($column['name']);
                $columnType = sanitize_text_field($column['type']);
                $wpdb->query("ALTER TABLE {$tableName} ADD {$columnName} {$columnType}");
                bbj_log3("Added column {$columnName} to table {$tableName}");
            }
        }
    } else {
        // If table does not exist, create it with the provided fields
        $columnsSQL = [];
        $primaryKeys = [];
        $uniqueKeys = [];

        foreach ($tableData as $column) {
            $columnName = sanitize_text_field($column['name']);
            $columnType = sanitize_text_field($column['type']);

            if (isset($column['key'])) {
                // Add key constraints (Primary Key, Unique Key, etc.)
                if ($column['key'] === 'UN PK') {
                    $columnType .= ' AUTO_INCREMENT';
                    $primaryKeys[] = $columnName;
                    $uniqueKeys[] = $columnName; // Add to unique keys array
                } elseif ($column['key'] === 'FK') {
                    // Handle foreign key constraints if necessary
                    $columnType .= ''; // You may need to add FK constraints logic here
                }
            }

            $columnsSQL[] = "{$columnName} {$columnType}";
        }

        // Add primary key and unique constraints to the SQL statement
        if (!empty($primaryKeys)) {
            $columnsSQL[] = "PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
        }

        if (!empty($uniqueKeys)) {
            foreach ($uniqueKeys as $uniqueKey) {
                $columnsSQL[] = "UNIQUE KEY {$uniqueKey} ({$uniqueKey})";
            }
        }

        // Create the table SQL
        $createTableSQL = "CREATE TABLE {$tableName} (" . implode(", ", $columnsSQL) . ") CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $wpdb->query($createTableSQL);
        bbj_log3("Created table {$tableName}");
    }

    // Return response
    return new WP_REST_Response(array('success' => true, 'message' => 'Table or fields created successfully.'), 200);
}




function get_db_tables($request) {
    global $wpdb;

    // Array to store table schemas
    $schemas = [];

    // Get all tables in the current WordPress database
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

    // Iterate over each table
    foreach ($tables as $table) {
        $table_name = $table[0]; // Extract the table name

        // Get column details for the current table
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);

        // Store the schema details in an associative array
        $schemas[$table_name] = array_map(function ($column) {
            return [
                'name' => $column['Field'],
                'type' => $column['Type'],
                'key'  => $column['Key']
            ];
        }, $columns);
    }

    return $schemas;
}


function check_feed_update_permissions($request) {
    // Get the user ID from the request
    $user_id = $request->get_param('user_id');

    // Verify the user exists
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('invalid_user', 'Invalid user ID.', array('status' => 400));
    }

    // Check if user has 'administrator' role or 'updater' role
    if (!(in_array('administrator', (array) $user->roles) || in_array('updater', (array) $user->roles))) {
        return new WP_Error('rest_forbidden', 'You do not have permission to update the feed.', array('status' => 403));
    }

    return true;
}

function handle_feed_update($request) {
    global $wpdb;

    // Get parameters from request
    $params = $request->get_params();

    $mainUpdate = $params['mainText'];
    $additionalUpdate = $params['additionalText'];
    $userID = $params['user_id'];

    return 'hi';
    
    // $main_text = sanitize_text_field($params['main_text']);
    // $additional_text = wp_kses_post($params['additional_text']);
    // $image_url = esc_url_raw($params['image_url']);

    // // Store update in the WordPress posts table (assuming this is your current method)
    // $post_id = wp_insert_post(array(
    //     'post_title'    => wp_strip_all_tags($main_text),
    //     'post_content'  => $additional_text,
    //     'post_status'   => 'publish',
    //     'post_author'   => get_current_user_id(),
    //     'post_type'     => 'feed_update'  // Assuming you have a custom post type for feed updates
    // ));

    // if (is_wp_error($post_id)) {
    //     return new WP_Error('insert_failed', 'Failed to insert feed update into posts table', array('status' => 500));
    // }

    // // Add the image URL as post meta
    // if (!empty($image_url)) {
    //     add_post_meta($post_id, 'feed_update_image', $image_url);
    // }

    // // Store update in the new custom table
    // $table_name = $wpdb->prefix . 'feed_updates';  // Make sure this table exists
    // $result = $wpdb->insert(
    //     $table_name,
    //     array(
    //         'main_text' => $main_text,
    //         'additional_text' => $additional_text,
    //         'image_url' => $image_url,
    //         'user_id' => get_current_user_id(),
    //         'created_at' => current_time('mysql')
    //     ),
    //     array('%s', '%s', '%s', '%d', '%s')
    // );

    // if ($result === false) {
    //     return new WP_Error('insert_failed', 'Failed to insert feed update into custom table', array('status' => 500));
    // }

    // return new WP_REST_Response(array('message' => 'Feed update stored successfully'), 200);


}






// Create a test route to check if the server is running
add_action('rest_api_init', function () {
    register_rest_route('bbj/v3', '/test', array(
        'methods' => 'GET',
        'callback' => function () {
            return 'Server is running';
        },
        'permission_callback' => '__return_true',
    ));
});



function pull_comment_vote($request) {

    
}


// Function to pull comments from the database
function pull_comments($request) {

    global $wpdb;



    $data = $request->get_params();
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

    // Loop through comments to collect user IDs filter out empty user IDs
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

    // return comments 
      // Map total comment counts to each comment
    $comments = array_map(function($comment)  {
        
        return comment_format($comment);
    }, $comments);

    return $comments;

}


function comment_format($comment, $depth = 0) {
    // Limit recursion depth to prevent potential issues with deeply nested comments
    if ($depth > 10) {
        return [];
    }
    
    // get the author ID
    $author_id = $comment->user_id;
  
    // this is if they were not logged in when they commented.  In the future they will be required to be logged in
    if ($author_id == 0) {
        // Attempt to find user by email
        $user = get_user_by('email', $comment->comment_author_email);
        if ($user) {
            $author_id = $user->ID;
        }
    }
  
    
    $author_avatar = get_avatar_url($author_id); 
    
  
    // Get first-level replies
    $replies = get_comments([
        'parent' => $comment->comment_ID,
        'status' => 'approve',
        'orderby' => 'comment_date_gmt',
        'order' => 'ASC'
    ]);
  
    // Recursively format replies
    $formatted_replies = array_map(function($reply) use ($depth) {
        return comment_format($reply, $depth + 1);
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
        'replies' => $formatted_replies,
        'total_comments' => get_user_meta($author_id, 'bbj_total_comments', true),
        'post_likes' => get_user_meta($author_id, 'bbj_total_likes', true),
        'post_dislikes' => get_user_meta($author_id, 'bbj_total_dislikes', true),
        'total_votes' => get_user_meta($author_id, 'bbj_total_votes', true),
        'user_rank' => get_user_meta($author_id, 'bbj_user_rank', true),
        'special_rank' => get_user_meta($author_id, 'bbj_special_rank', true)
    ];
  }


  function getVotes($request) {


    

    $data = $request->get_params();
    $comment_id = isset($data['comment_id']) ? $data['comment_id'] : 0;

    global $wpdb;
  
    $tableName = $wpdb->prefix . "wc_users_voted";
   
  
    $sql = $wpdb->prepare("SELECT user_id, vote_type, post_id, comment_id, date FROM $tableName WHERE `comment_id` = %d ORDER BY `id` DESC", $comment_id);
  
     $votes = $wpdb->get_results($sql, ARRAY_A);  
  
     return $votes;
  
  }


  