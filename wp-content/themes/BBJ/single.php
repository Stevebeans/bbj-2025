<?php
get_header(); ?>


<div id="page-id" data-id="<?= get_the_ID() ?>"></div>
<div class="bbj-container-inner">

  <?php while (have_posts()):
    the_post(); ?>

  <!-- outer section -->
  <section id="blog-post" class="rounded-md w-full flex flex-col lg:flex-row bg-white">
    <div class="container mx-auto relative">
      <div class="absolute top-4 w-full pl-4">
        <?php if (function_exists("yoast_breadcrumb")) {
            yoast_breadcrumb('<p id="breadcrumbs-new">', "</p>");
            } ?>
      </div>

      <div class="absolute top-64 w-full">
        <h1 class="text-lg md:text-3xl font-bold mb-1 text-white pl-3"><?php the_title(); ?></h1>
        <div class="text-white pl-3 mb-2 text-sm"><?php the_modified_date(); ?> | 
          <A href="#wpd-threads" class="text-second500 hover:text-secondHard !underline underline-offset-2"><?php echo $post->comment_count; ?> Comments</a>
        </div>  
      </div> 

      <?php if (has_post_thumbnail()): ?>
      <div class="featured-image h-[450px]" style="background-image: url('<?php the_post_thumbnail_url("featured-image-header"); ?>'); background-size: cover;"></div>
      <?php else: ?>
      <div class="bg-primary500 h-[450px]"></div>
      <?php endif; ?>  


      <div class="blog-post mx-auto bg-white w-full md:w-[90%] rounded-xl p-0.5 md:p-2 z-10  mb-10 -mt-[100px]">
        
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
        </div>

        <div class="p-2"> 

          <?php get_template_part("template-parts/quicklinks"); ?>

          
          <article  class="prose-base prose-slate">
          <?php the_content(); ?>  
          </article>
      
      </div>

          <?php //show_after_content(); ?>

          <?php get_template_part("template-parts/related-posts"); ?>          


        <div id="bbj-comment-system"></div>

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

        <?php
        endwhile;
        // End the loop.
        ?>
</div>

<?php get_footer();
