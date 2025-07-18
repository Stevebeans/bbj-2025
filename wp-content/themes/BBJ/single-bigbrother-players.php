<?php
get_header();

global $wpdb;

// get some player variables

$playerID = get_the_ID();
$playerName = explode(" ", get_the_title($playerID));

//echo $playerID;

$first_name = $playerName[0];
$last_name = end($playerName);

$first_name = esc_html(ucwords(strtolower($first_name)));
$last_name = esc_html(ucwords(strtolower($last_name)));
$nickname = esc_html(ucwords(strtolower(rwmb_meta("official_nickname"))));
$profile_pic = rwmb_meta("profile_picture", ["size" => "prof-pic-lg"]);

$fbLink = rwmb_meta("facebook");
$igLink = rwmb_meta("instagram");
$twLink = rwmb_meta("twitter");
$ttLink = rwmb_meta("tiktok");
$dob = rwmb_meta("date_of_birth");

$city = rwmb_meta("locality");
$state = rwmb_meta("administrative_area_level_1");
$job = rwmb_meta("occupation");

$weeks_players_table = $wpdb->prefix . "bbj_weeks_players";
$play_season_rel_table = $wpdb->prefix . "bbj_play_season_rel";
$seasons = $wpdb->prefix . "bbj_seasons";
$players = $wpdb->prefix . "bbj_players";

// $player_results = $wpdb->get_results(
//   $wpdb->prepare(
//     "SELECT wp.*, w.hoh, w.pov, w.nom, w.saved, w.evicted, w.voted_for, w.vote_to_win, s.season_number AS seasonNum, s.start_date AS season_start, s.end_date AS season_end, s.full_name AS season_name
//          FROM $weeks_players_table AS w
//          LEFT JOIN $play_season_rel_table AS wp
//          ON w.player_id = wp.player_id
// 				 LEFT JOIN $seasons AS s
// 				 ON wp.season_id = s.ID
//          WHERE wp.player_id = %d
// 				 GROUP BY wp.season_id",
//     $playerID
//   )
// );

$overall_totals = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT COUNT(DISTINCT w.season_id) AS season_count, SUM(w.hoh) AS hoh, SUM(w.pov) AS pov, SUM(w.nom) AS nom, SUM(w.saved) AS saved, SUM(w.evicted) AS evicted, 
				(SELECT COUNT(voted_for) FROM $weeks_players_table WHERE voted_for = %d) AS voted_for
     FROM $weeks_players_table AS w
     WHERE w.player_id = %d",
    $playerID,
    $playerID
  )
);

//echo "<pre>", print_r($overall_totals, 1), "</pre>";

$total_season = !empty($overall_totals[0]->season_count) ? $overall_totals[0]->season_count : 0;
$total_hoh = !empty($overall_totals[0]->hoh) ? $overall_totals[0]->hoh : 0;
$total_pov = !empty($overall_totals[0]->pov) ? $overall_totals[0]->pov : 0;
$total_nom = !empty($overall_totals[0]->nom) ? $overall_totals[0]->nom : 0;

$total_saved = $overall_totals[0]->saved;
$total_evicted = $overall_totals[0]->evicted;
$total_voted_for = $overall_totals[0]->voted_for;

$season_results = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT w.season_id, s.season_number AS seasonNum, s.start_date AS season_start, s.end_date AS season_end, s.abbreviation AS seasonAb, s.full_name AS season_name, SUM(w.hoh) AS hoh_sum, SUM(w.pov) AS pov_sum, SUM(w.nom) AS nom_sum, SUM(w.saved) AS saved_sum, SUM(w.evicted) AS evicted_sum, p.date_of_birth AS dob, 
				(SELECT COUNT(voted_for) FROM $weeks_players_table WHERE voted_for = %d AND season_id = w.season_id AND voted_for = %d) AS vote_count
     FROM $weeks_players_table AS w
     LEFT JOIN $seasons AS s
     ON w.season_id = s.ID
		 LEFT JOIN $players AS p
		 ON w.player_id = p.ID
     WHERE w.player_id = %d
     GROUP BY w.season_id
     ORDER BY s.start_date ASC",
    $playerID,
    $playerID,
    $playerID
  )
);

$winner_check = $wpdb->get_results(
  $wpdb->prepare(
    "SELECT winner, runner_up, afp 
		FROM $play_season_rel_table
		WHERE player_id = %d",
    $playerID
  )
);

//echo "<pre>", print_r($season_results, 1), "</pre>";

// Calculate the unique season count
//echo "<pre>", print_r($profile_pic["url"], 1), "</pre>";
?>

