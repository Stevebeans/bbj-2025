<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package bigbrotherjunkies
 */

get_header();
?>


<div class="body-regular" id="post-<?php the_ID(); ?>">

   <article>  
    <div class="widgetContain boxShadowsft">
        <div class="widgetHeader">
          <div class="titleBar"></div>
          <?php custom_breadcrumbs(); ?>
                       
        </div>
        <div class="widgetBody">
          <?php the_title( '<h1 class="blogTitle">', '</h1>' ); ?>


          <div class="entry-content">
            <div class="featured-image">
              <?php 
                if (has_post_thumbnail())
                  the_post_thumbnail( ); 
                ?>                  
            </div>

            <div class="post-content">

                <?php 
                  the_content();
                ?>
            </div>

          </div>
        </div>
    </div>
  </article>



  <?php get_template_part( 'template-parts/sidebar-pages' )?>

  
</div>
<?php
get_footer();
