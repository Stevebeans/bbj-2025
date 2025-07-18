<div class="newsArticle flex flex-col">           

<div class="newsSecond"><a href="<?php the_permalink(); ?>"><img src="<?php echo the_post_thumbnail_url("featured-thumbnail"); ?>" alt="<?php esc_attr(the_title()); ?>"></a></div>
  <div class="newsInfo2 flex-grow">              
    <div class="categoryInfo-sm"><?php echo $current_season; ?></div>
    <div class="coverHeadline"><a href="<?php the_permalink(); ?>"><h3><?php esc_attr(the_title()); ?></h3></a></div>
    <div class="coverExcerptSm flex-grow"><p><?php the_excerpt(); ?> <span><a href="<?php the_permalink(); ?>">Read More</a></span></p></div>
    <div class="frontMeta"><?php echo get_the_author_meta("display_name"); ?> <span class="timeStamp"> <?php the_modified_date(); ?></span></div>
  </div>

</div>