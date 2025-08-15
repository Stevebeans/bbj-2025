<?php
function bbj_add_edit_player() {
    global $wpdb;

// 1) Security check
    if (
        ! isset( $_POST['bbj_add_edit_player_nonce'] ) ||
        ! wp_verify_nonce( $_POST['bbj_add_edit_player_nonce'], 'bbj_add_edit_player_action' )
    ) {
        wp_die( 'Security check failed' );
    }

    

// 2) Collect & sanitize
    $player_id         = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
    $first_name        = sanitize_text_field($_POST['first_name']);
    $last_name         = sanitize_text_field($_POST['last_name']);
    $official_nickname = sanitize_text_field($_POST['official_nickname']);
    $date_of_birth     = sanitize_text_field($_POST['date_of_birth']);
    $gender            = sanitize_text_field($_POST['player_gender']);
    $occupation        = sanitize_text_field($_POST['occupation']);
    $facebook          = sanitize_text_field($_POST['facebook'] ?? '');
    $twitter           = sanitize_text_field($_POST['twitter'] ?? '');
    $tiktok            = sanitize_text_field($_POST['tiktok'] ?? '');
    $instagram         = sanitize_text_field($_POST['instagram'] ?? '');

    // Geo Fields 
    $address_street    = sanitize_text_field($_POST['address_street'] ?? '');
    $lat               = sanitize_text_field($_POST['lat'] ?? '');
    $lng               = sanitize_text_field($_POST['lng'] ?? '');
    $player_city       = sanitize_text_field($_POST['player_city'] ?? '');
    $player_state      = sanitize_text_field($_POST['player_state'] ?? '');
    $address_map       = $lat . ', ' . $lng . ', 14';

    bbj_log3(print_r($address_map, true));

// 3) Insert or Update post
    $post_args = [
        'ID'          => $player_id, // 0 = insert new
        'post_title'  => $first_name . ' ' . $last_name,
        'post_type'   => 'bigbrother-players',
        'post_status' => 'publish',
    ];

    $player_id = wp_insert_post($post_args);
    if (is_wp_error($player_id)) {
        wp_die('Could not save player.');
    }

    // Geo Table Update
    $geo_table = BBJ_V2_TABLE_GEO;

    $geo_data = [
        'ID'                            => $player_id,
        'address_street'                => $address_street,
        'lat'                           => $lat,
        'lng'                           => $lng,
        'locality'                      => $player_city,
        'administrative_area_level_1'   => $player_state,
        'address_map'                   => $lat . ',' . $lng . ',14',
    ];

    $geo_format = [
        '%d',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
        '%s',
    ];

    // use replace as ID is primary key
    $replaced = $wpdb->replace($geo_table, $geo_data, $geo_format);
    // if ID is not in the table, it will insert, otherwise it updates
    if ($replaced === false) {
         $updated = $wpdb->update(
            $geo_table,
            $geo_data,
            ['ID' => (int) $player_id],
            $geo_format,
            ['%d']
        );
        if ($updated === false) {
            $wpdb->insert($geo_table, $geo_data, $geo_format);
        }
    }

// 4) Handle profile picture upload (only if new file provided)
    $profile_picture = null;

    if (!empty($_FILES['profile_picture_file']['tmp_name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload_overrides = ['test_form' => false];
        $moved = wp_handle_upload($_FILES['profile_picture_file'], $upload_overrides);

        if (isset($moved['error'])) {
            wp_die('Upload error: ' . esc_html($moved['error']));
        }

        $file_path  = $moved['file'];
        $filetype   = wp_check_filetype(basename($file_path), null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(basename($file_path)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $player_id,
        ];

        $attach_id   = wp_insert_attachment($attachment, $file_path, $player_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        $profile_picture = $attach_id;
    }    

// 5) Save meta fields (don't overwrite profile picture unless we have a value)
    $meta_fields = [
        'first_name'        => $first_name,
        'last_name'         => $last_name,
        'official_nickname' => $official_nickname,
        'date_of_birth'     => $date_of_birth,
        'player_gender'     => $gender,
        'occupation'        => $occupation,
        'facebook'          => $facebook,
        'twitter'           => $twitter,
        'tiktok'            => $tiktok,
        'instagram'         => $instagram,
    ];

    if ($profile_picture) {
        $meta_fields['profile_picture'] = $profile_picture;
    } elseif (!empty($_POST['current_profile_picture'])) {
        $meta_fields['profile_picture'] = intval($_POST['current_profile_picture']);
    }

    foreach ($meta_fields as $key => $value) {
        rwmb_set_meta($player_id, $key, $value);
    }


    // 6) Bust spoiler bar cache for any affected seasons
    $link_table = $wpdb->prefix . 'bbj_v2_player_season';
    $season_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT bbj_season FROM {$link_table} WHERE bbj_player = %d", $player_id
    ));
    if ($season_ids) {
        foreach ($season_ids as $sid) {
            bbj_spoiler_bar_bust_cache((int)$sid);
        }
    }

    // 7) Redirect back to admin page
    $redirect = add_query_arg(
        [
            'page'      => 'bbj-v2-add-edit-player',
            'method'    => 'edit',
            'player_id' => $player_id,
            'updated'   => 'true',
        ],
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect);
    exit;
}
