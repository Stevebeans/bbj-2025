<?php
get_header();

$season_post_id = (int) get_the_ID();
$season_info    = bbj_v2_get_season_by_id($season_post_id);



$season_pk      = isset($season_info['id']) ? (int)$season_info['id'] : $season_post_id;

$season_players = bbj_v2_get_season_players($season_pk, 'profile-picture');



$season_name = $season_info ? $season_info['full_name'] : 'Unknown Season';
$season_number = $season_info ? $season_info['season_number'] : 'N/A';
$start_date = $season_info ? new DateTime($season_info['start_date']) : new DateTime();
$end_date   = $season_info ? new DateTime($season_info['end_date'])   : new DateTime();
$current_date = new DateTime();
// added 1 to include the end date in the count
$season_length = $start_date->diff($end_date)->days + 1;

$start_formatted = $start_date->format('M j, Y');
$end_formatted = $end_date->format('M j, Y');

$season_active = false;

// if current date is between start and end date, season is active
if ($current_date >= $start_date && $current_date <= $end_date) {
	$season_active = true;
}

// get percentage of season complete if active or show 100% 
if ($season_active) {
	$days_passed = $start_date->diff($current_date)->days + 1; // added 1 to include the current day
	
	$season_progress = ($days_passed / $season_length) * 100;
	$season_progress = round($season_progress, 2); // round to 2 decimal places
} else {
	$season_progress = 100;
}


// Derived counts (use your current_* flags)
$hg_total      = is_array($season_players) ? count($season_players) : 0;
$jury_count    = 0;
$evicted_count = 0;

foreach ($season_players as $sp) {
	
  if (!is_array($sp)) continue;
  if (!empty($sp['current_jury']))    $jury_count++;
  if (!empty($sp['current_evicted'])) $evicted_count++;
}
$active_count = max(0, $hg_total - $evicted_count);

// day count for header chip
if ($season_active) {
  $days_passed = $start_date->diff($current_date)->days + 1; // inclusive of today
} else {
  $days_passed = $current_date < $start_date ? 0 : $season_length;
}


// -------- Live Now state (uses current_* flags you already store)
$live_hoh = $live_pov = null; $live_block = []; $live_jury = 0; $live_evicted = 0;

foreach ((array)$season_players as $p) {
  if (!is_array($p)) continue;
  if (!empty($p['current_hoh']) && !$live_hoh) $live_hoh = $p;
  if (!empty($p['current_pov']) && !$live_pov) $live_pov = $p;
  if (!empty($p['current_nom'])) $live_block[] = $p;
  if (!empty($p['current_jury'])) $live_jury++;
  if (!empty($p['current_evicted'])) $live_evicted++;
}

// -------- Leaderboards (top 5)
$lb_fields = [
  'hoh'   => ['key' => 'bbj_total_hoh',        'label' => 'Most HOH Wins'],
  'pov'   => ['key' => 'bbj_total_pov',        'label' => 'Most POV Wins'],
  'nom'   => ['key' => 'bbj_total_nom',        'label' => 'Most Nominations'],
  'votes' => ['key' => 'bbj_votes_received',   'label' => 'Most Votes Received'],
];

$leaders = [];
foreach ($lb_fields as $slug => $cfg) {
  $key = $cfg['key'];
  $rows = array_values(array_filter((array)$season_players, fn($r) => isset($r[$key])));
  usort($rows, fn($a,$b) => (int)$b[$key] <=> (int)$a[$key]);
  $rows = array_slice($rows, 0, 5);
  $leaders[$slug] = array_map(function($r) use ($key) {
    return [
      'name'  => trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')),
      'value' => (int)($r[$key] ?? 0),
      'link'  => $r['permalink'] ?? '#',
    ];
  }, $rows);
}


?>

