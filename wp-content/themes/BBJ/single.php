<?php
get_header(); ?>


<div id="page-id" data-id="<?= get_the_ID() ?>"></div>

<main class="v2-primary-container">
  <div class="flex w-full flex-col mb-4 lg:flex-row dark:text-gray-200">
    <section id="main-left" class="flex-grow space-y-4">

      <?php while ( have_posts() ) : the_post();
        $post_id        = get_the_ID();
        $post_time_data = function_exists('my_post_time_ago_function') ? my_post_time_ago_function() : ['class'=>'','time_diff'=>''];
        $thumb_desktop  = get_the_post_thumbnail_url($post_id, 'bbj_v2_index_hero');
        $thumb_mobile   = get_the_post_thumbnail_url($post_id, 'bbj_v2_index_mobile');
        $schema_type    = ( get_post_type() === 'post' )
                          ? 'https://schema.org/BlogPosting'
                          : 'https://schema.org/Article';
      ?>

      <article itemscope itemtype="<?= esc_attr($schema_type); ?>" class="v2-primary-container-inner">
        <meta itemprop="datePublished" content="<?= esc_attr( get_the_date('c', $post_id) ); ?>">
        <meta itemprop="dateModified"  content="<?= esc_attr( get_the_modified_date('c', $post_id) ); ?>">

        <h1 class="font-mainHead text-3xl md:text-4xl text-primary500 p-2" itemprop="headline">
          <?php the_title(); ?>
        </h1>

        <!-- Hero -->
        <div class="relative h-[333px] bg-gray-100 overflow-hidden">
          <div class="absolute inset-0">
            <?php if ( $thumb_desktop ): ?>
              <img
                src="<?= esc_url($thumb_desktop) ?>"
                class="w-full h-full hidden md:block object-cover bbj-hero-img"
                alt="<?= esc_attr( get_the_title($post_id) ) ?>"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                sizes="(min-width:768px) 100vw, 0px"
                width="1920" height="333"
              />
            <?php endif; ?>

            <?php if ( $thumb_mobile ): ?>
              <img
                src="<?= esc_url($thumb_mobile) ?>"
                class="w-full h-full md:hidden object-cover bbj-hero-img"
                alt="<?= esc_attr( get_the_title($post_id) ) ?>"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                sizes="100vw"
                width="750" height="333"
              />
            <?php endif; ?>

            <?php if ( ! $thumb_desktop && ! $thumb_mobile ): ?>
              <div class="absolute inset-0 bg-primary500"></div>
            <?php endif; ?>
          </div>

          <!-- Meta Badge -->
          <div class="absolute w-full z-10 bottom-0 left-0">
            <div class="bg-white px-3 py-1 w-fit flex items-center rounded-tr-md font-ibm text-xs text-slate-700 v2-dark-reg">
              <i class="fa-regular fa-message mr-1"></i>
              <?= comments_number('No comments','1 comment','% comments'); ?>
            </div>
          </div>
        </div>

        <div class="p-2">
          <!-- Time / Updated -->
          <div class="-mt-1 bg-white pb-1 w-fit flex items-center rounded-tr-md font-ibm text-xs text-slate-700 v2-dark-reg">
            <?= bbj_time_tags($post_id, true); ?>
            <?php if ( ! empty($post_time_data['time_diff']) ) : ?>
              <span class="ml-2 text-xs hidden lg:block <?= esc_attr($post_time_data['class']); ?>" data-nosnippet>
                <?= esc_html($post_time_data['time_diff']); ?>
              </span>
            <?php endif; ?>
          </div>

          <!-- Author + Breadcrumbs -->
          <div class="flex justify-between border-b border-gray-200 py-2">
            <div class="flex items-center">
              <?php
                $author_id   = get_the_author_meta('ID');
                $avatar_url  = get_avatar_url($author_id, ['size' => 32]);
                $author_name = get_the_author();
              ?>
              <img src="<?= esc_url($avatar_url); ?>" class="rounded-full w-8 h-8 mr-2" alt="<?= esc_attr($author_name); ?>">
              <div class="text-gray-500">Author: <span class="font-bold"><?= esc_html($author_name); ?></span></div>
            </div>

            <div class="text-gray-500 hidden md:block">
              <?php if ( function_exists('yoast_breadcrumb') ) { yoast_breadcrumb('<p id="breadcrumbs-new">', '</p>'); } ?>
            </div>
          </div>

          <?php get_template_part('template-parts/quicklinks'); ?>
          <?php bbj_echo_ad ('before_content') ?>
          <article
            role="presentation" style="display: contents;"
            id="bbj-article-body"
            class="prose-base prose-slate 
                  max-w-none dark:prose-invert
                  break-words selection:bg-yellow-200 selection:text-black
                  prose-headings:font-mainHead prose-h2:scroll-mt-24 prose-h3:scroll-mt-24
                  prose-a:no-underline hover:prose-a:underline prose-a:text-primary500 hover:prose-a:text-primary700 visited:prose-a:text-primary600
                  prose-img:rounded-lg prose-img:mx-auto
                  prose-figcaption:text-sm prose-figcaption:text-slate-500
                  list-disc list-inside prose-li:marker:text-primary500
                  prose-code:bg-slate-100 dark:prose-code:bg-slate-800 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:before:content-[''] prose-code:after:content-['']
                  prose-pre:rounded-md prose-pre:p-4
                  prose-table:w-full prose-th:text-left prose-td:p-2"
            itemprop="articleBody">
            <?php the_content(); ?>
            </article>


          <?php get_template_part('template-parts/related-posts'); ?>

          <?php if ( function_exists('bbj_print_article_jsonld') ) { bbj_print_article_jsonld($post_id); } ?>
        </div>
      </article>

      <section class="v2-primary-container-inner" id="comments">
        <h2 class="v2-primary-subheader">Comments</h2>
        <?php
          // Comments
          if ( comments_open() || get_comments_number() ) :
            echo '<div id="bbj-comment-system"></div>';
            comments_template();
          endif;
        ?>
      </section>



      <?php endwhile; ?>

    </section>

    <?php get_template_part('template-parts/sidebar-default'); ?>
  </div>
</main>







<?php get_footer();
