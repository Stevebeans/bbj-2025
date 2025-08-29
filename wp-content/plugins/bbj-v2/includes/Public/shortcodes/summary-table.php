<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_shortcode( 'bbj_summary_table', 'bbj_summary_table_function' );

function bbj_summary_table_function() {

  global $bbj_is_admin;

  $current_season_id = (int) get_option('bbj_v2_current_season');
  
  if ( $current_season_id <= 0 ) { return ''; }


  // caching 
  $cache_key = bbj_spoiler_bar_summary_cache( $current_season_id, $bbj_is_admin );
  if ( false !== ($html = wp_cache_get($cache_key, BBJ_CACHE_GROUP)) ) {
    return $html;
  }

  $season_players = bbj_v2_get_season_players( $current_season_id, 'bbj_v2_spoiler_bar');


  $multi_hoh = false;
  $multi_pov = false;
  $multi_nom = false;
  $multi_havenot = false;

  $img_height = 40;
  $img_width = 40;
  

  // Get groups (I did array because some seasons might have multiples)
  $head_household = [];  
  $power_of_veto = [];
  $nominees = [];
  $have_nots = [];
  foreach ( $season_players as $player ) {
    if ( !empty($player['current_hoh']) ) {
      $head_household[] = 
       [
         'name' => $player['official_nickname'] ?: $player['first_name'],
         'image' => $player['profile_picture_url'] ?: '',
         'image-height' => $img_height,
         'image-width' => $img_width,
         'permalink' => get_permalink( $player['bbj_player'] )
       ];
    }

    if ( !empty($player['current_pov']) ) {
      $power_of_veto[] = 
       [
         'name' => $player['official_nickname'] ?: $player['first_name'],
         'image' => $player['profile_picture_url'] ?: '',
         'image-height' => $img_height,
         'image-width' => $img_width,
         'permalink' => get_permalink( $player['bbj_player'] )
       ];
    }

    if ( !empty($player['current_nom']) ) {
      $nominees[] = 
       [
         'name' => $player['official_nickname'] ?: $player['first_name'],
         'image' => $player['profile_picture_url'] ?: '',
         'image-height' => $img_height,
         'image-width' => $img_width,
         'permalink' => get_permalink( $player['bbj_player'] )
       ];
    }

    if ( !empty($player['current_havenot']) ) {
      $have_nots[] = 
       [
         'name' => $player['official_nickname'] ?: $player['first_name'],
         'image' => $player['profile_picture_url'] ?: '',
         'image-height' => $img_height,
         'image-width' => $img_width,
         'permalink' => get_permalink( $player['bbj_player'] )
       ];
    }

  }

  if ( count($head_household) > 1 ) {
    $multi_hoh = true;
  }

  if ( count($power_of_veto) > 1 ) {
    $multi_pov = true;
  }

  if ( count($nominees) > 1 ) {
    $multi_nom = true;
  }

  if ( count($have_nots) > 1 ) {
    $multi_havenot = true;
  }

  


  ob_start();
  ?>

  <div class="grid grid-cols-2 gap-2">
    <div class="v2-summary-card">
      <div class="w-full spoilerbar-hoh text-white text-center text-xs p-1">
        Head of Household
      </div>
      <div class="p-2 flex flex-wrap items-center justify-center">
        <?php if (!empty($head_household)) : ?>
          <?php 
            foreach ($head_household as $player) {
          ?>
            <a href="<?php echo esc_url($player['permalink']); ?>">
              <div class="flex flex-col items-center <?php echo $multi_hoh ? 'ml-2' : ''; ?>">          
                <img
                  src="<?php echo esc_url($player['image']); ?>"
                  alt="<?php echo esc_attr($player['name']); ?>"
                  height="<?php echo esc_attr($player['image-height']); ?>" 
                  width="<?php echo esc_attr($player['image-width']); ?>"
                  class="mx-auto rounded-full"
                />
                <div class="text-xs font-mainHead text-center mt-1">
                  <a href="<?php echo esc_url($player['permalink']); ?>"><?php echo esc_html($player['name']); ?>
                </div>
              </div>
            </a>
            <?php
            }
            ?>
        <?php else: ?>
          <div class="text-center text-gray-600 dark:text-gray-400 italic text-xs">No HoH Yet</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="v2-summary-card">
      <div class="w-full spoilerbar-pov text-white text-center text-xs p-1">
        Power of Veto
      </div>
      <div class="p-2 flex flex-wrap items-center justify-center">
        <?php if (!empty($power_of_veto)) : ?>
          <?php 
            foreach ($power_of_veto as $player) {
          ?>
            <a href="<?php echo esc_url($player['permalink']); ?>">
              <div class="flex flex-col items-center <?php echo $multi_pov ? 'ml-2' : ''; ?>">          
                <img
                  src="<?php echo esc_url($player['image']); ?>"
                  alt="<?php echo esc_attr($player['name']); ?>"
                  height="<?php echo esc_attr($player['image-height']); ?>" 
                  width="<?php echo esc_attr($player['image-width']); ?>"
                  class="mx-auto rounded-full"
                />
                <div class="text-xs font-mainHead text-center mt-1">
                  <a href="<?php echo esc_url($player['permalink']); ?>"><?php echo esc_html($player['name']); ?>
                </div>
              </div>
            </a>
            <?php
            }
            ?>
        <?php else: ?>
          <div class="text-center text-gray-600 dark:text-gray-400 italic text-xs">No PoV Yet</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="v2-summary-card">
      <div class="w-full spoilerbar-nom text-white text-center text-xs p-1">
        Nominees
      </div>
      <div class="p-2 flex flex-wrap items-center justify-center">
        <?php if (!empty($nominees)) : ?>
          <?php 
            foreach ($nominees as $player) {
          ?>
            <a href="<?php echo esc_url($player['permalink']); ?>">
              <div class="flex flex-col items-center <?php echo $multi_nom ? 'ml-2' : ''; ?>">          
              <img
                src="<?php echo esc_url($player['image']); ?>"
                alt="<?php echo esc_attr($player['name']); ?>"
                height="<?php echo esc_attr($player['image-height']); ?>" 
                width="<?php echo esc_attr($player['image-width']); ?>"
                class="mx-auto rounded-full"
              />
              <div class="text-xs font-mainHead text-center mt-1">
                <a href="<?php echo esc_url($player['permalink']); ?>"><?php echo esc_html($player['name']); ?>
              </div>
            </div>
          </a>
          <?php
          }
          ?>
        <?php else: ?>
          <div class="text-center text-gray-600 dark:text-gray-400 italic text-xs">No Nominees Yet</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="v2-summary-card">
      <div class="w-full bg-gray-800 text-white text-center text-xs p-1">
        Have Nots
      </div>
      <div class="p-2 flex flex-wrap items-center justify-center">
        <?php if (!empty($have_nots)) : ?>
          <?php 
            foreach ($have_nots as $player) {
          ?>
            <a href="<?php echo esc_url($player['permalink']); ?>">
              <div class="flex flex-col items-center <?php echo $multi_havenot ? 'ml-2' : ''; ?>">          
              <img
                src="<?php echo esc_url($player['image']); ?>"
                alt="<?php echo esc_attr($player['name']); ?>"
                height="<?php echo esc_attr($player['image-height']); ?>" 
                width="<?php echo esc_attr($player['image-width']); ?>"
                class="mx-auto rounded-full"
              />
              <div class="text-xs font-mainHead text-center mt-1">
                <a href="<?php echo esc_url($player['permalink']); ?>"><?php echo esc_html($player['name']); ?>
              </div>
            </div>
          </a>
          <?php
          }
          ?>
        <?php else: ?>
          <div class="text-center text-gray-600 dark:text-gray-400 italic text-xs">No Current HN</div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <?php
  $html = ob_get_clean();
  wp_cache_set($cache_key, $html, BBJ_CACHE_GROUP, BBJ_CACHE_TTL);
  
  return $html;
}