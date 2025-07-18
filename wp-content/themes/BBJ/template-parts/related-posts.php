<div class="border border-slate-400 rounded p-2">
  <h2 class="text-lg font-bold text-primary500 mb-2 ">Related Articles</h2>

  <div class="flex justify-between gap-2">
  <?php
  $current_post_id = get_the_ID();
  $categories = get_the_category();
  $category_id = $categories[0]->term_id;

  $args = [
    "posts_per_page" => 4,
    "orderby" => "date",
    "order" => "DESC",
    "post_type" => "post",
    "post_status" => "publish",
    "category__in" => $category_id,
    "post__not_in" => [$current_post_id],
  ];
  $related_posts = new WP_Query($args);

  if ($related_posts->have_posts()):
    while ($related_posts->have_posts()):
      $related_posts->the_post(); ?>
      <div class=" w-full">
        <?php if (has_post_thumbnail()): ?>
          
            <img src="<?php the_post_thumbnail_url("thumbnail"); ?>" alt="<?php the_title(); ?>" class="w-full rounded h-28 object-cover ">
          
        <?php endif; ?>
          <a href="<?php the_permalink(); ?>" class="text-sm pb-2 font-bold"><?php the_title(); ?></a>
      </div>
  <?php
    endwhile;
    wp_reset_postdata();
  endif;
  ?></div>
</div>
