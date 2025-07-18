<?php
get_header(); ?>


<?php
$playerID = get_the_id();

$playerTableInfo = ["storage_type" => "custom_table", "table" => "wp_bbj_players"];
$playerSeasonInfo = ["storage_type" => "custom_table", "table" => "wp_bbj_player_results"];

$player_banner = rwmb_meta("player_banner", $playerTableInfo);
$player_profile = rwmb_meta("profile_picture", $playerTableInfo);
$fbLink = rwmb_meta("facebook");
$igLink = rwmb_meta("instagram");
$twLink = rwmb_meta("twitter");
$ttLink = rwmb_meta("tiktok");
$rightSide = rwmb_meta("right_side", $playerTableInfo);

$evicted_date = rwmb_meta("evicted_date");
?>
<?php if ($player_banner):
  $banner = $player_banner["sizes"]["player-banner"]["url"]; ?>
<div class="player-header" style="background-image: url(<?php echo esc_url($banner); ?>)"></div>
<?php
endif; ?>

 <div class="new-body-container">


	<div class="player-profile">

				<div class="profile-left">
					<div class="player-profile-image">
					<?php
// $main_photo = get_field( 'profile_picture' );
?>
					<?php if ($player_profile):

       $altText = $player_profile["alt"];
       $profilePicture = $player_profile["sizes"]["profile-picture"]["url"];
       ?>
						<img src="<?php echo esc_url($profilePicture); ?>" alt="<?php echo esc_attr($altText); ?>" />
					<?php
     endif; ?>
					</div>

					<h1 class="player-name-mobile"><?php the_title(); ?></h1>
					<h3>Season Progress:</h3>

				

					<?php
     $connected = new WP_Query([
       "relationship" => [
         "id" => "player-to-season",
         "from" => get_the_ID(), // You can pass object ID or full object
       ],
       "nopaging" => true,
     ]);
     while ($connected->have_posts()):

       $connected->the_post();
       $start_date = rwmb_meta("start_date");
       $end_date = rwmb_meta("end_date");
       $season_abv = rwmb_meta("abbreviation");
       $seasonPercent = season_percentage($start_date, $end_date, $evicted_date);
       ?>	
						<div class="season-headline"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></div>
						<div class="horizontal rounded">
							<div class="progress-bar horizontal">
								<div class="progress-season"><?php days_calc($start_date, $evicted_date); ?> Days</div>
								<div class="progress-track">
									<div class="progress-fill">
										<span><?php echo $seasonPercent; ?>%</span>
									</div>
								</div>
							</div>
						</div>

						<?php
     endwhile;
     wp_reset_postdata();
     ?>

						<script>
							jQuery('.horizontal .progress-fill span').each(function(){
								var percent = jQuery(this).html();
								console.log (percent)
								jQuery(this).parent().css('width', percent);
							});
						</script>
					<div class="seasons">



          
					</div>
					<h3>Info:</h3>

					<?php echo "<pre>", print_r($start_date, 1), "</pre>"; ?>

					<div class="player-info">


						<div class="player-info-fa"><i class="fa-solid fa-user"></i></div> 
						<div class="player-info-cat">Gender:</div>
						<div class="player-info-details"><?php echo ucfirst(rwmb_meta("player_gender")); ?></div>
						
						<div class="player-info-fa"><i class="fa-regular fa-address-card"></i></div> 						
						<div class="player-info-cat">Age:</div>
						<div class="player-info-details"><?php echo show_age(rwmb_meta("date_of_birth"), $start_date); ?></div>						
						
						<div class="player-info-fa"><i class="fa-regular fa-address-card"></i></div> 						
						<div class="player-info-cat">Age: (now)</div>
						<div class="player-info-details"><?php echo current_age(rwmb_meta("date_of_birth")); ?></div>

										
						
						<div class="player-info-fa"><i class="fa-solid fa-house-chimney"></i></div> 						
						<div class="player-info-cat">City</div>
						<div class="player-info-details"><?php echo rwmb_meta("locality"); ?></div>

						
						<div class="player-info-fa"><i class="fa-solid fa-location-dot"></i></div> 						
						<div class="player-info-cat">State</div>
						<div class="player-info-details"><?php echo rwmb_meta("administrative_area_level_1"); ?></div>

						
						<div class="player-info-fa"><i class="fa-solid fa-briefcase"></i></div> 						
						<div class="player-info-cat">Occupation</div>
						<div class="player-info-details"><?php echo rwmb_meta("occupation"); ?></div>

										
					</div>

        
          <h3>Offical Social Media</h3>
					<div class="player-socials">
						         
          <?php if ($fbLink): ?>
						<div><a href="<?php echo $fbLink; ?>" target="_blank"><i class="fa-brands fa-facebook-f"></i></a></div>
          <?php endif; ?>
          <?php if ($igLink): ?>
						<div><a href="<?php echo $igLink; ?>" target="_blank"><i class="fa-brands fa-instagram"></i></a></div>
          <?php endif; ?>
					
          <?php if ($twLink): ?>
						<div><a href="<?php echo $twLink; ?>" target="_blank"><i class="fa-brands fa-twitter"></i></a></div>
          <?php endif; ?>
					
          <?php if ($ttLink): ?>
						<div><a href="<?php echo $ttLink; ?>" target="_blank"><i class="fa-brands fa-tiktok"></i></a></div>
          <?php endif; ?>
					</div>

					<div class="sideBlock">
						Ad Block 250 wide
					</div>

				</div>
				<div class="profile-right">
				
          <div class="player-profile-content">

						<h1><?php the_title(); ?> <span class="season-abv">(<?php echo $season_abv; ?>)</span></h1>
						

						<a href="/bigbrother-players">More Players</a>
					<?php the_content(); ?>

					</div>
				</div>
	</div>


   <div class="mainBody">

		 <?php echo "BEGIN NEWS"; ?>






  </div>
</div>


<?php get_footer();
