<?php
get_header();
global $post;
?>

<div class="new-body-container" id="post-<?php the_ID(); ?>">

   <article>  
    <div class="widgetContain boxShadowsft">
        <div class="widgetHeader">
          <div class="titleBar"></div>
          <?php custom_breadcrumbs(); ?>
                       
        </div>
        <div class="widgetBody">
          <?php the_title('<h1 class="blogTitle">', "</h1>"); ?>


          <div class="entry-content">
            <div class="featured-image">
              <?php if (has_post_thumbnail()) {
                the_post_thumbnail();
              } ?>                  
            </div>
              <?php get_template_part("template-parts/single-blog-post-no-meta"); ?>

            <h3>Related Posts</h3>
            <div class="related-posts">
              <?php example_cats_related_post(); ?>
            </div>
          </div>
        </div>
    </div>
  </article>




  
</div>
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
<?php get_footer();