<div class="bbj-container-inner">
	<div class="my-2 flex w-full flex-col rounded-md bg-white lg:flex-row overflow-hidden">
		<div class="flex-grow">
			<section id="profile-head" class="w-full flex h-fit md:h-[375px] flex-wrap md:flex-nowrap">
				<div class="w-full flex-grow relative order-2 md:order-1">
					<div class="absolute top-2 right-2">
							<?php if ($fbLink || $igLink || $twLink || $ttLink) { ?>
							<div class="text-xs text-white p">Offical Socials:</div>
							<?php } ?>
							<div class="flex">
								<?php if ($fbLink): ?>
								<div class="mr-2 "><a href="<?php echo $fbLink; ?>" target="_blank" class="text-second500 active:text-second500 hover:text-secondSoft visited:text-second500"><i class="fa-brands fa-facebook-f"></i></a></div>
							<?php endif; ?>
							<?php if ($igLink): ?>
								<div class="mr-2"><a href="<?php echo $igLink; ?>" target="_blank" class="text-second500 active:text-second500 hover:text-secondSoft visited:text-second500"><i class="fa-brands fa-instagram"></i></a></div>
							<?php endif; ?>
							
							<?php if ($twLink): ?>
								<div class="mr-2"><a href="<?php echo $twLink; ?>" target="_blank" class="text-second500 active:text-second500 hover:text-secondSoft visited:text-second500"><i class="fa-brands fa-twitter"></i></a></div>
							<?php endif; ?>
							
							<?php if ($ttLink): ?>
								<div><a href="<?php echo $ttLink; ?>" target="_blank" class="text-second500 active:text-second500 hover:text-secondSoft visited:text-second500"><i class="fa-brands fa-tiktok"></i></a></div>
							<?php endif; ?>
							</div>
							
					</div>
					<div class="h-[120px] md:h-[50%] bg-primary500 flex justify-center px-2 md:px-8 flex-col" >
						<h1 class="font-mainHead text-2xl md:text-5xl text-white"><?= $first_name ?><br /><?= $last_name ?></h1>
						<h2 class="font-hand text-xl md:text-3xl text-white"><?= $nickname ? "\"{$nickname}\"" : "" ?></h2>
					</div>
					<div class="h-[25px] md:h-[10%] bg-gradient-to-b from-primary500 to-white ">

					</div>
					<div class="h-[100px] md:h-[40%] w-full ">

						<div class="grid grid-cols-4 h-full pt-4">
							<div class="text-center">
							<div class="text-3xl md:text-5xl font-bold text-second500"><?= $total_season ?></div>
								<div class="text-sm md:text-xl">Season<?= $total_season > 1 ? "s" : "" ?>
								</div>
							</div>
							<div class="text-center">
								<div class="text-3xl md:text-5xl font-bold text-second500"><?= $total_hoh ?></div>
								<div class="text-sm md:text-xl">Head of Household</div>
							</div>
							<div class="text-center">
							<div class="text-3xl md:text-5xl font-bold text-second500"><?= $total_pov ?></div>
								<div class="text-sm md:text-xl">Power of Veto</div>
							</div>
							<div class="text-center">
							<div class="text-3xl md:text-5xl font-bold text-second500"><?= $total_nom ?></div>
								<div class="text-sm md:text-xl">Nominated</div>
							</div>
						</div>

					</div>
				</div>
				<div class="w-full md:w-[375px] flex-shrink-0 order-1 md:order-2 relative">
					<div class="absolute top-2 right-2 flex flex-wrap justify-end">
					<?php $res = s_results_overall($winner_check); ?>
						<?php foreach ($res as $value): ?>
							<?php if ($value == 1): ?>
								<div class="tracking-wider text-primary500 bg-yellow-300 px-4 py-1 text-sm rounded mx-1 font-semibold my-1" title="">
									<i class="fa-solid fa-trophy"></i> Winner
								</div>
							<?php elseif ($value == 2): ?>
								<div class="tracking-wider text-primary500 bg-violet-300 px-4 py-1 text-sm rounded mx-1 font-semibold my-1" title="">
									<i class="fa-solid fa-heart"></i> America's Favorite
								</div>
							<?php elseif ($value == 3): ?>
								<div class="tracking-wider text-primary500 bg-slate-300 px-4 py-1 text-sm rounded mx-1 font-semibold my-1" title="">
									<i class="fas fa-award" aria-hidden="true"></i> Runner Up
								</div>
							<?php endif; ?>
						<?php endforeach; ?>

						<?php if (is_user_logged_in()): ?>
							<?php if (is_user_logged_in() && current_user_can("edit_posts")): ?>
								<a href="<?php echo get_edit_post_link(); ?>" class="text-white active:text-white visited:text-white hover:text-secondSoft"><i class="fa-solid fa-edit"></i></a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<img class="w-[375px] h-[375px] md:rounded-bl-3xl  " src="<?= $profile_pic["url"] ?>" alt="Big Brother <?= $first_name . " " . $last_name . " profile picture" ?>">
				</div>
			</section>

			
			<section id="spacer2" class="w-full flex justify-center items-center my-4">
				<div class="w-[90%] h-[2px] bg-slate-400"></div>
			</section>

			<section id="player-bio" class="w-full flex justify-center items-center my-4 flex-wrap md:flex-nowrap">
				<div class="w-full md:w-[300px] grid grid-cols-2 flex-shrink p-2 bg-sky-100 rounded-lg ml-1 mr-1 md:ml-2 md:mr-4 text-sm">
					
					<div>City:</div><div><?= $city ?> </div>
					<div>State:</div><div><?= $state ?></div>
					<div>Occupation:</div><div><?= $job ?></div>
					<div>Current Age:</div><div>
					<?php if (!empty($season_results[0]->dob)): ?>
						<?= current_age_calc($season_results[0]->dob) ?>
					<?php endif; ?>

					</div>
					
					
				</div>

				<?php // Beginning for sum

