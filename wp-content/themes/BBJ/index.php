<?php

get_header(); ?>


<?php
$curSeason = currentSeason("name");
$curSeasonID = currentSeason("ID");
$current_season_id = get_option( 'bbj_v2_current_season', '' );

$user_id = get_current_user_id();
$posts_per_page_setting = get_user_meta($user_id, 'feed_update_count', true);


global $bbj_is_admin;


?>



<div class="bbj-container-inner">
<?php if (feedUpdater()): ?>
  
  <div id="index-feed-updater"></div>
<?php endif;  // End feed updater If?>



	<div class="mt-2 flex w-full flex-col bg-white lg:flex-row">
    <section id="main-left" class="flex-grow">
      <div class="flex justify-between">
        <h1 class="font-mainHead text-4xl text-primary500 p-2"><a href="/bigbrother-seasons/big-brother-27/">Big Brother 27 Spoilers</a></h1>
        <?php
        if ($bbj_is_admin) { ?>
          <div class="admin-notice"><a href="/wp-admin/admin.php?page=bbj-v2-edit-season&season_id=<?php echo $current_season_id; ?>">Edit Season</a></div>
        <?php
        }
        ?>

           <div class="page-last-updated pt-2 pr-2 text-xs text-gray-500">
            Last updated:
            <time class="updated"
                  datetime="<?php echo date('c', $latest_unix); ?>"
                  itemprop="dateModified">
              <?php echo human_time_diff( $latest_unix, current_time('timestamp') ) . ' ago'; ?>
            </time>
          </div>

            <?php
          
          ?>
      </div>


      <?php //if (!is_paged()):  // If Paged ?>
      <!-- Feed Updates and Featured Post block -->
      



      <div class="flex flex-grow p-2 flex-col " id="main-feeds">   
        <?php
        $args = [
          "post_type" => "post",
          "post_status" => "publish",
          "posts_per_page" => 5,
          "orderby" => "modified",
          "order" => "DESC",
        ];

        $recent_posts_query = new WP_Query($args);
        $recent_posts = $recent_posts_query->posts;

        // Featured Post
        $featured_post = array_shift($recent_posts);

        global $post;
        $post = $featured_post;
        setup_postdata($post);
        ?>

        <!-- <div class="border border-red-500 p-2 w-full my-4 bg-red-50 text-sm"><b>Important Notice:</b> Updates from Friday afternoon to Saturday evening may be less frequent. After a long season, we need a brief break to recharge for the final stretch. However, we will continue to provide updates on our Facebook page.
          <div class="font-semibold text-center text-lg"><a href="https://www.facebook.com/bigbrotherjunkies" target="_blank">Click here to visit the Facebook Page!</a></div>
        </div> -->
        <?php if (!is_paged()):  // If Paged ?>
        <div id="highlight-posts" class="w-full  flex-grow mt-4 md:mt-0">
          <h2 class="font-mainHead text-2xl text-primary500">Latest Post</h2>
          <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>

          <!-- Featured Post -->
          <div class="w-full bg-neutral-100 shadow-frontBox flex flex-col md:flex-row p-1 md:p-2">
            <div class="w-full md:w-[350px] flex-shrink-0"><a href="<?php the_permalink(); ?>"><img src="<?php echo the_post_thumbnail_url("featured-thumbnail"); ?>" class="w-full h-[225px] rounded-md" alt="<?= $curSeason ?> featured post"></a></div>
            <div class="flex flex-col min-h-[225px] h-full px-1 md:px-2">
              <h2 class="font-mainHead text-2xl font-bold mt-2 md:mt-0"><a href="<?php the_permalink(); ?>"><?= $featured_post->post_title ?></a></h2>
              <?php $post_time_data = my_post_time_ago_function(); ?>
              <time class="published flex font-ibm text-sm border-gray-200 mb-2 <?= $post_time_data["class"] ?>"
                  datetime="<?php echo get_the_modified_date('c'); ?>"
                  itemprop="datePublished">
                  <?= $post_time_data["time_diff"] ?>
            </time>
            
              <div class="text-sm flex-grow"><?php echo wp_trim_words(get_the_content(), 85, "..."); ?> <span class="read-more"><a href="<?php the_permalink(); ?>" >Read More...</a></span></div>
              <div class="flex justify-between font-ibm text-sm mt-auto">
                  <div>By: <?= get_the_author_meta("display_name") ?></div>
                  <div><?= $featured_post->comment_count ?> comments</div>
              </div>
            </div>
          </div>

          <?php wp_reset_postdata(); ?>
        </div>

        <?php endif; ?>

        <div class="my-6">
          <?php if (!newPremiumCheck()): ?>
            <!-- Tag ID: bigbrotherjunkies_incontent_reusable -->
            <div align="center" data-freestar-ad="__336x280 __336x280" id="bigbrotherjunkies_incontent_reusable">
              <script data-cfasync="false" type="text/javascript">
                freestar.config.enabled_slots.push({ placementName: "bigbrotherjunkies_incontent_reusable", slotId: "bigbrotherjunkies_incontent_reusable" });
              </script>
            </div>
          <?php endif; ?>
          <?php //spot_two(); ?>
        </div>

        

        <!-- Live Feed Update Block -->
        <div id="feed-updates" class="w-full">
          <?php if (!is_paged()):  // If Paged ?>
          <h3 class="font-mainHead text-2xl text-primary500"><a href="/feed-updates">Live Feed Updates</a></h3>
          <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>

          
          <div id="new-feed-updates"></div>
          <div class="text-xs">Showing the last <?= $posts_per_page_setting ?> Updates</div>

          <div id="loginModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div class="bg-white p-8 rounded relative">
              <button id="closeLoginModal" class="absolute top-2 right-2 text-gray-700">
                <i class="fa-solid fa-x"></i>
              </button>
              <p>You must be logged in to rate posts. Please <a href="/log-in">login</a> or <a href="/registration">register</a> here.</p>
            </div>
          </div>

         
          <?php
          $args = [
            "post_type" => "live-feed-updates",
            "posts_per_page" => $posts_per_page_setting,
            "orderby" => "modified",
            "order" => "DESC",
          ];
          $feed_updates = new WP_Query($args);
          ?>

          <?php if ($feed_updates->have_posts()): // If Feed Update Query?>
            <?php 
            $counter = 0;
            while ($feed_updates->have_posts()):
              $feed_updates->the_post(); 

              $post_id = get_the_ID();  
              ?>
            <?php $post_time_data = my_post_time_ago_function(); ?>


            <div class="p-1  border-sky-600 hover:bg-slate-200 border flex rounded-md  relative" data-reply-box="<?= get_the_ID() ?>">
            <?php 
            
              global $wpdb;

              $total_rating = 0;

              $table_name = $wpdb->prefix . 'bbj_feed_ratings';

              $query = "SELECT SUM(rating) AS total_rating FROM $table_name WHERE update_id = $post_id";

              $total_rating = $wpdb->get_var($query);

              if (!$total_rating) {
                $total_rating = 0;
              }

              $rating_color = "text-gray-500";

              if ($total_rating > 0) {
                $rating_color = "positive";
              } else if ($total_rating < 0) {
                $rating_color = "negative";
              }
              
            
            ?>
            <div id="feed-updates-left-<?= $post_id ?>" class="p-2 bg-gray-200 flex flex-col w-10 rounded-b-md rounded-tl-md feed-update-ratings" data-feed-rating="<?= $post_id ?>">
              
              <div class="feed-update-id-up hover:cursor-pointer text-center"><i class="fa-solid fa-chevron-up text-sky-600"></i></div>
              <div class="feed-update-count text-center font-ibm text-lg <?= $rating_color ?>" data-count-for="<?= $post_id ?>"><?= $total_rating ?></div>

              <div class="feed-update-id-down hover:cursor-pointer  text-center"><i class="fa-solid fa-chevron-down text-sky-600"></i></div> 
                         
            </div>  
            
            
            <div class="w-full flex flex-col">


              <div class="bg-gray-200 p-1 flex justify-between ">
                <div class="text-xs">
                <?php 
                    // get author avatar 
                    $author_id = get_the_author_meta("ID");
                    $author_avatar = get_avatar_url($author_id, 32);              
                    ?>
                    <!-- Prevent this div from shrinking and give it a minimum width (You can adjust the min-width as needed) -->
                    <div class="font-ibm text-sm flex-shrink-0 flex min-w-fit items-center">
                        <img src="<?= $author_avatar ?>" class="rounded-full w-4 h-4 mr-2" alt=""> <?php the_author(); ?> <span class="<?php echo $post_time_data["class"] ?> ml-2 text-xs"> <?php echo $post_time_data["time_diff"] ?></span>
                    </div>
                
                </div>
                <div class="text-xs">
                  <?php if (feedUpdater()): ?>
                    <a href="<?php echo get_edit_post_link(); ?>">Edit</a>
                  <?php endif; // Closed?>
                </div>
              </div>
              <div class="flex-col p-2 <?php echo has_post_thumbnail() ? 'min-h-[80px]' : ''; ?>">
                <?php if ( has_post_thumbnail() ): ?>
                  <div class="row-span-2 float-left  mr-2">
                  <a href="<?= the_permalink() ?>"><img src="<?php the_post_thumbnail_url('thumbnail'); ?>" class=" w-[85px] h-20 rounded" alt=""></a>                    
                  </div>
                <?php endif; // Closed ?>

                <div class="text-lg font-semibold"><span id="title-<?= get_the_ID(); ?>"><a href="<?= the_permalink() ?>"><?php the_title(); ?></a></span></div>
            
                <div class="text-sm mb-2 " id="content-wrapper-<?= get_the_ID(); ?>">
                  <div id="content-<?= get_the_ID(); ?>"><?= get_the_content(); ?></div>
                  <!-- Hidden Textarea for Content Edit -->
                  <textarea id="content-input-<?= get_the_ID(); ?>" style="display: none;" class="border border-purple-400 w-full"><?php echo esc_textarea(get_the_content()); ?></textarea>
                </div>
              </div>


              <div class="text-sm border-t border-gray-200 pt-1 w-full flex justify-between">
                <div class="pl-2">
                  <a href="<?= rtrim(get_permalink(), '/') ?>#wpd-main-form-wrapper-0_0">
                  <?php
                    $comment_count = get_comments_number();
                    echo $comment_count . ' ' . ($comment_count == 1 ? 'Comment' : 'Comments');
                  ?>
                 </a>
                </div>
                <a href="<?= rtrim(get_permalink(), '/') ?>#wpd-main-form-wrapper-0_0">
                </a>
              </div>
              <div class="reply-box" id="reply-box-inner-<?= get_the_ID(); ?>" class="" style="display:none">
            
                <?php 
                // if logged in show an input box or show a message 

                if (is_user_logged_in()):?>

                <textarea name="" id="comment-text-<?= get_the_ID(); ?>" class="w-full border rounded-md mb-1 p-1 text-sm"></textarea>
                <button class="submit-comment border py-0.5 px-2 bg-gray-200 border-gray-600 rounded-md" data-nonce="<?php echo wp_create_nonce( 'wp_rest' )?>" data-post-id="<?= get_the_ID()?>">Submit</button>
                  <div class="response-text"></div>

                <?php else: ?>

                  <div class="text-center text-sm">You must be logged in to reply to this post. <a href="/log-in">Login here</a> or <a href="/registration">Register here</a></div>

                  

                <?php endif; // End user logged in IF ?>
                    
              </div>

              <!-- <div class="text-xs text-primary500 hover:cursor-pointer absolute bottom-[-10px] right-2 reply-button">
                <div class="bg-sky-200 border border-sky-600 rounded-xl px-2 py-0.5  w-20 flex justify-center items-center reply-text">
                <i class="fa-solid fa-reply mr-2 reply-icon"></i> <span>Reply</span>
                </div>
              </div> -->
            </div>
          </div>

            <?php 
            
            $post_id = get_the_ID();  // Get the current Post ID
            set_query_var('post_id', $post_id);  // Pass it to the template part
            //get_template_part('template-parts/update-comments');            
            ?>

            
          <div class="mb-6"></div>
            <?php 

            if ($counter == 5) {
              //front_between_feed_updates(); // Your function call goes here
            } // Closed IF
            $counter++;

            endwhile;
            wp_reset_postdata(); 
          endif;  // End feed Update IF I believe?>


        

        <div class="text-center text-reg border border-gray-200 rounded py-2 bg-gray-100"><a href="/feed-updates" class="text-primary500 font-bold underline hover:underline visited:text-primary500 visited:underline">View All Feed Updates Here</a></div>

        
        <div class="my-6">
          <?php if (!newPremiumCheck()): ?>
            <!-- Tag ID: bigbrotherjunkies_incontent_reusable_Homepage2 -->
            <div align="center" data-freestar-ad="__336x280 __336x280" id="bigbrotherjunkies_incontent_reusable_Homepage2">
              <script data-cfasync="false" type="text/javascript">
                freestar.config.enabled_slots.push({ placementName: "bigbrotherjunkies_incontent_reusable_Homepage2", slotId: "bigbrotherjunkies_incontent_reusable_Homepage2" });
              </script>
            </div>
          <?php endif; ?>
        </div>

