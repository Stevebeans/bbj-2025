<?php
get_header();

global $wpdb;

// get some player variables

$playerID = get_the_ID();

// get player info 
$player_info = bbj_v2_get_player($playerID);
$all_seasons = bbj_v2_get_seasons_by_player($playerID);




$first_name = $player_info['first_name'];
$last_name = $player_info['last_name'];
$nickname = $player_info['official_nickname'];
$picture_id = $player_info['profile_picture'];
$profile_picture = wp_get_attachment_image_url( $picture_id, 'bbj_v2_profile_image' );

// NEW: awards + totals + per-season rows (single pass)
$winner = $runner_up = $afp = false;

$total_hoh = $total_pov = $total_nom = $total_votes = $total_days = 0;
$total_house_days = 0;
$season_rows = [];

foreach ($all_seasons as $s) {
  if (!is_array($s)) continue;

  $sid   = (int)($s['season_number'] ?? 0);
  $name  = !empty($s['abbreviation']) ? $s['abbreviation'] : (!empty($s['full_name']) ? $s['full_name'] : "Season $sid");
	$season_name = $s['full_name'] ?? '';

  $hoh   = (int)($s['bbj_total_hoh'] ?? 0);
  $pov   = (int)($s['bbj_total_pov'] ?? 0);
  $nom   = (int)($s['bbj_total_nom'] ?? 0);
  $votes = (int)($s['bbj_votes_received'] ?? 0);

	$evictedRaw = trim((string)($s['bbj_evicted_date'] ?? ''));
	$evicted    = ($evictedRaw && $evictedRaw !== '0000-00-00') ? new DateTime($evictedRaw) : null;
  $start = !empty($s['start_date']) ? new DateTime($s['start_date']) : null;
  $end   = !empty($s['end_date'])   ? new DateTime($s['end_date'])   : null;
  $days  = ($start && $end) ? $end->diff($start)->days + 1 : null;

	// get days in house by $start and bbj_evicted_date difference
	$evicted    = ($evictedRaw && $evictedRaw !== '0000-00-00') ? new DateTime($evictedRaw) : null;

	$age_during_season = null;
	if (!empty($player_info['date_of_birth']) && $start) {
		$dob = new DateTime($player_info['date_of_birth']);
		$age_during_season = $dob->diff($start)->y;
	}
	
	$exit = $evicted ?: $end;	
	$days_in_house = ($start && $exit) ? $exit->diff($start)->days + 1 : null;
	
	$progress_pct = ($days && $days_in_house)
  ? max(0, min(100, (int) round(($days_in_house / $days) * 100)))
  : null;

	
	$season_permalink = get_permalink($s['bbj_season'] ?? 0);


  // result label + flip awards
  $result = '—';
  if (!empty($s['season_winner']) && (int)$s['season_winner'] === (int)$playerID) { 
		$result = 'Winner';    $winner = true; 
	}
  elseif (!empty($s['runner_up']) && (int)$s['runner_up'] === (int)$playerID)      { 
		$result = 'Runner Up'; $runner_up = true; 
	}
  elseif (!empty($s['afp']) && (int)$s['afp'] === (int)$playerID)                  { 
		$result = 'AFP';       $afp = true; 
	}
	// jury and evicted
	elseif (!empty($s['current_jury']) && (int)$s['current_jury'] === 1) {
		$result = 'Jury';      $jury = true;
	}
	elseif (!empty($s['current_evicted']) && (int)$s['current_evicted'] === 1) {
		$result = 'Evicted';   $evicted = true;
	}

	

  $season_rows[] = [
    'season' 					=> $name,
		'season_name' 		=> $season_name,
		'season_link' 		=> $season_permalink,
    'hoh'    					=> $hoh,
    'pov'    					=> $pov,
    'nom'    					=> $nom,
    'votes'  					=> $votes,
    'total_days'			=> $days,
		'days_in_house'	  => $days_in_house,
		'progress_pct'		=> $progress_pct,
		'age_during' 			=> $age_during_season,
    'result' 					=> $result,
  ];

  // grand totals
  $total_hoh   += $hoh;
  $total_pov   += $pov;
  $total_nom   += $nom;
  $total_votes += $votes;
  $total_days  += $days ?: 0;
	$total_house_days += $days_in_house ?: 0;
}
$total_seasons = count($season_rows);

$fbLink = $player_info['facebook'] ?? '';
$igLink = $player_info['instagram'] ?? '';
$twLink = $player_info['twitter'] ?? '';
$ttLink = $player_info['tiktok'] ?? '';
$dob = $player_info['date_of_birth'] ?? '';
$city = $player_info['locality'] ?? '';
$state = $player_info['administrative_area_level_1'] ?? '';
$dob = $player_info['date_of_birth'] ?? '';
$job = $player_info['occupation'] ?? '';

?>

