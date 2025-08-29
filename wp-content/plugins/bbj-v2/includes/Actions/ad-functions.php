<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Register the action 

add_action ('admin_init', function() {
   
  // register settings for ads
  register_setting( 
    'bbj_ads_group',
    'bbj_ads',
    [
      'type' => 'array',
      'sanitize_callback' => null,
      'default' => []
    ]
  );

    add_settings_section(
      'bbj_ads_main',
      __('Ad Slots', 'bbj'),
      function () {
          echo '<p>' . esc_html__('Paste your ad snippets into the slots below. HTML/JS allowed for admins.', 'bbj') . '</p>';
      },
      'bbj-ads'
  );


  // define slots 
   $slots = [
        'above_header'        => 'Above Header',
        'in_header'           => 'Inside Header Ads (hidden)',
        'in_header_misc'      => 'Inside Header Misc (visible)',
        'below_header'        => 'Below Header',
        'below_header_mobile' => 'Below Header Mobile',
        'after_post'          => 'After Post',
        'index_top'           => 'Index Top',
        'index_top_mobile'    => 'Index Top Mobile',
        'index_mid'           => 'Index Mid',
        'index_bottom'        => 'Index Bottom',
        'in_content_feeds'    => 'In Content Feeds',
        'single_top'          => 'Single Post Top',
        'single_mid'          => 'Single Post Mid',
        'single_bottom'       => 'Single Post Bottom',
        'sidebar_bottom'      => 'Sidebar (Bottom)',
        'sidebar_top'         => 'Sidebar (Top)',
        'footer'              => 'Footer',
    ];

  // register each slot
  foreach ( $slots as $key => $label ) {
      add_settings_field(
          "bbj_ads_{$key}",
          esc_html($label),
          function () use ( $key ) {
             if ( ! current_user_can( 'manage_options' ) ) {
                 return;
             }
             $opts = get_option( 'bbj_ads', [] );
             $val = isset( $opts[ $key ] ) ? $opts[ $key ] : '';
             // do not escape here, as we want to allow HTML/JS
             echo '<textarea name="bbj_ads[' . esc_attr( $key ) . ']" rows="5" cols="50" class="large-text">' . esc_textarea( $val ) . '</textarea>';
             echo '<p class="description">' . esc_html__('Paste your ad code here.', 'bbj') . '</p>';
          },
          'bbj-ads',
          'bbj_ads_main'
      );
  } 
});
