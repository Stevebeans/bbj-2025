<?php

get_header(); ?>

<?php
$curSeason = currentSeason("name");
$curSeasonID = currentSeason("ID");
$current_season_id = get_option( 'bbj_v2_current_season', '' );
$bbj_season = bbj_v2_get_season_by_id($current_season_id);
$season_name = $bbj_season['full_name'] ?? '';
$season_permalink = get_permalink($current_season_id);



$user_id = get_current_user_id();
$posts_per_page_setting = get_user_meta($user_id, 'feed_update_count', true);


global $bbj_is_admin;


$hero_q = new WP_Query([
  'post_type'              => 'post',
  'post_status'            => 'publish',
  'posts_per_page'         => 1,
  'orderby'                => 'modified',
  'order'                  => 'DESC',
  'ignore_sticky_posts'    => true,
  'no_found_rows'          => true,
  'update_post_term_cache' => false,
  'update_post_meta_cache' => false,
]);

$featured_post = null;
$hero_id = 0;

if ( $hero_q->have_posts() ) {
  $hero_q->the_post();
  $featured_post = get_post();
  $hero_id = $featured_post->ID;
}
$post_time_data = my_post_time_ago_function(); 

$mobile_thumbnail = get_the_post_thumbnail_url($hero_id, 'bbj_v2_index_mobile');
$desktop_thumbnail = get_the_post_thumbnail_url($hero_id, 'bbj_v2_index_hero');
wp_reset_postdata();
?>

<main class="v2-primary-container">
<?php if (feedUpdater()): ?>  
  <div id="index-feed-updater"></div>
