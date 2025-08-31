<?php 

// add rest api 

add_action( 'rest_api_init', function () {
   register_rest_route('bbj/v1', '/feed-update', array(
    'methods' => 'POST',
    'callback' => 'update_feed',
    'permission_callback' => function () {
      return current_user_can('edit_others_posts') || current_user_can('updater');
    }
  ));
});



function update_feed(WP_REST_Request $request) {

  
  $title = $request->get_param('title');
  $content = $request->get_param('content');
  $image = $request->get_file_params()['image'];

  $post_id = wp_insert_post([
    'post_title'    => $title,
    'post_content'  => $content,
    'post_status'   => 'publish',
    'post_type'     => 'live-feed-updates',
  ]);

  
  

  if ($post_id) {
    // Handle the image upload
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

   

    $upload_overrides = ['test_form' => false];
        // see exactly what file-array you got
    $upload = wp_handle_upload( $image, $upload_overrides );
       // see success or WP_Error/‘error’

    if ($upload && !isset($upload['error'])) {
      $filetype = wp_check_filetype(basename($upload['file']), null);
      $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
        'post_content'   => '',
        'post_status'    => 'inherit'
      ];

      $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

      $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);

      wp_update_attachment_metadata($attach_id, $attach_data);

      set_post_thumbnail($post_id, $attach_id);


       
    }
          /**
           * This action clears Breeze's internal cache and also
           * purges Varnish, exactly like clicking "Purge All Cache".
           */
    do_action( 'breeze_clear_all_cache' );
     

    return new WP_REST_Response("Post created with ID: $post_id", 200);
  } else {
    return new WP_Error('create_error', 'Could not create post.', ['status' => 500]);
  }
}
