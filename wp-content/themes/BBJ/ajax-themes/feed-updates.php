
<?php
global $post;
$get_author_id = $post->post_author;

$user = wp_get_current_user();
$allowed_roles = ["editor", "administrator"];
?>

<?php if (array_intersect($allowed_roles, $user->roles)): ?>
<?php endif; ?>
  <div class="feed-update-post">
    <div class="header"><h3><?php the_title(); ?></h3></div>
    <div class="date"><?php the_modified_date(); ?></div>
    <div class="body">
      <div>
      <?php if (has_post_thumbnail()):
        $thumb = the_post_thumbnail($size = "featured-thumbnail");
      endif; ?>  
    <?php the_content(); ?></div>
    </div>
  </div>
  
<?php if (array_intersect($allowed_roles, $user->roles)): ?>

<?php endif; ?>