<main class="v2-primary-container">
	<div class="flex w-full flex-col mb-4 lg:flex-row dark:text-gray-200">
		<section id="main-left" class="flex-grow space-y-4">

			<div class="v2-primary-container-inner grid grid-cols-1 md:grid-cols-[1.5fr_1fr] gap-4 mb-4 text-center text-sm md:text-base p-2 rounded-lg">
				<div class="border bg-primary500 p-2 flex flex-col justify-between">					
					<div class="mb-2">
						<h1 class="font-mainHead text-2xl md:text-4xl text-white"><?= $first_name ?> <?= $last_name ?></h1>
						<h2 class="font-hand text-xl md:text-3xl text-white"><?= $nickname ? "\"{$nickname}\"" : "" ?></h2>
					</div>
					<div class='flex justify-around'>
						<?php if ($winner): ?>
							<div class="tracking-wider text-primary500 bg-yellow-300 px-4 py-1 text-sm rounded mx-1 font-semibold my-1">
								<i class="fa-solid fa-trophy"></i> Winner
							</div>
						<?php endif; ?>

						<?php if ($afp): ?>
							<div class="tracking-wider text-primary500 bg-violet-300 px-4 py-1 text-sm rounded mx-1 font-semibold my-1">
								<i class="fa-solid fa-heart"></i> America’s Favorite
							</div>
						<?php endif; ?>

						<?php if ($runner_up): ?>
							<div class="tracking-wider text-primary500 bg-slate-300 px-4 py-1 text-sm rounded mx-1 font-semibold my-1">
								<i class="fa-solid fa-award"></i> Runner Up
							</div>
						<?php endif; ?>
					</div>

					
					<div class="grow grid grid-cols-2 lg:grid-cols-4 gap-2 items-center">
						<div class="v2-player-stat-bubble">
							<div class="v2-player-stat"><?php echo $total_hoh; ?></div>
							<div class="v2-player-stat-label">Total HoH</div>
						</div>
						<div class="v2-player-stat-bubble">
							<div class="v2-player-stat"><?php echo $total_pov; ?></div>
							<div class="v2-player-stat-label">Total PoV</div>
						</div>
						<div class="v2-player-stat-bubble">
							<div class="v2-player-stat"><?php echo $total_nom; ?></div>
							<div class="v2-player-stat-label">Nominated</div>
						</div>
						<div class="v2-player-stat-bubble">
							<div class="v2-player-stat"><?php echo $total_votes; ?></div>
							<div class="v2-player-stat-label">Votes</div>
						</div>
					</div>

					<div class='flex justify-end text-xs'>
						<?php
							$social_cfg = [
								'facebook'  => ['url' => $fbLink ?? '', 'icon' => 'fa-brands fa-facebook-f', 'label' => 'Facebook'],
								'instagram' => ['url' => $igLink ?? '', 'icon' => 'fa-brands fa-instagram',  'label' => 'Instagram'],
								'twitter'   => ['url' => $twLink ?? '', 'icon' => 'fa-brands fa-x-twitter',    'label' => 'Twitter'],  
								'tiktok'    => ['url' => $ttLink ?? '', 'icon' => 'fa-brands fa-tiktok',     'label' => 'TikTok'],
							];

							// keep only non-empty links
							$socials = array_filter($social_cfg, static fn($s) => is_string($s['url']) && trim($s['url']) !== '');
							?>

						<?php if (!empty($socials)): ?>
							<nav class="mt-2 flex items-center justify-end gap-2 text-xs" aria-label="Player social links">
								<span class="text-white/85 mr-1">Socials:</span>
								<ul class="flex items-center gap-1">
									<?php foreach ($socials as $net => $s): ?>
										<li>
											<a
												href="<?= esc_url($s['url']) ?>"
												target="_blank"
												rel="noopener nofollow"
												aria-label="<?= esc_attr($s['label']) ?>"
												class="group inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/25 text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60"
												title="<?= esc_attr($s['label']) ?>"
											>
												<i class="<?= esc_attr($s['icon']) ?> text-[15px]"></i>
												<span class="sr-only"><?= esc_html($s['label']) ?></span>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							</nav>
						<?php else: ?>
							<div class="flex justify-end text-xs text-white/80">None Listed Yet</div>
						<?php endif; ?>

					</div>
				</div>
				<div class="flex justify-center items-center">
					<img class="w-full h-full rounded-xl overflow-hidden border-2 border-gray-500  " src="<?php echo $profile_picture ?>" alt="Big Brother <?= $first_name . " " . $last_name . " profile picture" ?>">
				</div>
			</div>

			<!-- player info -->
			<section id="player-info" class="grid grid-cols-2 md:grid-cols-4 p-2 md:p-0 gap-2 md:gap-4 mb-4 text-center text-sm md:text-base rounded-lg">
				<div class="v2-primary-container-inner">
					<div class="v2-player-stat-label">City</div>
					<div class="v2-player-stat-sm"><?= $city ?: "N/A" ?></div>
				</div>
				<div class="v2-primary-container-inner">
					<div class="v2-player-stat-label">State</div>
					<div class="v2-player-stat-sm"><?= $state ?: "N/A" ?></div>
				</div>
				<div class="v2-primary-container-inner">
					<div class="v2-player-stat-label">Occupation</div>
					<div class="v2-player-stat-sm"><?= $job ?: "N/A" ?></div>
				</div>
				<div class="v2-primary-container-inner">
					<div class="v2-player-stat-label">Current Age</div>
					<div class="v2-player-stat-sm">
						<?php if (!empty($dob)): ?>
							<?= current_age_calc($dob) ?>
						<?php else: ?>
							N/A
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section id="season-breakdown" class="v2-primary-container-inner mt-4 overflow-hidden m-2 md:m-0">
				<div class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-gray-300">
					Season Breakdown
				</div>

				<div class="overflow-x-auto">
					<table class="min-w-full table-auto text-sm">
						<thead class="bg-slate-50 text-slate-600 dark:bg-gray-800 dark:text-gray-300 text-xs uppercase">
							<tr>
								<th class="px-3 py-2 text-left">Season</th>								
								<th class="px-3 py-2 text-center">Age</th>
								<th class="px-3 py-2 text-center">HOH</th>
								<th class="px-3 py-2 text-center">POV</th>
								<th class="px-3 py-2 text-center">NOM</th>
								<th class="px-3 py-2 text-center">Votes</th>
								<th class="px-3 py-2 text-center">Days</th>
								<th class="px-3 py-2 text-center">Progress</th>
								<th class="px-3 py-2 text-center">Result</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($season_rows as $r): ?>
								
								<tr class="border-t border-slate-200 dark:border-gray-700">
									<td class="px-3 py-2 font-medium text-left">
										<a href="<?= esc_url($r['season_link']) ?>" class="hover:underline text-thirdColor visited:text-thirdColor"><?= esc_html($r['season_name']) ?></a>
									</td>
									<td class="px-3 py-2 text-gray-600 text-center tabular-nums"><?= $r['age_during'] ?? '—' ?></td>
									<td class="px-3 py-2 text-gray-600 text-center tabular-nums"><?= $r['hoh'] ?></td>
									<td class="px-3 py-2 text-gray-600 text-center tabular-nums"><?= $r['pov'] ?></td>
									<td class="px-3 py-2 text-gray-600 text-center tabular-nums"><?= $r['nom'] ?></td>
									<td class="px-3 py-2 text-gray-600 text-center tabular-nums"><?= $r['votes'] ?></td>
									<td class="px-3 py-2 text-gray-600 text-center tabular-nums"><?= $r['days_in_house'] ?: '—' ?></td>
									
									<td class="px-3 py-2">
										<?php if ($r['progress_pct'] !== null): ?>
											<div class="flex items-center justify-end gap-2">
												<!-- track -->
												<div
													class="relative h-2.5 w-28 overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-700"
													role="progressbar"
													aria-label="Season progress"
													aria-valuemin="0"
													aria-valuemax="100"
													aria-valuenow="<?= $r['progress_pct'] ?>"
												>
													<!-- fill -->
													<div
														class="h-full rounded-full bg-gradient-to-r from-slate-300 to-primary500"
														style="width: <?= $r['progress_pct'] ?>%;"
													></div>
												</div>
												<!-- % -->
												<span class="w-10 text-right text-xs tabular-nums text-slate-600 dark:text-gray-300">
													<?= $r['progress_pct'] ?>%
												</span>
											</div>
										<?php else: ?>
											—
										<?php endif; ?>
									</td>
									<td class="px-3 py-2 text-center"><?= $r['result'] ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot class="bg-slate-50 dark:bg-gray-800 font-semibold">
							<tr>
								<td class="px-3 py-2 text-left">Totals (<?= $total_seasons ?> seasons)</td>
								<td class="px-3 py-2 text-center"><?= $total_hoh ?></td>
								<td class="px-3 py-2 text-center"><?= $total_pov ?></td>
								<td class="px-3 py-2 text-center"><?= $total_nom ?></td>
								<td class="px-3 py-2 text-center"><?= $total_votes ?></td>
								<td class="px-3 py-2 text-center"><?= $total_house_days ?: '—' ?></td>
								<td class="px-3 py-2"></td>
								<td class="px-3 py-2"></td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>


		
			
			<section id="player-profile" class="v2-primary-container-inner p-2 m-2 md:m-0">
				<article role="presentation" style="display: contents;" id="bbj-article-body"
							class="prose-base prose-slate 
										max-w-none dark:prose-invert
										break-words selection:bg-yellow-200 selection:text-black
										prose-headings:font-mainHead prose-h2:scroll-mt-24 prose-h3:scroll-mt-24
										prose-a:no-underline hover:prose-a:underline prose-a:text-primary500 hover:prose-a:text-primary700 visited:prose-a:text-primary600
										prose-img:rounded-lg prose-img:mx-auto
										prose-figcaption:text-sm prose-figcaption:text-slate-500
										list-disc list-inside prose-li:marker:text-primary500
										prose-code:bg-slate-100 dark:prose-code:bg-slate-800 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:before:content-[''] prose-code:after:content-['']
										prose-pre:rounded-md prose-pre:p-4
										prose-table:w-full prose-th:text-left prose-td:p-2"
							itemprop="articleBody">
					<?php the_content(); ?>
				</article>
			</section>
		</section>

	  
    <?php get_template_part('template-parts/sidebar-default'); ?>
	</div>
</main>








<?php get_footer();
