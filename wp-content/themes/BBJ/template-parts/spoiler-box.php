
<?php
if (is_home() || is_single() || is_archive()):
  $seasonID = rwmb_meta("current_season", ["object_type" => "setting"], "bbj_settings");
else:
  $seasonID = get_the_id();
endif;

$players = $wpdb->get_results(
  'SELECT sn.*, s.full_name FROM wp_bbj_player_season_new AS sn
LEFT JOIN wp_bbj_seasons s ON s.ID = sn.ID
  WHERE sn.ID = "' .
    $seasonID .
    '"'
);
$playerList = unserialize($players[0]->player_list2);
?>

<div class="spoiler-box">
<?php foreach ($playerList as $player):

  $addInfo = $wpdb->get_results('SELECT profile_picture, first_name, last_name FROM wp_bbj_players WHERE ID = "' . $player["player_id"] . '"');
  $imgUrl = wp_get_attachment_image_src($addInfo[0]->profile_picture, "profile-picture");

  // Image Link
  // echo $imgUrl[0]
  ?>


            
<?php if (isset($player["current_hoh"])): ?>
    <div class="sb-hoh">
      <div class="sb-hoh-ban ban-style"><div class="ban-text">HOH</div></div>
      <a href="<?php the_permalink($player["player_id"]); ?>"><?php echo get_the_post_thumbnail($player["player_id"], "profile-picture"); ?></a>
    </div>
<?php endif; ?>


<?php if (isset($player["current_pov"])): ?>   
    <div class="sb-pov">
      
    <div class="sb-pov-ban ban-style"><div class="ban-text">POV</div></div>
      <a href="<?php the_permalink($player["player_id"]); ?>"><?php echo get_the_post_thumbnail($player["player_id"], "profile-picture"); ?></a>
    </div>
<?php endif; ?> 


<?php if (isset($player["current_nom"])): ?>
    <div class="sb-nom">
      
    <div class="sb-nom-ban ban-style-nom"><div class="ban-text-sm">NOM</div></div>
    <a href="<?php the_permalink($player["player_id"]); ?>"><?php echo get_the_post_thumbnail($player["player_id"], "profile-picture"); ?></a></div>
<?php endif; ?>   


<?php if (isset($player["current_nom2"])): ?>
    <div class="sb-nom2">
    
    <div class="sb-nom-ban ban-style-nom"><div class="ban-text-sm">NOM</div></div>  
    <a href="<?php the_permalink($player["player_id"]); ?>"><?php echo get_the_post_thumbnail($player["player_id"], "profile-picture"); ?></a></div>
<?php endif;
endforeach; ?>
</div>
