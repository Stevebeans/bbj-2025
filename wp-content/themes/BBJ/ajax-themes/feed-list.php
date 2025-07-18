
<?php
global $post;
$get_author_id = $post->post_author;
?>

  <div class="feed-update-post">
    <div class="header"><h3><?php the_title(); ?></h3></div>
    <div class="date"><?php the_modified_date(); ?></div>
    <div class="body">

    
    <?php if (has_post_thumbnail()):
      echo '<div class="feed-update-featured">';
      $thumb = the_post_thumbnail($size = "featured-thumbnail");
      echo "</div>";
    endif; ?>  
    <?php the_content(); ?></div>
  </div>
