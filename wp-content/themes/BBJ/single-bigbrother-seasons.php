<?php

use function PHPSTORM_META\map;

get_header();
?>



<div class="bbj-container-inner">

<?php
//get all the fields
$seasonName = get_the_title(get_the_ID());
$image = rwmb_meta("season_picture");

$banner = rwmb_meta("season_banner_image", ["size" => "player-banner"]);
$start_date = rwmb_meta("start_date");
$end_date = rwmb_meta("end_date");
$abbrv = rwmb_meta("abbreviation");
$seasonNum = rwmb_meta("season_number");
$seasonID = get_the_id();

global $wpdb;

$player_table = $wpdb->prefix . "bbj_players";
$rel_table = $wpdb->prefix . "bbj_play_season_rel";
$week_play_table = $wpdb->prefix . "bbj_weeks_players";
$season_id = (int) $seasonID;

$query = $wpdb->prepare(
  "
	SELECT
			p.*,
			r.winner,
			r.runner_up,
			r.afp,
			r.evicted,
			r.jury,
			r.evict_date,
			r.total_hoh, 
			r.total_pov,
			r.total_nom,
			r.total_saved,
			r.total_weeks,
			r.total_veto_played,
			r.save_per,
			r.hoh_per,
			r.pov_per,
			r.comp_per,
			r.votes_nom,
			r.total_hoh_played,
			sub.hoh_count,
			sub.pov_count,
			sub.nom_count,
			sub.saved_count,
			sub.active_count,
			vf.voted_for_count,  
			sub.veto_played_count,
			sub.misc_comp_count
	FROM
			$player_table p
			INNER JOIN $rel_table r ON p.ID = r.player_id
			LEFT JOIN (
					SELECT
							player_id,
							season_id,
							SUM(hoh) AS hoh_count,
							SUM(pov) AS pov_count,
							SUM(nom) AS nom_count,
							sum(active) AS active_count,
							SUM(saved) AS saved_count,
							SUM(veto_played) AS veto_played_count,
							SUM(misc_comp) AS misc_comp_count
					FROM
							$week_play_table
					WHERE
							season_id = $season_id
					GROUP BY
							player_id,
							season_id
			) sub ON p.ID = sub.player_id AND r.season_id = sub.season_id
			LEFT JOIN ( -- New subquery for voted_for_count
					SELECT
							voted_for,
							COUNT(*) AS voted_for_count
					FROM
							$week_play_table
					WHERE
							season_id = $season_id AND voted_for IS NOT NULL
					GROUP BY
							voted_for
			) vf ON p.ID = vf.voted_for 
	WHERE
			r.season_id = %d
	ORDER BY
			r.evict_date DESC
	",
  $season_id
);

$players = $wpdb->get_results($query);
?>

