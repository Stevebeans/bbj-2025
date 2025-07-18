<?php
/*
        $paged = get_query_var("paged") ? get_query_var("paged") : 1;

        $args = [
          "post_type" => "live-feed-updates",
          "posts_per_page" => 15,
          "orderby" => "modified",
          "order" => "DESC",
          "paged" => $paged,
        ];
        $feed_updates = new WP_Query($args);
        $counter = 0;
        ?>


        <div id="new-post-message" class="flex p-4 mb-4 text-primary500 border-t-4 border-sky-300 bg-sky-50 dark:text-blue-400 dark:bg-gray-800 dark:border-blue-800" role="alert">
    <svg class="flex-shrink-0 w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
    <div class="ml-3 text-sm font-medium">
      A simple info alert with an <a href="#" class="font-semibold underline hover:no-underline">example link</a>. Give it a click if you like.
    </div>
    <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-blue-50 text-primary500 rounded-lg focus:ring-2 focus:ring-blue-400 p-1.5 hover:bg-blue-200 inline-flex h-8 w-8 dark:bg-gray-800 dark:text-blue-400 dark:hover:bg-gray-700" data-dismiss-target="#new-post-message" aria-label="Close">
      <span class="sr-only">Dismiss</span>
      <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
    </button>
      </div>

      <?php if ($feed_updates->have_posts()): ?>
        <?php while ($feed_updates->have_posts()):

          $feed_updates->the_post();
          $counter++;
          ?>


          <div class="p-2 border-b last:border-0 border-gray-400 flex gap-4 mb-4">          
            <div class="row-span-2 w-10 flex justify-center items-start">
              <div>
                <?php
                $author_id = get_the_author_meta("ID");
                $avatar_url = get_avatar_url($author_id, ["size" => 32]);
                ?>
                <img src="<?php echo $avatar_url; ?>"class="rounded-full w-10 h-10"alt="Author Avatar"> 
              </div>  
            </div>

            <div>
              
              <div class="text-red-500 text-sm"><?php echo the_modified_date(); ?></div>
              <div>
                <div class="text-gray-800 text-lg"><?php the_title(); ?></div>
                <div class="text-gray-800"><?php the_content(); ?></div>
              </div>
            </div>

          </div>

          <?php if (!premiumCheck() && ($counter == 3 || $counter == 8 || $counter == 14)): ?>
          <div class="md:col-span-2">
          <?php get_template_part("template-parts/ads/ad-in-article"); ?>
          </div>
          <?php endif; ?>
        <?php
        endwhile; ?>
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
        </nav>*/
?>
