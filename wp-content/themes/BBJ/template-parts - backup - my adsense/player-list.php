<?php
$seasonID = get_the_id();
global $wpdb;
$sql =
  "SELECT
      wwbj.first_name, wwbj.last_name, wwbj.profile_picture, wwbj.ID AS playerID,
      stats.`evicted_date`, seasons.`ID` AS seasonID, seasons.start_date, seasons.end_date
      FROM wp_bbj_players AS wwbj
      LEFT JOIN `wp_mb_relationships` 
          ON (wwbj.`ID` = `wp_mb_relationships`.`from`)
      LEFT JOIN wp_bbj_seasons AS seasons
          ON (seasons.`ID` = `wp_mb_relationships`.`to`)
      LEFT JOIN wp_bbj_player_season_stats AS stats 
          ON (stats.`ID` = wwbj.`ID`)
      WHERE (seasons.`ID`) = ' " .
  $seasonID .
  "'
      ORDER BY stats.`evicted_date` DESC; ";

$players = $wpdb->get_results($sql);

foreach ($players as $p):

  $seasonStart = $p->start_date;
  $seasonEnd = $p->end_date;
  $evicted_date = $p->evicted_date;
  $seasonPercent = season_percentage($seasonStart, $seasonEnd, $evicted_date);
  $imgUrl = wp_get_attachment_image_src($p->profile_picture, "tiny");
  ?>


<div class="player-table">
  <div class="pt-pic"><a href="<?php the_permalink($p->playerID); ?>"><img src="<?php echo $imgUrl[0]; ?>" alt="<?php echo $p->first_name . " " . $p->last_name; ?> Profile Picture"></a></div>
  <div class="pt-title"><a href="<?php the_permalink($p->playerID); ?>"><?php echo $p->first_name . " " . $p->last_name; ?></a></div>

  <div class="pt-bar">
    <div class="horizontal rounded">
      <div class="progress-bar horizontal">
        <div class="progress-track">
          <div class="progress-fill"><span><?php echo $seasonPercent; ?>%</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
endforeach;
wp_reset_postdata();
?>

<script>
    jQuery('.horizontal .progress-fill span').each(function(){
      var percent = jQuery(this).html();
      console.log (percent)
        console.log ('hi');
      jQuery(this).parent().css('width', percent);
    
    });
  </script>