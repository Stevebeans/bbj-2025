<?php 

// Add a link to the admin bar to clear all caches
function add_clear_cache_link($wp_admin_bar) {
  if (current_user_can('manage_options')) {
      $wp_admin_bar->add_node(array(
          'id' => 'clear-cache',
          'title' => 'Clear BBJ Cache',
          'href' => wp_nonce_url(admin_url('?clear_all_cache=true'), 'clear_all_cache'),
      ));
  }
}
add_action('admin_bar_menu', 'add_clear_cache_link', 100);

// Clear W3 Total Cache, Varnish, and Cloudflare cache
function clear_all_cache() {
  if (current_user_can('manage_options') && isset($_GET['clear_all_cache']) && wp_verify_nonce($_GET['_wpnonce'], 'clear_all_cache')) {
      // Clear W3 Total Cache
      if (function_exists('w3tc_flush_all')) {
          w3tc_flush_all();
      }

      // Clear Cloudflare Cache
      $api_key = getenv('CLOUDFLARE_API_KEY');
      $email = getenv('CLOUDFLARE_EMAIL');
      $zone_id = getenv('CLOUDFLARE_ZONE_ID');

      $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/purge_cache';
      $args = array(
          'headers' => array(
              'X-Auth-Email' => $email,
              'X-Auth-Key'   => $api_key,
              'Content-Type' => 'application/json',
          ),
          'body'    => json_encode(array('purge_everything' => true)),
          'method'  => 'POST',
          'timeout' => 10,
      );

      $response = wp_remote_post($url, $args);
      if (is_wp_error($response)) {
          error_log('Error clearing Cloudflare cache: ' . $response->get_error_message());
      }

      // Clear Cloudways Varnish Cache
      $api_key = getenv('CLOUDWAYS_API_KEY');
      $email = getenv('CLOUDWAYS_EMAIL');
      $server_id = getenv('CLOUDWAYS_SERVER_ID');
      $app_id = getenv('CLOUDWAYS_APP_ID');

      $api_endpoint = "https://api.cloudways.com/api/v1/app/manage/purge_varnish";

      $args = array(
          'headers' => array(
              'Content-Type' => 'application/x-www-form-urlencoded',
              'Accept' => 'application/json',
              'Authorization' => 'Basic ' . base64_encode($email . ':' . $api_key),
          ),
          'body' => array(
              'server_id' => $server_id,
              'app_id' => $app_id,
              'url' => home_url(),
          ),
          'method' => 'POST',
          'timeout' => 10,
      );

      $response = wp_remote_post($api_endpoint, $args);
      if (is_wp_error($response)) {
          error_log('Error clearing Cloudways Varnish cache: ' . $response->get_error_message());
      }

      // Redirect back to the referring page
      $redirect_url = remove_query_arg(array('clear_all_cache', '_wpnonce'), wp_get_referer());
      wp_safe_redirect($redirect_url);
      exit;
  }
}
add_action('admin_init', 'clear_all_cache');