<?php endif;  // End first page stuff?>
        <div id="more-posts" class="w-full mt-6 border-t border-gray-200 pt-4">
        
          <h3 class="font-mainHead text-2xl text-primary500">More Stories & News</h3>
          <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>

          <?php
          $paged = get_query_var("paged") ? get_query_var("paged") : 1;

          $counter = 0;

          // Set the number of posts per page
          $posts_per_page = 10;

          // Calculate the offset

          if ($paged == 1) {
            $offset = 1; // For front page, offset 5 featured posts
          } else {
            $offset = ($paged - 1) * $posts_per_page + 2; // For pages 2 and beyond, start from where the front page left off
          }

          // Set the query arguments
          $args = [
            "posts_per_page" => $posts_per_page,
            "offset" => $offset,
            "orderby" => "modified",
            "order" => "DESC",
            "paged" => $paged,
          ];

          $second_latest_post = new WP_Query($args);
          $first_page_link = get_pagenum_link(1);
          $max_num_pages = $second_latest_post->max_num_pages;

          //bbj_log2(print_r($second_latest_post, true));
          echo '<div class="w-full flex gap-3 mb-4 justify-between">';
          if ($paged > 1) {
            $previous_page = $paged - 1;
            $previous_page_link = get_pagenum_link($previous_page);
            echo '<a href="' . $previous_page_link . '" class="back-button"><i class="fa-solid fa-angles-left"></i> Back</a>';
            echo '<a href="' . $first_page_link . '" class="first-page-button"><i class="fa-solid fa-house"></i> Home</a>';
          }
          if ($paged < $max_num_pages) {
            $next_page = $paged + 1;
            $next_page_link = get_pagenum_link($next_page);
            echo '<a href="' . $next_page_link . '" class="next-page-button">Next Page <i class="fa-solid fa-angles-right"></i></a>';
          }
          echo "</div>";

          if ($second_latest_post->have_posts()):
            while ($second_latest_post->have_posts()):

              $second_latest_post->the_post();
              $counter++;
              $post_time_data = my_post_time_ago_function();
          ?>
          <div class="border-b border-gray-300 flex flex-col md:flex-row py-4">
            <div class="flex-shrink-0 w-full md:w-[250px] "><a href="<?php the_permalink(); ?>"><img src="<?php echo the_post_thumbnail_url("featured-thumbnail"); ?>" class="w-full h-[150px]" alt="<?php esc_attr(the_title()); ?>"></a></div>
            <div class="grid grid-cols-2 w-full pl-2">
              <!-- First row -->

              <?php $categories = get_the_category(); ?>
              <div class="font-ibm text-sm text-left text-gray-500"><?php echo !empty($categories) ? esc_html($categories[0]->name) : "Uncategorized"; ?></div>
              <div class="font-ibm text-sm text-right text-gray-500"><?= $post_time_data["time_diff"] ?></div>
              
              <!-- Second row -->
              <div class="col-span-2">
                <div class="font-mainHead text-2xl"><a href="<?php the_permalink(); ?>"><h2><?php esc_attr(the_title()); ?></a></div>
                <div class="text-sm"><?= wp_trim_words(get_the_content(), 55, "...") ?></div>
              </div>
              
              <!-- Third row -->
              <div class="font-ibm text-sm text-gray-500  text-left"><?= get_the_author_meta("display_name") ?></div>
              <div class="font-ibm text-sm text-gray-500  text-right"><?= comments_number("No comments", "1 comment", "% comments") ?></div>
            </div>
          </div>

          <?php
              if ($counter == 4 || $counter == 9) {
                //between_posts();
              }
            endwhile;
          endif; // End second_latest_post
          ?>
            <nav class="w-full flex items-center justify-between p-2">
            <div class="hidden md:block grow text-sm text-gray-700 dark:text-gray-200">
              <?php
              global $wp_query;
              $page = get_query_var("paged") ? get_query_var("paged") : 1;
              $page_count = $wp_query->max_num_pages;
              echo "Showing: ";
              echo sprintf("%d–%d of %d", ($page - 1) * get_option("posts_per_page") + 1, min($wp_query->found_posts, $page * get_option("posts_per_page")), $wp_query->found_posts);
              ?>
            </div>

            <div class="flex rounded-md shadow border border-gray-200 w-full max-w-[400px] mx-auto dark:border-gray-400">
            <?php
            $pagination_links = paginate_links([
              "base" => get_pagenum_link(1) . "%_%",
              "format" => "page/%#%/",
              "current" => $paged,
              "total" => $second_latest_post->max_num_pages,
              "prev_text" => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>',
              "next_text" => '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>',
              "type" => "array",
            ]);

            if (!empty($pagination_links)) {
              foreach ($pagination_links as $link) {
                echo '<div class="page-num">' . $link . "</div>";
              }
            }
            ?>
            </div>
          </nav>

        </div>
          

          
      </div>
        

        
    </div>
    <!-- Feed Updates and Featured Post block end -->
      
    
    

    </section>
    
    <?php get_template_part("template-parts/sidebar-default"); ?>
      
  </div>
  

  
  
</div>

<script>
  window.userLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
</script>



<?php get_footer();
