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



	<div class="flex w-full flex-col mb-4  lg:flex-row dark:text-gray-200">
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
            
            <div class="-mt-1 bg-white pb-1 w-fit flex items-center rounded-tr-md font-ibm text-xs text-slate-700 v2-dark-reg">              
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
        <section class="v2-primary-container-inner grow p-2 mb-4 lg:mb-0" aria-labelledby="main-feeds" itemscope itemtype="https://schema.org/LiveBlogPosting">
         

          <h2 id="main-feeds" class="v2-primary-subheader" itemprop="headline">Latest Feed Updatessss</h2>
          <div class="lg:h-[1300px] lg:min-h-0 lg:overflow-y-auto ">
          
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
            if ( in_array( $counter, [2, 7], true ) ) {
              if ( function_exists('bbj_echo_ad') ) {
             
               bbj_echo_ad('between_feeds');
                
              }
            }
            endwhile; 
            wp_reset_postdata();            
          endif;  
          ?>
          <!-- End Loop Through Feed Updates -->
          </div>
        <div class="w-full text-center text-xl font-mainHead"><a href="/feed-updates/">View More Feed Updates Here</a></div>
        </section>

        <div class="w-full lg:w-[300px] lg:shrink-0" >
            <section class="v2-primary-container-inner mb-4 p-2">
              <?php get_template_part("template-parts/socials"); ?>
            </section>

          <section aria-labelledby="main-stats" class="v2-primary-container-inner mb-4 p-2" >
              <h2 id="main-stats" class="v2-primary-subheader"><?php echo $season_name; ?> Houseboard</h2>
              <div class="p-2">
                <?= do_shortcode("[bbj_summary_table]") ?>
              </div>
          </section>

          <section aria-labelledby="main-stats" class="v2-primary-container-inner mb-4 p-2" >
              <h2 id="main-stats" class="v2-primary-subheader">Watch Live Feeds</h2>
                <div class="p-2 flex justify-center items-center flex-col">
                  <a rel="sponsored"
                    href="https://paramountplus.qflm.net/c/161260/3116110/3065" target="_top" id="3116110">
                    <div><img src="//a.impactradius-go.com/display-ad/3065-3116110" border="0" alt="" width="290" height="46"/></div>
                    <div class=" text-mainHead text-center font-semibold">One Week Free</div>
                  </a>
                  <img height="0" width="0" src="https://paramountplus.qflm.net/i/161260/3116110/3065" style="position:absolute;visibility:hidden;" border="0" />
                </div>
          </section>    

          <section aria-labelledby="main-stats" class="v2-primary-container-inner p-2" >
              <h2 id="main-stats" class="v2-primary-subheader"><?php echo $season_name; ?> Stats</h2>
              <div class="p-2">
                <?= do_shortcode("[bbj_standings_table season_id='" . esc_attr($curSeasonID) . "']") ?>
              </div>
          </section>
        </div>
      </div>


      
      <?php bbj_echo_ad( 'index_mid' ); ?>

      
      
      
      <section class="v2-primary-container-inner mb-4 p-2" aria-labelledby="more-posts">
        <h2 id="more-posts" class="v2-primary-subheader">More Stories & News</h2>

        

        <?php
        // Show next 10 posts, excluding the hero
        $posts_per_page = 10;
        $more_args = [
          'post_type'              => 'post',
          'post_status'            => 'publish',
          'posts_per_page'         => $posts_per_page,
          'orderby'                => 'modified',
          'order'                  => 'DESC',
          'ignore_sticky_posts'    => true,
          'post__not_in'           => array_filter([$hero_id]), // exclude hero if present
          'no_found_rows'          => true,     // no pagination needed
          'update_post_term_cache' => false,
          'update_post_meta_cache' => false,
        ];

        $more_q = new WP_Query($more_args);

        if ($more_q->have_posts()):
          while ($more_q->have_posts()):
            $more_q->the_post();
            $post_id        = get_the_ID();
            $post_time_data = my_post_time_ago_function();
            $thumb_url      = get_the_post_thumbnail_url($post_id, 'featured-thumbnail');
            $categories     = get_the_category();
        ?>
          <article class="border-b border-gray-300 flex flex-col md:flex-row py-4">
            <div class="flex-shrink-0 w-full md:w-[250px]">
              <a href="<?php the_permalink(); ?>">
                <?php if ($thumb_url): ?>
                  <img
                    src="<?= esc_url($thumb_url); ?>"
                    class="w-full h-[150px] object-cover rounded-lg"
                    alt="<?= esc_attr(get_the_title()); ?>"
                    loading="lazy" decoding="async"
                  />
                <?php endif; ?>
              </a>
            </div>

            <div class="grid grid-cols-2 w-full pl-2">

              <!-- Row 1 -->
              <div class="col-span-2">
                <h3 class="font-mainHead text-2xl">
                  <a href="<?php the_permalink(); ?>"><?= esc_html(get_the_title()); ?></a>
                </h3>
                <div class="text-xs flex"><?= bbj_time_tags($post_id, true); ?>
                  <span class="ml-2 text-xs hidden lg:inline <?php echo $post_time_data["class"] ?>"  data-nosnippet>
                    <?= esc_html( $post_time_data['time_diff'] ) ?>
                  </span>
                </div>
                <div class="text-sm">
                  <?= esc_html( wp_trim_words( wp_strip_all_tags( get_the_content(null, false, $post_id) ), 55, '…' ) ); ?>
                </div>
              </div>

              <!-- Row 2 -->
              <div class="font-ibm text-sm text-gray-500 text-left">
                <?= esc_html(get_the_author_meta('display_name')); ?>
              </div>
              <div class="font-ibm text-sm text-gray-500 text-right">
                <?= comments_number('No comments', '1 comment', '% comments'); ?>
              </div>
            </div>
          </article>
        <?php
          endwhile;
          wp_reset_postdata();
        endif;
        ?>
        <div class="w-full text-center mt-4 text-xl font-mainHead">
          <a href="/page/2/">View More Stories & News Here</a>
        </div>
      </section>   

    </section>
    
    <?php get_template_part("template-parts/sidebar-default"); ?>
      
  </div>
  

  
  
</main>

<script>
  window.userLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
</script>


<?php get_footer();