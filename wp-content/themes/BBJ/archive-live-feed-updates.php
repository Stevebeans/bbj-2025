<?php
get_header(); ?>

<div class="new-body-container" id="post-<?php the_ID(); ?>">

   <article>  
    <div class="widgetContain boxShadowsft">
        <div class="widgetHeader">
          <div class="titleBar"></div>
          <?php custom_breadcrumbs(); ?>
                       
        </div>
        <div class="widgetBody">
          <?php
          $primary_category = rwmb_meta("current_season", ["object_type" => "setting"], "bbj_settings");
          $current_season = get_the_title($primary_category);
          ?>
          <h1><?php echo $current_season; ?> Live Feed Updates</h1>


          <div class="entry-content">
            <div class="post-content">

            
            <div class="feed-update-container">
                <div class="update-left">
                <div class="feed-update">
                <?php $stream = '[ajax_load_more id="3056419650" container_type="div" cache="true" cache_id="8086353915" paging="true" paging_show_at_most="25" paging_scroll="true:100" paging_controls="true" paging_previous_label="Prev" paging_next_label="Next" preloaded="true" seo="true" theme_repeater="feed-list.php" post_type="live-feed-updates" posts_per_page="15" cta="true" cta_position="after:7" cta_theme_repeater="adsense-feed-updates.php"]';
// $stream = '[ajax_load_more id="9371596830" container_type="div" cta="true" cta_position="before:5" cta_theme_repeater="adsense-feed-updates.php" seo="true" theme_repeater="feed-list.php" post_type="live-feed-archives" posts_per_page="10" destroy_after="20"]';
?> 
                  <?php echo do_shortcode($stream); ?>
                  
</div>
              </div>
              
              <?php get_template_part("template-parts/sidebar-spoiler-box"); ?>
            </div>
            </div>

          </div>
        </div>
    </div>
  </article>


  <div class="post-bottom">
    <div class="comment-section">
      <?php if (comments_open()):
        comments_template();
      endif; ?>
    </div>

    <aside class="comment-aside">
      Comment Stuff
    </aside>
  </div>

  
</div>
<?php get_footer();