<?php endif ?>



	<div class="flex w-full flex-col  lg:flex-row dark:text-gray-200">
    <section id="main-left" class="flex-grow space-y-4">


      <?php if ( $featured_post ): ?>
      <article <?php echo is_singular() ? 'itemscope itemtype="https://schema.org/BlogPosting"' : ''; ?> class="v2-primary-container-inner" id="featured-post-<?= $hero_id ?>" aria-labelledby="featured-post-<?= $hero_id ?>-title">
        <h1 class="font-mainHead text-2xl text-primary500 p-2">Latest <a href="<?php echo esc_url( $season_permalink ); ?> "><?php echo $season_name ?> Spoilers</a></h1>
        <!-- Latest Article Hero Section -->
        <div class="relative h-[333px] bg-gray-100 overflow-hidden">
          <div class="absolute inset-0">
            <a href="<?= esc_url( get_permalink($hero_id) ) ?>">
            <img
              src="<?= esc_url( $desktop_thumbnail ) ?>"
              class="w-full h-full hidden md:block object-cover bbj-hero-img"
              alt="<?= esc_attr( get_the_title($hero_id) ) ?>"
              loading="eager"
              fetchpriority="high"
              decoding="async"
              sizes="(min-width:768px) 100vw, 0px"
              width="1920" height="333"
            />
            <img
              src="<?= esc_url( $mobile_thumbnail ) ?>"
              class="w-full h-full md:hidden object-cover bbj-hero-img"
              alt="<?= esc_attr( get_the_title($hero_id) ) ?>"
              loading="eager"
              fetchpriority="high"
              decoding="async"
              sizes="100vw"
              width="750" height="333"
            />

            </a>
          </div>
           
          <div class="absolute w-full z-10 bottom-0 left-0 ">
            <!-- Meta Data -->
            <div class="bg-white  px-4 py-1 w-fit flex items-center rounded-tr-md font-ibm text-xs text-slate-700 v2-dark-reg">             
              
              <i class="fa-regular fa-message mr-1"></i> <?= comments_number("No comments", "1 comment", "% comments") ?>
            </div>          
          </div>

          <!-- Read More Button -->
          <div class="absolute bottom-4 right-4 z-30">
            <a href="<?= esc_url( get_permalink($hero_id) ) ?>" class="inline-flex text-sm md:text-base items-center rounded  px-2 md:px-4 py-1 font-bold text-white
          bg-gradient-to-r from-red-400 to-red-700
          hover:from-red-500 hover:to-red-800
          focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:ring-offset-2
          transition-colors visited:text-white ">
              Read More
            </a>
          </div>
        </div>
        <div>
          <div class="p-2">
            <h2 class="font-mainHead text-3xl md:text-4xl pt-0 pb-0 mt-0 mb-0 text-primary500 visited:text-primary500 hover:text-primary500 ">
              <a href="<?= esc_url( get_permalink($hero_id) ) ?>" class="font-normal v2-dark-link my-0 py-0">
                <?= esc_html( get_the_title($hero_id) ) ?>
              </a>
            </h2> 
            
            <div class="-mt-2 bg-white pb-1 w-fit flex items-center rounded-tr-md font-ibm text-xs text-slate-700 v2-dark-reg">              
              <?php $post_time_data = my_post_time_ago_function(); ?>
              <?= bbj_time_tags( $hero_id, true ); ?>
              <span class="ml-2 text-xs hidden lg:block <?php echo $post_time_data["class"] ?>"  data-nosnippet>
                <?= esc_html( $post_time_data['time_diff'] ) ?>
              </span>
            </div>   

            <?php
              $raw = get_post_field('post_excerpt', $hero_id);
              if ($raw === '') {
                $raw = wp_strip_all_tags( get_post_field('post_content', $hero_id) );
              }
              $mobile_text  = wp_html_excerpt($raw, 85, '…');  // 25 chars
              $desktop_text = wp_html_excerpt($raw, 250, '…');  // 85 chars
              ?>
                            
              <div class="text-sm v2-dark-reg">
                <span class=""><?= esc_html($desktop_text) ?></span>
            </div>
          </div>
        </div>
      </article>
      <?php bbj_print_article_jsonld( $hero_id ); ?>

      <?php endif; ?> 

      <?php bbj_echo_ad( 'index_top' ); ?>

        <?php if (!newPremiumCheck()):
        // this is literally just so tailwind can recognize it and create the css for it
        ?>
        <div class="v2-ad-container"></div>
        <?php endif; ?>

      
      <div class="flex flex-col lg:flex-row lg:gap-4">

        <!-- Feed Updates Section -->
        <section class="v2-primary-container-inner grow p-2mb-4 lg:mb-0" aria-labelledby="main-feeds" itemscope itemtype="https://schema.org/LiveBlogPosting">
         

          <h2 id="main-feeds" class="font-mainHead text-2xl text-primary500 uppercase p-2" itemprop="headline">Latest Feed Updates</h2>
          <div class=" lg:h-[2000px] lg:min-h-0 lg:overflow-y-auto ">

          <?php
          $args = [
            "post_type"             => "live-feed-updates",
            "posts_per_page"        => 15,
            "orderby"               => "modified",
            "order"                 => "DESC",
            "post_status"           => "publish",
            'no_found_rows'         => true,
            'ignore_sticky_posts'   => true,
          ];
          $feed_updates = new WP_Query($args);
          

          if ($feed_updates->have_posts()): 
            $counter = 0;
            while ($feed_updates->have_posts()):
              $feed_updates->the_post(); 
              $post_id = get_the_ID(); 
              $post_time_data = my_post_time_ago_function(); 
              $slug = get_post_field('post_name', $post_id);

              
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
        
            <!-- Loop Through Feed Updates -->
          <a href="<?php echo esc_url( get_permalink() ); ?>" class="group block w-full rounded-md v2-dark-reg text-inherit visited:text-inherit hover:text-primary500" data-reply-box="<?= esc_attr( $post_id ) ?>">

            <article class="v2-feed-update-container w-full" id="<?= esc_attr( $slug ) ?>" itemscope itemtype="https://schema.org/BlogPosting" itemprop="liveBlogUpdate">

             
              <?php
                // AVATAR AND VOTES
                $author_id     = (int) get_the_author_meta('ID');
                $author_name   = get_the_author_meta('display_name');
                $author_avatar = get_avatar_url($author_id, 32);
              ?>
              <!-- Feed Update Content -->
              
                <section class="flex gap-2 mb-4 items-center">
                  <img src="<?= esc_url($author_avatar); ?>" class="rounded-full w-10 h-10"
                      alt="<?= esc_attr($author_name); ?>" loading="lazy" decoding="async">
                  <div class="flex flex-col leading-tight">
                    <div class="font-sans font-semibold text-gray-600 m-0 p-0">
                      <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                        <span itemprop="name"><?= esc_html($author_name); ?></span>
                      </span>
                    </div>
                    <time class="<?= esc_attr($post_time_data['class']); ?> text-xs"
                          datetime="<?= esc_attr(get_the_date('c', $post_id)); ?>"
                          itemprop="datePublished" data-nosnippet>
                      <?= esc_html($post_time_data['time_diff']); ?>
                    </time>
                    <meta itemprop="dateModified" content="<?= esc_attr(get_the_modified_date('c', $post_id)); ?>">
                  </div>
                </section>

                <h3 class="text-base font-sans font-semibold text-primary500" itemprop="headline">
                  <span id="title-<?= $post_id; ?>"><?= esc_html(get_the_title()); ?></span>
                </h3>

                <div class="text-sm mb-2" id="content-wrapper-<?= $post_id; ?>">
                  <div id="content-<?= get_the_ID(); ?>" class="bbj-feed-content" itemprop="articleBody"><?= get_the_content(); ?></div>

                  <!-- Hidden Textarea for Content Edit -->
                  <textarea id="content-input-<?= $post_id; ?>" style="display: none;"
                            class="border border-purple-400 w-full"><?php echo esc_textarea(get_the_content()); ?></textarea>

                  <?php // if featured image 
                  if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('featured-feed-update', [
                      'class'    => 'w-full h-auto rounded-lg',
                      'loading'  => 'lazy',
                      'decoding' => 'async',
                      'sizes'    => '(min-width:1024px) 700px, (min-width:640px) 90vw, 100vw',
                      'itemprop' => 'image',
                    ]); ?>
                  <?php endif; ?>
                </div>
                <div class="text-xs text-right border-t border-gray-400 font-ibm  text-slate-700 v2-dark-reg">
                  <i class="fa-regular fa-message"></i>
                  <?php
                    $comment_count = get_comments_number();
                    echo $comment_count . ' ' . ($comment_count == 1 ? 'Comment' : 'Comments');
                  ?>
                </div>
              
              
            </article>
            </a>
            <?php 
            $counter++;
            endwhile; 
            wp_reset_postdata();            
          endif;  
          ?>
          <!-- End Loop Through Feed Updates -->
          </div>
        <div class="w-full text-center text-xl font-mainHead"><a href="/feed-updates/">View More Feed Updates Here</a></div>
        </section>

        <div class="w-full lg:w-[300px] lg:shrink-0" >
          <section aria-labelledby="main-stats" class="v2-primary-container-inner mb-4" >
              <h2 id="main-stats" class="font-mainHead text-2xl text-primary500 uppercase p-2"><?php echo $season_name; ?> Houseboard</h2>
              <div class="p-2">
                <?= do_shortcode("[bbj_summary_table]") ?>
              </div>
          </section>

          <section aria-labelledby="main-stats" class="v2-primary-container-inner mb-4" >
              <h2 id="main-stats" class="font-mainHead text-2xl text-primary500 uppercase p-2">Watch Live Feeds</h2>
                <div class="p-2">
                  <a rel="sponsored"
                    href="https://paramountplus.qflm.net/c/161260/3116110/3065" target="_top" id="3116110">
                    <div><img src="//a.impactradius-go.com/display-ad/3065-3116110" border="0" alt="" width="290" height="46"/></div>
                    <div class=" text-mainHead text-center font-semibold">One Week Free</div>
                  </a>
                  <img height="0" width="0" src="https://paramountplus.qflm.net/i/161260/3116110/3065" style="position:absolute;visibility:hidden;" border="0" />
                </div>
          </section>    

          <section aria-labelledby="main-stats" class="v2-primary-container-inner" >
              <h2 id="main-stats" class="font-mainHead text-2xl text-primary500 uppercase p-2"><?php echo $season_name; ?> Stats</h2>
              <div class="p-2">
                <?= do_shortcode("[bbj_standings_table season_id='" . esc_attr($curSeasonID) . "']") ?>
              </div>
          </section>
        </div>
      </div>


      
      <?php bbj_echo_ad( 'index_mid' ); ?>

      <?php

    
      
      /**
       * 
       * PLEASE REC
       * 
       * 
       */
      
      
      //if (!is_paged()):
          // If Paged ?>
      <!-- Feed Updates and Featured Post block -->
      

      



      <div class="flex flex-grow p-2 flex-col " id="main-feeds">   


        
        <?php /*
        <!-- Live Feed Update Block -->
        <section id="feed-updates" class="w-full bg-white dark:bg-slate-800 p-2" aria-labelledby="feed-updates-heading">
          <?php if (!is_paged()):  // If Paged ?>
          <h2 class="font-mainHead text-2xl text-primary500 p-2">Live Feed Updates</h2>
          
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
                        <img src="<?= $author_avatar ?>" class="rounded-full w-4 h-4 mr-2" alt=""> <?php the_author(); ?> <span class="<?php echo $post_time_data["class"] ?> ml-2 text-xs"  data-nosnippet> <?php echo $post_time_data["time_diff"] ?></span>
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
        </section>
        

      <?php endif;  // End first page stuff?>
      */?>
        <div id="more-posts" class="w-full mt-6 border-t border-gray-200 pt-4 bg-white dark:bg-slate-800 p-2">
        
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
              $post_id = get_the_ID();
              
              $counter++;
              $post_time_data = my_post_time_ago_function();
          ?>
          <div class="border-b border-gray-300 flex flex-col md:flex-row py-4">
            <div class="flex-shrink-0 w-full md:w-[250px] "><a href="<?php the_permalink(); ?>"><img src="<?php echo the_post_thumbnail_url("featured-thumbnail"); ?>" class="w-full h-[150px]" alt="<?php esc_attr(the_title()); ?>"></a></div>
            <div class="grid grid-cols-2 w-full pl-2">
              <!-- First row -->

              <?php $categories = get_the_category(); ?>
              <div class="font-ibm text-sm text-left text-gray-500"><?php echo !empty($categories) ? esc_html($categories[0]->name) : "Uncategorized"; ?></div>
              <div class="font-ibm text-sm text-right text-gray-500"  data-nosnippet><?= $post_time_data["time_diff"] ?></div>
              
              <!-- Second row -->
              <div class="col-span-2">
                <div class="font-mainHead text-2xl"><a href="<?php the_permalink(); ?>"><h2><?php esc_attr(the_title()); ?></a></div>
                <div class="text-xs"><?php echo bbj_time_tags( $post_id, true ); ?></div>
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
        

        
      
      <!-- Feed Updates and Featured Post block end -->
      
    
    

    </section>
    
    <?php get_template_part("template-parts/sidebar-default"); ?>
      
  </div>
  

  
  
</main>

<script>
  window.userLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
</script>



<?php get_footer();