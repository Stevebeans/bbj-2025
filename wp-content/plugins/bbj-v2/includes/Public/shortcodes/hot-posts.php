<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_shortcode ( 'bbj_hot_posts', 'bbj_hot_posts_function' );

function bbj_hot_posts_function( $atts ) {


  global $bbj_is_admin;

$atts = shortcode_atts([
  'count' => 10,
  'days'  => 14,
], $atts, 'bbj_hot_posts');

  $limit       = max(1, (int) $atts['count']);
  $window_days = max(1, (int) $atts['days']);

  $is_admin_view = isset($bbj_is_admin)
    ? (bool) $bbj_is_admin
    : ( is_user_logged_in() && current_user_can('manage_options') );

  // versioned + parameter-aware key
  $cache_key = bbj_hot_posts_cache($limit, $is_admin_view, $window_days);
  if ( false !== ($html = wp_cache_get($cache_key, BBJ_CACHE_GROUP)) ) {
    return $html;
  }

  // grab a list of posts with the most comments in the last 14 days
  $args = [
    'posts_per_page'              => $limit,
    'orderby'                     => 'comment_count',
    'order'                       => 'DESC',
    'date_query'                  => [[
      'after'                     => "$window_days days ago",
      'inclusive'                 => true,
    ]],
    'post_status'                 => 'publish',
    'ignore_sticky_posts'         => true,
    'no_found_rows'               => true,
    'update_post_meta_cache'      => false,
  ];

  $q = new WP_Query($args);  

  if ( function_exists('update_post_thumbnail_cache') ) {
    update_post_thumbnail_cache($q);
  }
  
  ob_start();
  ?>
  <?php if ( $q->have_posts() ) : ?>
      <div class="lg:h-[600px] overflow-y-auto">
    <?php while ( $q->have_posts() ) : $q->the_post();
      $post_thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'bbj_hot_thumbnail');
    ?>      
      <div class="flex mb-2 border-b last:border-0 border-gray-300 pb-2 space-x-2">
        <div class="w-[128px] h-[80px] shrink-0"><a href="<?php the_permalink(); ?>">
          <img class="w-full h-full object-cover rounded-lg"
             src="<?php echo esc_url($post_thumbnail); ?>" 
             alt="<?php the_title_attribute(); ?>"
             loading="lazy"
             fetchpriority="low"
             decoding="async"
          />
        </a></div>
        <div class="text-sm font-sans font-semibold text-primary500"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></div>
      </div>            
    <?php endwhile; ?>
    </div>
  <?php else : ?>
    <p>No posts in the last 14 days.</p>
  <?php endif; ?>

<?php

  wp_reset_postdata();

  $html = ob_get_clean();
  $ttl = (int) BBJ_CACHE_TTL + wp_rand(0, 60); // small jitter prevents stampedes
  wp_cache_set($cache_key, $html, BBJ_CACHE_GROUP, $ttl);
  return $html;
}