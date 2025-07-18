<?php

/**
* Template Name: Single Sidebar - Full
*/
get_header();
?>

<div class="bodyContainer">
	<!-- Main   -->
	<div class="mainBody">
		<div class="widgetContain boxShadowsft">
			<div class="widgetHeader">
        <div class="titleBar"></div>
          <h2 class="widgetTitle"><?php the_title( ); ?></h2>        
      </div>
      <div class="widgetBody">
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
	</div>

  <?php get_template_part( 'template-parts/sidebar-main' )?>
</div>
<?php
get_footer();