<div class="bbj-container-inner">
	<div class="my-2 flex w-full flex-col rounded-md bg-white lg:flex-row overflow-hidden">

		<div class="flex-grow">
		<section id="header" class="relative">
				<?php if (is_array($banner) && isset($banner["url"])): ?>
						<img src="<?= esc_url($banner["url"]) ?>" alt="Profile Picture" class="w-full">
				<?php endif; ?>
				<div class="absolute bottom-0 left-0 bg-primary500 bg-opacity-50 w-full flex items-center justify-start pt-1 pb-2 px-2"><h1 class="text-white text-lf md:text-5xl font-osw "><?= $seasonName ?></h1></div>
		</section>


			<section id="body" class="flex flex-col md:flex-row">
				<div class="md:w-[220px] w-full flex-shrink-0  md:border-r  border-gray-400 p-2">
					<h2 class="font-osw text-xl mb-4">Houseguests</h2>

					<?php foreach ($players as $player):

       //echo "<pre>", print_r($player, 1), "</pre>";

       $prof_pic = rwmb_meta("profile_picture", ["size" => "profile-picture"], $player->ID);
       $p_firstName = $player->first_name;
       $p_lastName = $player->last_name;
       $p_nickName = $player->official_nickname;
       $p_dob = $player->date_of_birth;
       ?>
						
						<a href="<?= get_permalink($player->ID) ?>" class="">
							<?php $res = w_check_season($player); ?>
						<div class="season-pl-card border border-gray-300 rounded-md p-2 mb-2 flex hover:bg-slate-100 hover:shadow-lg">
							<div class="relative h-14 w-14">
								<img src="<?= $prof_pic["url"] ?>" class="w-full rounded-full <?php echo in_array(4, $res) || in_array(5, $res) ? "grayscale" : ""; ?>" alt="<?= $p_firstName . " " . $p_lastName . " from " . $seasonName ?>">
								<div class="absolute bottom-0 left-0 bg-white rounded-tr-2xl font-ibm w-6 h-5 flex justify-center items-center font-bold text-slate-600 text-sm pr-1"><?= new_age_calc($p_dob, $start_date) ?></div>
							</div>
							<div class="flex flex-col">
								<div class="ml-2 text-sm leading-3">
									<?php foreach ($res as $r): ?>
										<?php
          if ($r == 1) { ?>
											<i class="fa-solid fa-trophy  text-yellow-300"></i> 
										<?php }
          if ($r == 2) { ?>
											<i class="fa-solid fa-heart text-red-600"></i> 
										<?php }
          if ($r == 3) { ?>
											<i class="fas fa-award text-purple-400" aria-hidden="true"></i>
										<?php }
          ?>
									<?php endforeach; ?>
								</div>
								<div class="ml-2 flex flex-col">
									<div class="font-osw text-lg leading-4"><?= $p_firstName ?></div>
									<div class="font-osw text-lg leading-4"><?= $p_lastName ?></div>
									<div class="font-hand text-sm leading-2"><?= $p_nickName ? "\"{$p_nickName}\"" : "" ?></div>
								</div>
								
							</div>
						

						</div>
						</a>
					<?php
     endforeach; ?>

					
				</div>
				<div class="p-2 flex flex-col flex-grow">
					<div>
					</div>

					<div class="w-full">
						<h2 class="font-osw text-xl mb-4">Season Recap</h2>

						<div class="w-full flex-grow  p-2">
							<table class="w-full" id="stat-table">
								<tr  class="border-b border-slate-200">
									<th class="text-left text-xs md:text-sm p-0.5 md:p-1 text-slate-600">PLAYER</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">AGE</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">HOH</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">POV</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">NOM</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600"><span class="hidden md:block">VOTES</span><span class="block md:hidden">VTE</span></th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">DAYS</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">RESULT</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600 hidden md:table-cell">PROGRESS</th>

								</tr>

								<?php foreach ($players as $player):

								
									
									$leaveDate = getLeaveDate($player->evict_date);

          $prof_pic = rwmb_meta("profile_picture", ["size" => "profile-picture"], $player->ID); ?>
									<tr class="border-0 even:bg-gray-100">
									<td class="!p-1 text-sm !border-r !border-b border-slate-200">
    								<a href="<?= get_permalink($player->ID) ?>" class="hover:underline visited:underline">
        						<div class="flex items-center"> <!-- Add this div with flex and items-center classes -->
        	    					<img src="<?= $prof_pic["url"] ?>" class="w-5 h-5 rounded-full mr-2" alt="<?= $p_firstName . " " . $p_lastName . " from " . $seasonName ?>">
            					<?= $player->first_name . " " . $player->last_name ?>
														</div>
												</a>
										</td>

										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= new_age_calc($player->date_of_birth, $start_date) ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->hoh_count ? $player->hoh_count : 0 ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->pov_count ? $player->pov_count : 0 ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->nom_count ? $player->nom_count : 0 ?></td>
										<td class="text-sm text-center !border-b  !border-r border-slate-200"><?= $player->voted_for_count ? $player->voted_for_count : 0 ?></td>
										<td class="text-sm text-center !border-b  !border-r border-slate-200"><?= days_calc_new($start_date, $leaveDate) ?></td>
										<td class="text-left !pl-2"><?= s_results_week($player, "player-list") ?></td>
										<td class=" hidden md:table-cell">
									<?php $s_percent = season_percentage_calc($start_date, $end_date, $leaveDate); ?>
									
									<div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
									<div class="<?php if ($s_percent < 50) {
           echo "bg-green-200";
         } elseif ($s_percent >= 50 && $s_percent <= 75) {
           echo "bg-green-400";
         } elseif ($s_percent > 75 && $s_percent <= 99) {
           echo "bg-green-600";
         } elseif ($s_percent == 100) {
           echo "bg-green-800";
         } ?> h-2.5 rounded-full" style="width: <?= $s_percent ?>%"></div>

									</div>

								</td>
									</tr>
								<?php
        endforeach; ?>
							</table>
						</div>
					</div>

					<div>
					</div>

					<div class="w-full">
						<h2 class="font-osw text-xl mb-4">Advanced Stats</h2>

						<table class="w-full" id="stat-table">
								<tr  class="border-b border-slate-200">
									<th class="text-left text-xs md:text-sm p-0.5 md:p-1 text-slate-600"></th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">Veto Played</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">Saved</th>
									
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">Saved %</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">HoH %</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">PoV %</th>
									
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">Votes/Nom</th>
									<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">Comp %</th>
								</tr>

								<?php foreach ($players as $player):

          $active_weeks = $player->active_count;
          $hoh_wins = $player->hoh_count;
          $hoh_played = max($active_weeks - max($hoh_wins - 1, 0), 0);

          $prof_pic = rwmb_meta("profile_picture", ["size" => "profile-picture"], $player->ID);
          ?>

								<tr class="border-0 even:bg-gray-100">
									<td class="!p-1 text-sm !border-r !border-b border-slate-200 text-center w-6">
    								<a href="<?= get_permalink($player->ID) ?>" class="hover:underline visited:underline">
        						<div class="flex items-center justify-center"><img src="<?= $prof_pic["url"] ?>" class="w-5 h-5 rounded-full" alt="<?= $p_firstName . " " . $p_lastName . " from " . $seasonName ?>"></div>
												</a>
										</td>

										
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->veto_played_count ? $player->veto_played_count : 0 ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->saved_count ? $player->saved_count : 0 ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->total_nom > 0 ? $player->save_per : "-" ?></td>
										
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->total_hoh_played > 0 ? $player->hoh_per : "-" ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->total_veto_played > 0 ? $player->pov_per : "-" ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->nom_count > 0 ? $player->votes_nom : "-" ?></td>
										<td class="text-sm text-center !border-b !border-r border-slate-200"><?= $player->total_hoh_played > 0 || $player->total_veto_played > 0 ? $player->comp_per : "-" ?></td>




								</tr>


								<?php
        endforeach; ?>
						</table>

						<div class="text-xs">
							Glossary: 
							<ul>
								<li>Saved: How many times a player was saved from the nomination block</li>
								<li>Saved %: Average saved per nominated</li>
								<li>HoH %: HOH wins vs how many played in</li>
								<li>PoV %: POV wins vs how many played in</li>
								<li>Votes/Nom: Average number of votes the player received per nomination</li>
							</ul>
						</div>
					</div>

					<div>
					</div>

					<div class="prose-base prose-slate p-2 "> 
						<?php the_content() ?>
					</div>
				</div>
			</section>
		</div>

		<div class="w-[320px]  flex-shrink-0">
		<?php get_template_part("template-parts/sidebar-default"); ?>
		</div>
	</div>
</div>









<?php get_footer();