<main class="v2-primary-container">
	<div class="flex w-full flex-col mb-4 lg:flex-row dark:text-gray-200">
		<section id="season-main-left" class="flex-grow space-y-4 mr-0 lg:mr-4">
			<div class="v2-primary-container-inner mb-4 p-2 rounded-lg">
				<div class="bg-primary500 p-2 rounded-lg">
					<h1 class="text-2xl md:text-4xl font-mainHead text-white"><?php echo $season_name; ?> - Season Hub</h1>
				  <div class="flex mt-2 flex-wrap gap-2">
						<div class="rounded-xl px-2 py-1 text-xs bg-second500 text-primary500">
							Day <?= number_format_i18n($days_passed) ?>/<?= number_format_i18n($season_length) ?>
						</div>
						<div class="rounded-xl px-2 py-1 text-xs bg-blue-200 text-primary500">
							Houseguests: <?= number_format_i18n($hg_total) ?>
						</div>
						<div class="rounded-xl px-2 py-1 text-xs bg-blue-100 text-primary500">
							Active: <?= number_format_i18n($active_count) ?>
						</div>
						<div class="rounded-xl px-2 py-1 text-xs bg-purple-200 text-primary500">
							Jury: <?= number_format_i18n($jury_count) ?>
						</div>
						<div class="rounded-xl px-2 py-1 text-xs bg-gray-300 text-primary500">
							Evicted: <?= number_format_i18n($evicted_count) ?>
						</div>
					</div>
				</div>
				
			</div>

			<?php 
			
			$sort_bucket = function(array $p): int {
				if (!empty($p['current_evicted'])) return 5;
				if (!empty($p['current_jury']))    return 4;
				if (!empty($p['current_nom']))     return 2;
				if (!empty($p['current_pov']))     return 1;
				if (!empty($p['current_hoh']))     return 0;
				return 3; // default active
			};

			usort($season_players, function($a, $b) use ($sort_bucket) {
				if (!is_array($a) || !is_array($b)) return 0;
				$ba = $sort_bucket($a); $bb = $sort_bucket($b);
				if ($ba !== $bb) return $ba <=> $bb;
				$na = trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? ''));
				$nb = trim(($b['first_name'] ?? '').' '.($b['last_name'] ?? ''));
				return strcasecmp($na, $nb);
			});

			?>

			<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
				<?php foreach ((array)$season_players as $player): ?>
					<?php
						if (!is_array($player)) continue;
						$nickname = trim((string)($player['official_nickname'] ?? ''));
						$photo    = $player['profile_picture_url'] ?? '';
						$name     = trim(($player['first_name'] ?? '').' '.($player['last_name'] ?? ''));
						$link     = $player['permalink'] ?? '#';

						// status flags
						$is_hoh     = !empty($player['current_hoh']);
						$is_pov     = !empty($player['current_pov']);
						$is_nom     = !empty($player['current_nom']);
						$is_jury    = !empty($player['current_jury']);
						$is_evicted = !empty($player['current_evicted']);

						// core totals
						$hoh   = (int)($player['bbj_total_hoh'] ?? 0);
						$pov   = (int)($player['bbj_total_pov'] ?? 0);
						$nom   = (int)($player['bbj_total_nom'] ?? 0);
						$votes = (int)($player['bbj_votes_received'] ?? 0);

						// progress: days in house vs season length
						$ev_raw  = trim((string)($player['bbj_evicted_date'] ?? ''));
						$ev_dt   = ($ev_raw && $ev_raw !== '0000-00-00' && $ev_raw !== '0000-00-00 00:00:00') ? new DateTime($ev_raw) : null;
						$exit_dt = $ev_dt ?: $end_date;
						$days_in = ($start_date && $exit_dt) ? max(0, $exit_dt->diff($start_date)->days + 1) : null;
						$pct     = ($season_length > 0 && $days_in !== null) ? (int)round(($days_in / $season_length) * 100) : null;

						// age at season start (your global helper handles DateTime/string)
						$age     = age_calc_player_season($player['date_of_birth'] ?? null, $start_date);
					?>
					<a href="<?= esc_url($link) ?>" class="block">
						<article class="v2-player-season-card rounded-xl border border-slate-200 bg-white p-2 shadow-sm dark:bg-slate-800 dark:border-slate-700">
							<div class="grid grid-cols-[75px_1fr] gap-2">
								<div class="flex-shrink-0 relative">
									<img src="<?= esc_url($photo) ?>" alt="<?= esc_attr($name) ?>"
											class="rounded-lg h-[75px] w-[75px] object-cover <?= $is_evicted ? 'grayscale' : '' ?>">	

									<div class="flex flex-wrap gap-1 text-[10px] absolute bottom-0.5 right-0.5">
										<?php if ($is_hoh): ?><span class="rounded-full border border-emerald-300 bg-emerald-100/80 px-2 py-0.5 text-emerald-700">HOH</span><?php endif; ?>
										<?php if ($is_pov): ?><span class="rounded-full border border-cyan-300 bg-cyan-100/80 px-2 py-0.5 text-cyan-700">POV</span><?php endif; ?>
										<?php if ($is_nom): ?><span class="rounded-full border border-amber-300 bg-amber-100/80 px-2 py-0.5 text-amber-700">Nom</span><?php endif; ?>
										<?php if ($is_jury): ?><span class="rounded-full border border-violet-300 bg-violet-100/80 px-2 py-0.5 text-violet-700">Jury</span><?php endif; ?>
										<?php if ($is_evicted): ?><span class="rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-slate-700">Evicted</span><?php endif; ?>
									</div>
								</div>

								<div class="flex-grow min-w-0">
									<h3 class="text-sm font-semibold truncate">
										<?= esc_html($name) ?>
										<?php if ($nickname !== ''): ?>
											<span class="text-gray-500 font-normal font-hand"> (<?= esc_html($nickname) ?>)</span>
										<?php endif; ?>
									</h3>

									<div class="mt-0.5 text-[11px] text-slate-600">
										Age: <?= $age !== null ? (int)$age : '—' ?>
										<?php if (!empty($player['locality']) || !empty($player['administrative_area_level_1'])): ?>
											• <?= esc_html(trim(($player['locality'] ?? '').($player['locality'] && $player['administrative_area_level_1'] ? ', ' : '').($player['administrative_area_level_1'] ?? ''))) ?>
										<?php endif; ?>
									</div>



									
								</div>						
								
								<div class="col-span-2">
									<div class="mt-1 grid grid-cols-4 gap-1 text-center">
										<div class="rounded border border-slate-200 bg-slate-50 py-0.5"><div class="text-xs font-semibold tabular-nums"><?= $hoh ?></div><div class="text-[9px]">HOH</div></div>
										<div class="rounded border border-slate-200 bg-slate-50 py-0.5"><div class="text-xs font-semibold tabular-nums"><?= $pov ?></div><div class="text-[9px]">POV</div></div>
										<div class="rounded border border-slate-200 bg-slate-50 py-0.5"><div class="text-xs font-semibold tabular-nums"><?= $nom ?></div><div class="text-[9px]">NOM</div></div>
										<div class="rounded border border-slate-200 bg-slate-50 py-0.5"><div class="text-xs font-semibold tabular-nums"><?= $votes ?></div><div class="text-[9px]">Votes</div></div>
									</div>
								</div>

								<div class="col-span-2">
									
									<!-- Progress bar -->
									<div class="flex items-center gap-2">
										<div class="flex items-center justify-between text-[10px] text-slate-500">
											<span>Progress</span>
											<span class="ml-1 tabular-nums"><?= $pct !== null ? $pct.'%' : '—' ?></span>
										</div>
										<div class="h-2 w-full overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-700" role="progressbar"
												aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct !== null ? (int)$pct : 0 ?>">
											<div class="h-full rounded-full bg-thirdColor" style="width: <?= $pct !== null ? (int)$pct : 0 ?>%"></div>
										</div>
									</div>

								</div>
							</div>
						</article>
					</a>
				<?php endforeach; ?>
			</div>

		</section>

		<section id="season-main-right">
			<div class="v2-primary-container-inner p-2 rounded-lg">
				<h2 class="text-lg md:text-2xl font-mainHead mb-2">Season <?= $season_number ?> Info</h2>
				<table class="w-full">
					<tr class="border-b border-slate-200">
						<td class="text-sm md:text-base p-1 font-semibold">Name:</td>
						<td class="text-sm md:text-base p-1 "><?= $season_name ?></td>
					</tr>
					<tr class="border-b border-slate-200">
						<td class="text-sm md:text-base p-1 font-semibold">Start:</td>
						<td class="text-sm md:text-base p-1 "><?= $start_formatted ?></td>
					</tr>
					<tr class="border-b border-slate-200">
						<td class="text-sm md:text-base p-1 font-semibold">End:</td>
						<td class="text-sm md:text-base p-1 "><?= $end_formatted ?></td>
					</tr>
					<tr>
						<td class="text-sm md:text-base p-1 font-semibold">Length:</td>
						<td class="text-sm md:text-base p-1 "><?= $season_length ?> days</td>
					</tr>
				</table>
			</div>


			<!-- Live Now -->
			<div class="v2-primary-container-inner p-2 mt-3 rounded-lg">
				<h3 class="text-base md:text-lg font-semibold text-slate-800 dark:text-slate-100">Live Now</h3>

				<div class="mt-2 grid grid-cols-2 gap-2 text-sm">
					<div class="rounded border border-slate-200 bg-slate-50 p-2">
						<div class="text-slate-500 text-center font-semibold">HOH</div>
						<div class="font-medium truncate">
							<?php if ($live_hoh): ?>
								<a href="<?= esc_url($live_hoh['permalink'] ?? '#') ?>" class="hover:underline flex flex-col items-center justify-center">
									<img src="<?= esc_url($live_hoh['profile_picture_url'] ?? '') ?>" alt="" class="inline-block h-8 w-8 rounded-full object-cover mr-1 align-middle">
									<div><?= esc_html($live_hoh['first_name']) ?></div>
								</a>
								
							<?php else: ?>
								—
							<?php endif; ?>
						</div>
					</div>
					<div class="rounded border border-slate-200 bg-slate-50 p-2">
						<div class="text-slate-500 text-center font-semibold">POV</div>
						<div class="font-medium truncate">
							<?php if ($live_pov): ?>
								<a href="<?= esc_url($live_pov['permalink'] ?? '#') ?>" class="hover:underline flex flex-col items-center justify-center">
									<img src="<?= esc_url($live_pov['profile_picture_url'] ?? '') ?>" alt="" class="inline-block h-8 w-8 rounded-full object-cover mr-1 align-middle">
									<div><?= esc_html($live_pov['first_name']) ?></div>
								</a>
							<?php else: ?>
								—
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="mt-2 rounded border border-slate-200 bg-slate-50 p-2">
					<div class="text-slate-500 text-center font-semibold">On the Block</div>
					<div class="mt-1 flex flex-wrap gap-1 items-center justify-center">
						<?php if (!empty($live_block)): ?>
							<?php foreach (array_slice($live_block, 0, 3) as $ob): ?>
								<div class="">
									<img src="<?= esc_url($ob['profile_picture_url'] ?? '') ?>" alt="" class="inline-block h-8 w-8 rounded-full object-cover mr-1 align-middle">
								</div>
							<?php endforeach; ?>
							<?php if (count($live_block) > 3): ?>
								<span class="text-[11px] text-slate-500">+<?= count($live_block) - 3 ?></span>
							<?php endif; ?>
						<?php else: ?>
							<span class="text-[11px] text-slate-500">—</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="mt-2 grid grid-cols-2 gap-2 text-center">
					<div class="rounded border border-slate-200 bg-slate-50 p-2">
						<div class="text-slate-500 text-center font-semibold">Jury</div>
						<div class="font-semibold"><?= (int)$live_jury ?></div>
					</div>
					<div class="rounded border border-slate-200 bg-slate-50 p-2">
						<div class="text-slate-500 text-center font-semibold">Evicted</div>
						<div class="font-semibold"><?= (int)$live_evicted ?></div>
					</div>
				</div>

				<div class="mt-3">
					<div class="mb-1 flex items-center justify-between text-[11px] text-slate-500">
						<span>Season Progress</span>
						<span>Day <?= number_format_i18n($days_passed) ?>/<?= number_format_i18n($season_length) ?> (<?= $season_progress ?>%)</span>
					</div>
					<div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-700" role="progressbar"
							aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= esc_attr($season_progress) ?>">
						<div class="h-full rounded-full bg-thirdColor" style="width: <?= esc_attr($season_progress) ?>%"></div>
					</div>
				</div>
			</div>

			<!-- Leaderboards -->
			<div class="v2-primary-container-inner p-2 mt-3 rounded-lg">
				<h3 class="text-base md:text-lg font-semibold text-slate-800 dark:text-slate-100">Leaderboards</h3>

				<?php foreach (['hoh'=>'Most HOH Wins','pov'=>'Most POV Wins','nom'=>'Most Nominations','votes'=>'Most Votes Received'] as $key => $title): 
						$list = $leaders[$key] ?? [];
						$max  = 0;
						foreach ($list as $row) { if ($row['value'] > $max) $max = $row['value']; }
						$max = max(1, $max);

						$lb_palette = [
							'hoh'   => ['track' => 'bg-green-100', 'bar' => 'bg-green-500'],
							'pov'   => ['track' => 'bg-amber-100',    'bar' => 'bg-amber-500'],
							'nom'   => ['track' => 'bg-rose-100',   'bar' => 'bg-rose-500'],
							'votes' => ['track' => 'bg-purple-100',    'bar' => 'bg-purple-500'],
						];
				?>
					<div class="mt-2">
						<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><?= esc_html($title) ?></div>
						<ol class="mt-1 space-y-1">
							<?php foreach ($list as $row): 
								$pct = (int) round(($row['value'] / $max) * 100);
							?>
								<li class="flex items-center gap-2">
									<a href="<?= esc_url($row['link']) ?>" class="truncate text-sm hover:underline"><?= esc_html($row['name']) ?></a>
									<div class="ml-auto flex items-center gap-2">
										<?php $pal = $lb_palette[$key] ?? ['track'=>'bg-slate-200/70','bar'=>'bg-primary500']; ?>
											<div class="h-2 w-28 overflow-hidden rounded <?= $pal['track'] ?>">
												<div class="h-full <?= $pal['bar'] ?>" style="width: <?= $pct ?>%"></div>
											</div>
										<span class="w-6 text-right text-xs tabular-nums"><?= (int)$row['value'] ?></span>
									</div>
								</li>
							<?php endforeach; ?>
							<?php if (empty($list)): ?>
								<li class="text-xs text-slate-500">—</li>
							<?php endif; ?>
						</ol>
					</div>
				<?php endforeach; ?>
			</div>

		</section>

		
    <?php get_template_part('template-parts/sidebar-default'); ?>
	</div>
</main>





<?php get_footer();