$total_days = 0; ?>

				<div class="w-full flex-grow  p-2">
					<table class="w-full" id="stat-table">
						<tr  class="border-b border-slate-200">
							<th class="text-sm p-1 text-slate-600"><span class="hidden md:block">SEASON</span></th>
							<th class="text-sm p-1 text-slate-600 hidden md:block">AGE</th>
							<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">HOH</th>
							<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">POV</th>
							<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">NOM</th>
							<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600"><span class="hidden md:block">VOTES</span><span class="block md:hidden">VTE</span></th>
							<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">DAYS</th>
							<th class="text-xs md:text-sm p-0.5 md:p-1 text-slate-600">RESULT</th>
							<th class="text-sm p-1 text-slate-600 hidden md:block">PROGRESS</th>

						</tr>
						<?php foreach ($season_results as $season): ?>

							<?php
       $p_week = $wpdb->get_results(
         $wpdb->prepare(
           "SELECT * FROM $play_season_rel_table 
										WHERE season_id = %d AND player_id = %d",
           $season->season_id,
           $playerID
         )
       );

       if (isset($p_week[0])) {
         $evict_date = $p_week[0]->evict_date;
				 $leaveDate = getLeaveDate($evict_date);
       } else {
         // handle the case where $p_week is empty or doesn't have the desired key
       }
       ?>
							<tr class="border-0">
								<td class="!border-r border-slate-200"><a href="<?= get_permalink($season->season_id) ?>" class="hover:underline visited:underline"><span class="hidden md:block"><?= $season->season_name ?></span><span class="block md:hidden"><?= $season->seasonAb ?></span></a></td>
								<td class="text-center !border-r border-slate-200  hidden md:block"><?= new_age_calc($season->dob, $season->season_start) ?></td>
								<td class="text-center  !border-r border-slate-200"><?= $season->hoh_sum ?></td>
								<td class="text-center  !border-r border-slate-200"><?= $season->pov_sum ?></td>
								<td class="text-center  !border-r border-slate-200"><?= $season->nom_sum ?></td>
								<td class="text-center  !border-r border-slate-200"><?= $season->vote_count ?></td>
								<td class="text-center  !border-r border-slate-200">
									
									<?php
        $days = days_calc_new($season->season_start, $leaveDate);
        $total_days += $days;
        echo $days;
        ?></td>
								<td class="text-left !pl-2"><?= s_results_week($p_week, "player-page") ?></td>
								<td>
									<?php $s_percent = season_percentage_calc($season->season_start, $season->season_end, $leaveDate); ?>
									
									<div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
									<div class=" hidden md:block <?php if ($s_percent < 50) {
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
						<?php endforeach; ?>
							<tr class="border-t border-slate-200">
								<td class="font-bold text-sm">Totals:</td>
								<td class=""></td>
								<td class="font-bold text-second500 text-center"><?= $total_hoh ?></td>
								<td class="font-bold text-second500 text-center"><?= $total_pov ?></td>
								<td class="font-bold text-second500 text-center"><?= $total_nom ?></td>
								<td class="font-bold text-second500 text-center"><?= $total_voted_for ?></td>
								<td class="font-bold text-second500 text-center"><?= $total_days ?></td>
								
							</tr>
					</table>
				</div>
			</section>

			<section id="bio-2" class="p-2 prose w-full">
				<?php the_content(); ?>
			</section>
		</div>

		<div class="w-full md:w-[320px]  flex-shrink-0">
		<?php get_template_part("template-parts/sidebar-default"); ?>
		</div>
	</div>
</div>








<?php get_footer();
