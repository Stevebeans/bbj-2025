<?php
use function PHPSTORM_META\map;

get_header();
?>



<div class="bbj-container-inner">

<?php
//get all the fields
$playerName = get_the_title(get_the_ID());
$image = rwmb_meta("season_picture");

$banner = rwmb_meta("season_banner_image", ["size" => "player-banner"]);
$start_date = rwmb_meta("start_date");
$end_date = rwmb_meta("end_date");
$abbrv = rwmb_meta("abbreviation");
$seasonNum = rwmb_meta("season_number");
$seasonID = get_the_id();
?>


  <div class="bbj-inner-content-container">
    <div class="bbj-content-container">
      
    
      <div class="flex flex-col md:flex-row">



				
				
				<div class="grow">




				
							<div class="p-2">
								<div class="text-gray-600 font-osw ">All Big Brother Seasons:</div>

									<div class="relative overflow-x-auto shadow-md sm:rounded-lg mt-6">
											<table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
													<thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
															<tr>
																	<th scope="col" class="px-6 py-3">
																			Season
																	</th>
																	<th scope="col" class="px-6 py-3 text-center">
																			Start
																	</th>
																	<th scope="col" class="px-6 py-3 text-center">
																			Finish
																	</th>
																	<th scope="col" class="px-6 py-3 text-center">
																			Days
																	</th>
																	<th scope="col" class="px-6 py-3 text-center">
																			Winner
																	</th>
																	<th scope="col" class="px-6 py-3 text-center">
																			AFP
																	</th>
															</tr>
													</thead>

													
													<tbody>								

								<?php
        global $wpdb;

        $query = "SELECT seasons.ID, seasons.start_date, seasons.end_date, seasons.season_number, seasons.full_name, seasons.abbreviation, player_season.player_list2
FROM {$wpdb->prefix}bbj_seasons AS seasons
LEFT JOIN {$wpdb->prefix}bbj_player_season_new AS player_season
ON seasons.ID = player_season.ID
ORDER BY seasons.start_date";

        $results = $wpdb->get_results($query);

        foreach ($results as $row):
          $player_list = unserialize($row->player_list2); ?>
				<tr class="bg-white border-b dark:bg-gray-900 dark:border-gray-700">
					<td class="px-6 py-4"><a href="<?= get_permalink($row->ID) ?>"><?= esc_html($row->full_name) ?></a></td>
					<td class="px-6 py-4 text-center"><?= date("m/d/y", strtotime($row->start_date)) ?></td>
					
					<td class="px-6 py-4 text-center"><?= date("m/d/y", strtotime($row->end_date)) ?></td>
					<td class="px-6 py-4 text-center"><?= days_calc($row->start_date, $row->end_date) ?></td>
					
					<?php if (is_array($player_list)): ?>
						<td class="px-6 py-4 text-center">
						<?php foreach ($player_list as $player):
        $player_query = $wpdb->prepare(
          "SELECT id, first_name, last_name, official_nickname
FROM {$wpdb->prefix}bbj_players
WHERE ID = %d",
          $player["player_id"]
        );

        $player_details = $wpdb->get_row($player_query);

        if (isset($player["current_house_status"]) && $player["current_house_status"][0] === "winner") {
          echo "<a href='" . get_permalink($player_details->id) . "'>" . $player_details->first_name . " " . $player_details->last_name . "</a>";
        } else {
          echo "";
        }
      endforeach; ?>
				</td>
       

				<td class="px-6 py-4 text-center">
						<?php foreach ($player_list as $player):
        $player_query = $wpdb->prepare(
          "SELECT id, first_name, last_name, official_nickname
FROM {$wpdb->prefix}bbj_players
WHERE ID = %d",
          $player["player_id"]
        );

        $player_details = $wpdb->get_row($player_query);

        if (isset($player["current_house_status"]) && $player["current_house_status"][0] === "afp") {
          echo "<a href='" . get_permalink($player_details->id) . "'>" . $player_details->first_name . " " . $player_details->last_name . "</a>";
        } else {
          echo "";
        }
      endforeach; ?>
				</td>

			 <?php endif;
        endforeach;
        ?>

						
				</tr>

				<?php  ?>
        </tbody>




    </table>
</div>



								<div><?php the_content(); ?></div>
							</div>
				</div>
				<div class=" shrink-0">
						<?php get_template_part("template-parts/sidebar-default"); ?>
				</div>
			</div>

    </div>

  </div>




</div>










<?php get_footer();
