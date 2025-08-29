<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_shortcode ('bbj_standings_table', 'bbj_render_standings_table');

function bbj_render_standings_table($atts) {

    
    $days_between = function (DateTime $start, DateTime $end, bool $inclusive = false) {
        $s = (clone $start)->setTime(0,0,0);
        $e = (clone $end)->setTime(0,0,0);
        $d = $s->diff($e)->days;      // exclusive
        return $inclusive ? $d + 1 : $d;
    };


    

    $season_id = (int) get_option('bbj_v2_current_season');

    if ($season_id <= 0) {
        return '<p>' . esc_html__('Invalid season ID.', 'bbj') . '</p>';
    }

    // caching 
      $is_admin_view = is_user_logged_in() && current_user_can('manage_options');
      $cache_key     = bbj_standings_table_cache($season_id, $is_admin_view);
      if (false !== ($html = wp_cache_get($cache_key, BBJ_CACHE_GROUP))) {
          return $html;
      }

    $season_info = bbj_v2_get_season_by_id($season_id);
    $season_name = $season_info['full_name'] ?? esc_html__('Unknown Season', 'bbj');
    $season_players = bbj_v2_get_season_players($season_id, 'bbj_v2_spoiler_bar');

    bbj_log3(print_r($season_players, true));

    if (empty($season_players) || !is_array($season_players)) {
        return '<p>' . esc_html__('No players found for this season.', 'bbj') . '</p>';
    }

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
    $current_date      = new DateTime('now', $tz);
    $season_start_date = !empty($season_info['start_date']) ? new DateTime($season_info['start_date'], $tz) : null;
    $season_end_date   = !empty($season_info['end_date'])   ? new DateTime($season_info['end_date'], $tz)   : null;
    $season_length_days = ($season_start_date && $season_end_date)
    ? max(0, $days_between($season_start_date, $season_end_date, true))  // inclusive
    : 0;
    $days_elapsed = $season_start_date ? $season_start_date->diff($current_date)->days : 0;


    foreach ($season_players as &$player) {
        if (!empty($player['official_nickname'])) {
            $player['first_name'] = $player['official_nickname'];
        }
    }
    unset($player);
    
    if ($season_start_date) {
        if ($current_date < $season_start_date) {
            $days_elapsed = 0;
        } elseif ($season_end_date && $current_date > $season_end_date) {
            $days_elapsed = $season_length_days;
        } else {
            $end_for_elapsed = $season_end_date
                ? ($current_date < $season_end_date ? $current_date : $season_end_date)
                : $current_date;
            $days_elapsed = $season_start_date->diff($end_for_elapsed)->days;
        }
    } else {
        $days_elapsed = 0;
    }

    $days_remaining   = max(0, $season_length_days - $days_elapsed);
    $is_season_active = ($season_start_date && $season_end_date && $current_date >= $season_start_date && $current_date <= $season_end_date);

  
    $progressPct = ($season_length_days > 0)
    ? max(0, min(100, round(($days_elapsed / $season_length_days) * 100)))
    : 0;

    usort($season_players, function ($a, $b) use ($season_end_date, $current_date, $tz) {
        $aEvicted = ((int)($a['current_jury'] ?? 0) === 1) || ((int)($a['current_evicted'] ?? 0) === 1);
        $bEvicted = ((int)($b['current_jury'] ?? 0) === 1) || ((int)($b['current_evicted'] ?? 0) === 1);

        if ($aEvicted !== $bEvicted) {
            return $aEvicted <=> $bEvicted; // active first
        }

        if (!$aEvicted && !$bEvicted) {
            return ($b['bbj_total_hoh'] ?? 0) <=> ($a['bbj_total_hoh'] ?? 0);
        }

        $fallbackEnd = $season_end_date ?: $current_date;

        $aRaw  = trim($a['bbj_evicted_date'] ?? '');
        $bRaw  = trim($b['bbj_evicted_date'] ?? '');

        $aDate = (!$aRaw || $aRaw === '0000-00-00' || $aRaw === '0000-00-00 00:00:00')
            ? $fallbackEnd
            : new DateTime($aRaw, $tz);

        $bDate = (!$bRaw || $bRaw === '0000-00-00' || $bRaw === '0000-00-00 00:00:00')
            ? $fallbackEnd
            : new DateTime($bRaw, $tz);

        $byDate = $bDate->getTimestamp() <=> $aDate->getTimestamp(); // later first
        if ($byDate !== 0) return $byDate;

        return ($b['bbj_total_hoh'] ?? 0) <=> ($a['bbj_total_hoh'] ?? 0);


    });



    
  
  
    ob_start();
    ?>

    <div class="grid grid-cols-5 items-center text-center p-2 bg-gray-100 dark:bg-gray-800 rounded-lg mb-6">
      <div class="font-sans font-semibold text-xs">Days</div>
      <div class="font-sans font-semibold text-xs">Elapsed</div>
      <div class="font-sans font-semibold text-xs">Rem</div>
      <div class="font-sans font-semibold text-xs">%</div>
      <div class="font-sans font-semibold text-xs">Status</div>

      <div class="text-primary500 font-semibold"><?php echo esc_html($season_length_days); ?></div>
      <div class="text-primary500 font-semibold"><?php echo esc_html(min($days_elapsed, $season_length_days)); ?></div>
      <div class="text-primary500 font-semibold"><?php echo esc_html(max($days_remaining, 0)); ?></div>
    <div class="text-primary500 font-semibold"><?php echo esc_html($season_length_days > 0 ? round(($days_elapsed / $season_length_days) * 100) . '%' : '0%'); ?></div>
    <div class="text-primary500 font-semibold text-xs"><?php echo $is_season_active ? esc_html__('Active', 'bbj') : esc_html__('Complete', 'bbj'); ?></div>


        <div class="col-span-5">
        
            <div class="w-full h-1 bg-gray-300 dark:bg-gray-700">
                <div class="h-1  bg-second500 transition-[width] duration-700 ease-out"
            style="width: <?php echo esc_attr($progressPct); ?>%;"></div>
            </div>
        </div>

    </div>


    

   <div class="grid grid-cols-[minmax(9ch,_1fr)_repeat(5,_max-content)] items-center bbj-player-card border p-4 mb-4 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-300">
      <div class="text-left font-semibold text-xs bg-gray-200 dark:bg-gray-700 p-1">Player</div>
      <div class="text-center font-semibold text-xs bg-gray-200 dark:bg-gray-700 p-1">H</div>
      <div class="text-center font-semibold text-xs bg-gray-200 dark:bg-gray-700 p-1">V</div>
      <div class="text-center font-semibold text-xs bg-gray-200 dark:bg-gray-700 p-1">N</div>
      <div class="text-center font-semibold text-xs bg-gray-200 dark:bg-gray-700 p-1">VR</div>      
      <div class="text-center font-semibold text-xs bg-gray-200 dark:bg-gray-700 p-1">TD</div>


      <?php foreach ($season_players as $player) { ?>
        <?php 
          $evRaw = trim($player['bbj_evicted_date'] ?? '');

          $effective_clock_end   = $season_end_date
              ? ($current_date < $season_end_date ? $current_date : $season_end_date)
              : $current_date;

          $has_valid_eviction_dt = ($evRaw && $evRaw !== '0000-00-00' && $evRaw !== '0000-00-00 00:00:00');
          $player_end = $has_valid_eviction_dt ? new DateTime($evRaw, $tz) : $effective_clock_end;

          $days_in_house = ($season_start_date && $player_end)
              ? max(0, $days_between($season_start_date, $player_end, true))
              : 0;

          // flag player if evicted
          $is_evicted   = ((int)($player['current_jury'] ?? 0) === 1) || ((int)($player['current_evicted'] ?? 0) === 1);
          $text_classes = $is_evicted ? 'text-gray-400 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100';

       
        ?>
        <div class="truncate text-left flex items-center <?php echo esc_attr($text_classes)?>">
         
          <img src="<?php echo esc_url($player['profile_picture_url'] ?? ''); ?>"
            class="h-4 w-4 rounded-full mr-1 object-cover"
            alt="<?php echo esc_attr(($player['first_name'] ?? '') . ' avatar'); ?>">
            <a href="<?php echo esc_url($player['permalink'] ?? ''); ?>" ><?php echo esc_html($player['first_name'] ?? ''); ?></a>
        </div>
        
        <div class="text-center tabular-nums whitespace-nowrap <?php echo esc_attr($text_classes)?>"><?php echo esc_html($player['bbj_total_hoh'] ?? '0'); ?></div>
        <div class="text-center tabular-nums whitespace-nowrap <?php echo esc_attr($text_classes)?>"><?php echo esc_html($player['bbj_total_pov'] ?? '0'); ?></div>
        <div class="text-center tabular-nums whitespace-nowrap <?php echo esc_attr($text_classes)?>"><?php echo esc_html($player['bbj_total_nom'] ?? '0'); ?></div>
        <div class="text-center tabular-nums whitespace-nowrap <?php echo esc_attr($text_classes)?>"><?php echo esc_html($player['bbj_votes_received'] ?? '0'); ?></div>
        <div class="text-center tabular-nums whitespace-nowrap <?php echo esc_attr($text_classes)?>"><?php echo esc_html($days_in_house); ?> 

        </div>
      <?php }  ?>
      <div class="border-t border-gray-300 dark:border-gray-700 text-xs col-span-6 mt-4 pt-2 text-gray-600 dark:text-gray-400 italic grid grid-cols-[35px_1fr]">
        <div class="text-center">H</div>
        <div>Head of Household Wins</div>
        <div class="text-center">V</div>
        <div>Power of Veto Wins</div>
        <div class="text-center">N</div>
        <div>Nominations</div>
        <div class="text-center">VR</div>
        <div>Votes Received</div>
        <div class="text-center">TD</div>
        <div>Total Days in the House</div>
      </div>
    </div>

    

    <?php
    $html = ob_get_clean();

    // Short TTL while season is active, long when it’s over.
    $ttl = $is_season_active ? BBJ_CACHE_TTL : DAY_IN_SECONDS;
    wp_cache_set($cache_key, $html, BBJ_CACHE_GROUP, $ttl);

    return $html;
}