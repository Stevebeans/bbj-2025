<?php 

function render_new_feed_updates_block() {
  // Query for the latest 10 posts of the 'live-feed-updates' custom post type

  global $post;
  $original_post = $post;

  $current_post_date = get_the_date('m/d/Y', $post->ID);




  $user_id = get_current_user_id();
  $posts_per_page = get_user_meta($user_id, 'feed_update_count', true);

  if (empty($posts_per_page)) {
      $posts_per_page = 10; // default value
  }

  $query = new WP_Query(array(
    'post_type' => 'live-feed-updates',
    'posts_per_page' => -1,
    'order' => 'ASC',
    'date_query' => array(
        array(
            'year' => get_the_date('Y', $post->ID),
            'month' => get_the_date('n', $post->ID),
            'day' => get_the_date('j', $post->ID),
        ),
    ),
));



  // Reset to the original post
  // Start buffering output
  ob_start();

  // Get today's date format in mm/dd/yyyy
  
  $today = date('m/d/Y');
?>

<div class="feed-updates border-t-2 border-b-2 border-gray-200 pt-4 pb-4">

  <div class="text-lg bold">Live Feed Updates For <?= $current_post_date ?></div>

    <?php if ( $query->have_posts() ) :
    $count = 0; 
      while ( $query->have_posts() ) : 
        $query->the_post();
        $count++; // Increment counter
        $post_time_data = my_post_time_ago_function();
    ?>

    <div class="my-4 p-1 border-l-8 border-gray-200 hover:bg-slate-200 border-t border-b rounded-md">
      <div class="bg-gray-100 p-1">
        <div class="text-xs ">
          <?php 
              echo get_the_date('m/d/y h:ia');
          ?>
          (<span class="<?php echo $post_time_data["class"] ?>"><?php echo $post_time_data["time_diff"] ?></span>)
        </div>
        <div class="text-xs">
          By: <?php the_author(); ?>
        </div>
      </div>
  
      <div><?php the_title(); ?></div>
      <div class="text-center">
        <?php
          if (has_post_thumbnail()) {
            echo get_the_post_thumbnail(null, 'large', array( 'class' => 'text-center mx-auto rounded-lg my-1' ));
          }
        ?>
      </div>
      <div><?php the_content(); ?></div>
      
    </div>



        <?php
        if ($count % 5 == 0) {
          show_impact_radius();
        }
      endwhile;
      // ends loop

      wp_reset_postdata();

          $post = $original_post;

        else:
          echo '<div class="text-center">No updates found for this date.</div>';
        endif;
        ?>
       

  <div class="end-feed-updates">If you would like to see more feed updates, <a href="/feed-updates" class="text-blue-600 font-bold underline hover:underline visited:text-blue-700">Visit our feed update page here</a></div>

  
</div>

<?php
  // Reset Post Data

  // Get output from the buffer and end buffering
  $output = ob_get_clean();

  return $output;
}

// Register the server-side rendering callback
function register_new_feed_updates_block() {
  if ( function_exists( 'register_block_type' ) ) {
      register_block_type( 'my-plugin/new-feed-updates', array(
          'render_callback' => 'render_new_feed_updates_block',
      ) );
  }
}
add_action( 'init', 'register_new_feed_updates_block' );
