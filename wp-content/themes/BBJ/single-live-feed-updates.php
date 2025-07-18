<?php
get_header(); ?>



<div id="page-id" data-id="<?= get_the_ID() ?>"></div>

<div class="bbj-container-inner">

<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

<?php $post_time_data = my_post_time_ago_function(); ?>
  <section id="blog-post" class="rounded-md w-full flex flex-col lg:flex-row bg-white"> 

    <div class="container mx-auto relative">
      <div class="absolute top-4 w-full pl-4">
        <?php if (function_exists("yoast_breadcrumb")) {
            yoast_breadcrumb('<p id="breadcrumbs-new">', "</p>");
            } ?>
      </div>

      <div class="w-full bg-primary500 flex justify-between p-2">
        <div class="text-left bg-gray-200 border border-sky-500 py-1 px-2 rounded-lg text-sm">
          <?php
          $prev_post = get_previous_post();
          if (!empty($prev_post)): ?>
            <a href="<?php echo get_permalink($prev_post->ID); ?>"><i class="fa-solid fa-backward"></i> Previous Update</a>
          <?php endif; ?>
        </div>
        <div class="text-center">
          <a href="/feed-updates/" class="text-white visited:text-white"><i class="fa-solid fa-angle-up"></i> Return to feed updates</a>
        </div>
        <div class="text-right bg-gray-200 border border-sky-500 py-1 px-2 rounded-lg text-sm">
          <?php
          $next_post = get_next_post();
          if (!empty($next_post)): ?>
            <a href="<?php echo get_permalink($next_post->ID); ?>">Next Update <i class="fa-solid fa-forward"></i></a> 
          <?php endif; ?>
        </div>        
      </div>


      
      <div class="bg-primary500 h-[60px]"></div>
        

      <div class="blog-post mx-auto bg-white w-full md:w-[90%] rounded-xl p-0.5 md:p-2 z-10  mb-10 -mt-[25px]">
        
        <div class="flex justify-between border-b border-gray-200 p-2">          
          <div class="flex justify-center items-center">
            
            <?php
            $author_id = get_the_author_meta("ID");
            $avatar_url = get_avatar_url($author_id, ["size" => 32]);
            $author_name = get_the_author();
            ?>
            <div><img src="<?php echo $avatar_url; ?>"class="rounded-full w-8 h-8 mr-2"alt="Author Avatar"></div>
            <div class="text-gray-500">Author: <span class="font-bold"><?php echo $author_name; ?></span></div>  
          </div>
          <div class="text-xs text-right">
            <div class="<?php echo $post_time_data["class"] ?>"><?php echo $post_time_data["time_diff"] ?></div>  
            <div class="text-gray-600"><?= get_the_date('m/d/y h:ia'); ?></div>
          </div>
        </div>

        <div class="prose-base prose-slate p-2 "> 

          <h1 class="text-lg md:text-3xl font-bold mb-1 p-2"><?php the_title(); ?></h1>

          <?php get_template_part("template-parts/quicklinks"); ?>

          <?php show_front_responsive() ?>

          <?php //featured image 
          
          if (has_post_thumbnail()): ?>
          <div>
            <img src="<?= the_post_thumbnail_url( "featured-image-header" )?>" alt="" class="mt-2">
          </div>
          <?php endif; ?>

          <?php the_content(); ?>  
      
        </div>

        <?php show_after_content(); ?>


        <div>
          <?php if (comments_open()):
            comments_template();
          endif; ?>
        </div>

      </div>
    </div>

    <div class="border-l border-gray-200" id="bbj-sidebar">
    
        
      <?php get_template_part("template-parts/sidebar-default"); ?>

    </div>

  </section>

<?php endwhile; endif; ?>
</div>

<?php get_footer(); ?>
