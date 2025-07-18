<?php get_header(); 

$curSeason = currentSeason("name");
$curSeasonID = currentSeason("ID");


$user_id = get_current_user_id();
$posts_per_page = get_user_meta($user_id, 'feed_update_count', true);
?>


<div class="bbj-container-inner">


  <div class="bbj-inner-content-container">
    <div class="bbj-content-container">
      <div class="mt-6">
          <h1 class="font-mainHead text-3xl text-primary500"><?= $curSeason ?> Live Feed Updates</h1>
          <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>    


          <?php 
          
          // Get lastest live feed post 

          $args = [
            "post_type" => "post",
            "post_status" => "publish",
            "posts_per_page" => 1,  // Fetch only one post
            "orderby" => "date",  // Sort by most recent date
            "order" => "DESC",  // Descending order
            "category_name" => "live-feed-updates-big-brother-26"  // Specify the category by its slug
          ];
        
          $recent_posts_query = new WP_Query($args);
          if ($recent_posts_query->have_posts()) :
              $recent_posts_query->the_post();  // Setup global post data
        
          
          ?>

                    
            <div id="highlight-posts" class="w-full flex-grow mt-4 md:mt-0">
                <h2 class="font-mainHead text-2xl text-primary500">Latest Discussion Thread</h2>

                <div class="w-full bg-neutral-100 shadow-frontBox flex flex-col md:flex-row p-1 md:p-2">
                    <div class="w-full md:w-[350px] flex-shrink-0">
                        <a href="<?php the_permalink(); ?>">
                            <img src="<?php echo the_post_thumbnail_url("featured-thumbnail"); ?>" class="w-full h-[225px] rounded-md" alt="<?php echo $curSeason ?> featured post">
                        </a>
                    </div>
                    <div class="flex flex-col min-h-[225px] h-full px-1 md:px-2">
                        <h2 class="font-mainHead text-2xl font-bold mt-2 md:mt-0"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <?php $post_time_data = my_post_time_ago_function(); ?>
                        <div class="font-ibm text-sm <?php echo $post_time_data["class"]; ?>"><?php echo $post_time_data["time_diff"]; ?></div>
                        <div class="text-sm flex-grow">
                            <?php echo wp_trim_words(get_the_content(), 85, "..."); ?> <span class="read-more"><a href="<?php the_permalink(); ?>" >Read More...</a></span>
                        </div>
                        <div class="flex justify-between font-ibm text-sm mt-auto">
                            <div>By: <?php echo get_the_author_meta("display_name"); ?></div>
                            <div><?php echo get_comments_number(); ?> comments</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            endif;
            wp_reset_postdata();
            ?>

        <?php spot_two(); ?>
        <div id="new-feed-updates"></div>

        <div id="loginModal" class="hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
          <div class="bg-white p-8 rounded relative">
            <button id="closeLoginModal" class="absolute top-2 right-2 text-gray-700">
              <i class="fa-solid fa-x"></i>
            </button>
            <p>You must be logged in to rate posts. Please <a href="/log-in">login</a> or <a href="/registration">register</a> here.</p>
          </div>
        </div>


        <?php
        $paged = get_query_var("paged") ? get_query_var("paged") : 1;

        $sort = $_GET["sort"] ?? "newest";
        $date_range = $_GET["date-range"] ?? "all";
        
        

       

        $args = [
          "post_type" => "live-feed-updates",
          "posts_per_page" => $posts_per_page,
          "paged" => $paged,
        ];

        switch ($sort) {
          case "newest":
            $args["orderby"] = "modified";
            $args["order"] = "DESC";
            break;
          case "oldest":
            $args["orderby"] = "modified";
            $args["order"] = "ASC";
            break;
          case "highest":
            $args["meta_key"] = "total_rating";
            $args["orderby"] = "meta_value_num";
            $args["order"] = "DESC";
            break;
          case "lowest":
            $args["meta_key"] = "total_rating";
            $args["orderby"] = "meta_value_num";
            $args["order"] = "ASC";
            break;
          default :
            $args["orderby"] = "modified";
            $args["order"] = "DESC";
            break;
        }

        if ($date_range !== "all") {
          $args["date_query"] = [];
          switch ($date_range) {
            case "today":
              $args["date_query"]["after"] = "today";
              break;
            case "yesterday":
              $args["date_query"]["after"] = "yesterday";
              $args["date_query"]["before"] = "today";
              break;
            case "week":
              $args["date_query"]["after"] = "1 week ago";
              break;
            case "month":
              $args["date_query"]["after"] = "1 month ago";
              break;
            case "year":
              $args["date_query"]["after"] = "1 year ago";
              break;
          }
        }


        bbj_log2(print_r($meta_value, true));



        $feed_updates = new WP_Query($args);
        $counter = 0;


        ?>
        

        <form method="get">
        <div class="w-full text-right flex my-2 justify-end items-center">
          <div class="mr-2">
          Sort

          <select name="sort" id="sort" class="border border-gray-300 rounded-md p-1">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="highest" <?= $sort === 'highest' ? 'selected' : '' ?>>Highest Rated</option>
            <option value="lowest" <?= $sort === 'lowest' ? 'selected' : '' ?>>Lowest Rated</option>
          </select>
          </div>

          <div class="mr-2">
            Date Range 

            <select name="date-range" id="date-range" class="border border-gray-300 rounded-md p-1">
              
              <option value="all" <?= $date_range === '' ? 'selected' : '' ?> >All</option>
              <option value="today" <?= $date_range === 'today' ? 'selected' : '' ?>>Today</option>
              <option value="yesterday" <?= $date_range === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
              <option value="week" <?= $date_range === 'week' ? 'selected' : '' ?>>This Week</option>
              <option value="month" <?= $date_range === 'month' ? 'selected' : '' ?>>This Month</option>
              <option value="year" <?= $date_range === 'year' ? 'selected' : '' ?>>This Year</option>
            </select>
          </div>

          <div><button class="bg-sky-200 border border-sky-600 rounded-xl px-2 py-0.5  w-20 flex justify-center items-center">Update</button></div>
        </div>
      </form>

        <?php if ($feed_updates->have_posts()): ?>
        <?php while ($feed_updates->have_posts()):

          $feed_updates->the_post();
          $counter++;


          $post_id = get_the_ID();  // Get the current Post ID
          set_query_var('post_id', $post_id);  // Pass it to the template part
          

          ?>
          <?php $post_time_data = my_post_time_ago_function(); ?>


          <div class="border-primary500 hover:bg-slate-200 border rounded-md p-1 flex relative" data-reply-box="<?= $post_id ?>">  
          
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
              <div class="bg-gray-200 p-1 w-full flex items-center">
                  <?php 
                  // get author avatar 
                  $author_id = get_the_author_meta("ID");
                  $author_avatar = get_avatar_url($author_id, 32);              
                  ?>
                  <!-- Prevent this div from shrinking and give it a minimum width (You can adjust the min-width as needed) -->
                  <div class="font-ibm text-sm flex-shrink-0 flex min-w-fit items-center">
                      <img src="<?= $author_avatar ?>" class="rounded-full w-6 h-6 mr-2" alt=""> <?php the_author(); ?>
                  </div>

                  <!-- This div will take up the remaining space -->
                  <div class="text-xs flex flex-grow items-center justify-end">
                      
                      <span class="<?php echo $post_time_data["class"] ?>"><?php echo $post_time_data["time_diff"] ?><br /><?= get_the_date('m/d/y h:ia'); ?></span>  
                  </div>
              </div>


              <div class="text-lg font-semibold p-2">
                <a href="<?php the_permalink(); ?>" class="text-primary500">
                <?php the_title(); ?>
                </a>
              </div>
              <div class="text-center">
                <?php
                    if (has_post_thumbnail()) {
                      echo get_the_post_thumbnail(null, 'large', array( 'class' => 'text-center mx-auto rounded-lg my-1' ));
                    }
                  ?>
              </div>
              <div class="p-2" id="main-page-feed"><?= get_the_content() ?></div>
              <div class="px-2 border-t border-gray-300 flex justify-between items-center">
                <div class="text-gray-500 underline">
                <a href="<?= rtrim(get_permalink(), '/') ?>#wpd-main-form-wrapper-0_0">
                  <?php
                    $comment_count = get_comments_number();
                    echo $comment_count . ' ' . ($comment_count == 1 ? 'Comment' : 'Comments');
                  ?>
                </a>

                </div>
                
                
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

                  

                <?php endif; ?>
                    
              </div>

              <!-- <div class="text-xs text-primary500 hover:cursor-pointer absolute bottom-[-10px] right-2 reply-button z-30">
                <div class="bg-sky-200 border border-sky-600 rounded-xl px-2 py-0.5  w-20 flex justify-center items-center reply-text">
                <i class="fa-solid fa-reply mr-2 reply-icon"></i> <span>Reply</span>
                </div>
              </div> -->


            </div>

          </div>


            <?php 
            
            
            //get_template_part('template-parts/update-comments');            
            ?>


          <div class="mb-6"></div>

          <?php if ($counter % 5 === 0): ?>
            <?php front_between_feed_updates() ?>
        <?php endif; ?>
        <?php endwhile; ?>
        <?php else: ?>
          <p>No updates found</p>
        <?php endif; ?>
        <?php wp_reset_postdata(); ?>
      </div>


      <nav class="w-full flex items-center justify-between p-2">
        <div class="hidden md:block grow text-sm text-gray-700 dark:text-gray-200">
          <?php
          $page = get_query_var("paged") ? get_query_var("paged") : 1;
          $page_count = $feed_updates->max_num_pages;
          echo "Showing: ";
          echo sprintf("%d–%d of %d", ($page - 1) * $feed_updates->query_vars["posts_per_page"] + 1, min($feed_updates->found_posts, $page * $feed_updates->query_vars["posts_per_page"]), $feed_updates->found_posts);
          ?>
        </div>

        <div class="flex rounded-md shadow border border-gray-200 w-full max-w-[400px] mx-auto dark:border-gray-400">
        <?php
        $pagination_links = paginate_links([
          "base" => get_pagenum_link(1) . "%_%",
          "format" => "page/%#%/",
          "current" => $paged,
          "total" => $feed_updates->max_num_pages,
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
        </nav>


    </div>



    <?php
    // Set the localstorage for lastViewedPostID for the latest post in the custom post type live-feed-updates using WP Query
    $args = [
      "post_type" => "live-feed-updates",
      "posts_per_page" => 1,
      "orderby" => "date",
      "order" => "DESC",
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) {
      $post = $query->posts[0];

      // Save post ID to localStorage
      echo "<script>localStorage.setItem('lastViewedPostId', " . $post->ID . ");</script>";

      // Save timestamp to localStorage
      echo "<script>localStorage.setItem('lastViewedTimestamp', " . time() . ");</script>";
    }

    wp_reset_postdata();
    ?>


    <div>
      <?php get_template_part("template-parts/sidebar-default"); ?>
    </div>
  </div>


</div>

<script>
  window.userLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;

</script>


<?php get_footer(); ?>
