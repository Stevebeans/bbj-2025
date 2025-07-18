<?php 

add_action('init', 'process_feed_update_form');


function process_feed_update_form() {
  // Your existing code here.
  if (isset($_POST['feed_update_count'], $_POST['user_id'], $_POST['display_name'])) {

    $submitted_user_id = intval($_POST['user_id']);
    $current_user_id = get_current_user_id();

    if ($submitted_user_id !== $current_user_id) {
      // Log an error or handle the mismatch
      return;
    }

    // Continue processing the form as before

    $result = update_user_meta($submitted_user_id, 'feed_update_count', intval($_POST['feed_update_count']));

    if ($result === false) {
    } else {
    }

    // Save the display name
    $display_name = sanitize_text_field($_POST['display_name']);

    $userdata = array(
        'ID' => $current_user_id,
        'display_name' => $display_name
    );

    $user_id = wp_update_user($userdata);

    if (is_wp_error($user_id)) {
        bbj_log2(print_r('Failed to update display name', true));
    } else {
        bbj_log2(print_r('Display name updated', true));
    }

  } else {
  }

  }


  function custom_pre_get_avatar_data($args, $id_or_email) {
    $user = get_user_by('id', $id_or_email);
    if ($user) {
        $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            $custom_url = wp_get_attachment_url($custom_avatar);
            $args['url'] = $custom_url;
            $args['found_avatar'] = true;
        }
    }
    return $args;
}
add_filter('pre_get_avatar_data', 'custom_pre_get_avatar_data', PHP_INT_MAX, 2);


  function use_custom_avatar($avatar, $id_or_email, $size, $default, $alt) {
    $user = false;

    if (is_numeric($id_or_email)) {
        $id = (int) $id_or_email;
        $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $id = (int) $id_or_email->user_id;
            $user = get_user_by('id', $id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {
        $custom_avatar = get_user_meta($user->data->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            $avatar = wp_get_attachment_image($custom_avatar, [$size, $size], false, ['class' => 'avatar avatar-'.$size, 'alt' => $alt]);
        }
    }
    return $avatar;
}
add_filter('get_avatar', 'use_custom_avatar', PHP_INT_MAX, 6);



function custom_get_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
    $user = get_user_by('id', $id_or_email);
    if ($user) {
        $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            $custom_url = wp_get_attachment_url($custom_avatar);
            //bbj_log2('Custom Avatar URL: ' . $custom_url);
            $avatar = "<img src='{$custom_url}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' alt='{$alt}' />";
            return $avatar;
        }
    }
    //bbj_log2('Default Avatar: ' . $avatar);
    return $avatar;
}
add_filter('get_avatar', 'custom_get_avatar', PHP_INT_MAX - 1, 6);





function handle_avatar_upload() {
    //bbj_log2('Started avatar upload process');
    
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'avatar_upload_nonce')) {
        bbj_log2('Security check failed');
        wp_send_json_error(['message' => 'Security check failed.']);
        exit;
    }

    //bbj_log2('Security check passed, made it to the upload avatar');

    $current_user_id = get_current_user_id();
    
    if (!$current_user_id) {
        //bbj_log2('User not logged in');
        wp_send_json_error(['message' => 'You must be logged in to upload.']);
    }

    if (!isset($_FILES['file'])) {
        //bbj_log2('No file uploaded');
        wp_send_json_error(['message' => 'No file uploaded.']);
    }

    //bbj_log2('File found, proceeding to upload');

    // Upload the file into the WordPress Media Library
    $uploaded = media_handle_upload('file', 0);
    if (is_wp_error($uploaded)) {
        //bbj_log2('Upload error: ' . $uploaded->get_error_message());
        wp_send_json_error(['message' => 'Error uploading file: ' . $uploaded->get_error_message()]);
    } else {
        //bbj_log2('Upload success: ' . $uploaded);
        // Save the image ID as a user meta
        update_user_meta($current_user_id, 'custom_avatar', $uploaded);
        wp_send_json_success();
    }
}
add_action('wp_ajax_upload_avatar', 'handle_avatar_upload'); // If logged in
add_action('wp_ajax_nopriv_upload_avatar', 'handle_avatar_upload'); // If not logged in
