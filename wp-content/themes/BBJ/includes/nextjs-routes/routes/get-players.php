<?php 

function get_players() {
    global $wpdb;
    // pull the players from the database
    $players_table = $wpdb->prefix . "bbj_players";
    $query         = $wpdb->prepare("SELECT * FROM $players_table");
    
    try {
        $results = $wpdb->get_results($query, ARRAY_A);

        if ($results === null) {
            // Something went wrong with the query
            throw new Exception($wpdb->last_error);
        }

        if (empty($results)) {
            // No players found
            return new WP_REST_Response(['message' => 'No players found'], 404);
        }

        // Loop through each player and generate a thumbnail URL if profile_picture is valid
        foreach ($results as &$player) {
            if (!empty($player['profile_picture'])) {
                // The profile_picture field should be an attachment ID
                $attachment_id = $player['profile_picture'];
                
                // Retrieve the thumbnail URL
                $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
                
                // You could store the URL under the same key,
                // but it might be cleaner to add a separate key like 'profile_picture_url'
                $player['profile_picture_url'] = $thumbnail_url ? $thumbnail_url : '';
            } else {
                // If there's no attachment, set it to an empty string or handle however you'd like
                $player['profile_picture_url'] = '';
            }
        }

        return new WP_REST_Response($results, 200);
        
    } catch (Exception $e) {
        return new WP_Error(
            'database_error',
            'Database query failed: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}
