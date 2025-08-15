<?php 

defined( 'ABSPATH' ) || exit;

add_shortcode( 'bbj_spoiler_bar', 'bbj_render_spoiler_bar' );

function bbj_render_spoiler_bar() {
    global $bbj_is_admin;
    // get current season from options 
    $current_season_id = get_option( 'bbj_v2_current_season', '' );
    $current_season = bbj_v2_get_season_by_id( $current_season_id );

    // Caching 
    $cache_key = bbj_spoiler_bar_cache_key( (int)$current_season_id, (bool)$bbj_is_admin );
    if ( false !== ($cached = wp_cache_get($cache_key, BBJ_CACHE_GROUP)) ) {
        return $cached;
    }

    // get season 27 as that has 17 players
    // $is_s27 = isset($current_season['season_number'])
    // ? ((int) $current_season['season_number'] === 27)
    // : ((int) $current_season_id === 27);

    // $gridColsClass = $is_s27 ? 'grid-cols-9' : 'grid-cols-8';

    
    $season_players = bbj_v2_get_season_players( $current_season_id, 'bbj_v2_spoiler_bar' );

    

    // sort players by their spoiler weight
    usort($season_players, function ($a, $b) {
        $wa = bbj_spoiler_weight($a);
        $wb = bbj_spoiler_weight($b);
        if ($wa !== $wb) {
            return $wa - $wb;
        }

        // Same bucket: Jury (5) or Evicted (6) → sort by bbj_evicted_date
        if ($wa === 5 || $wa === 6) {
            $da = bbj_eviction_ts($a['bbj_evicted_date'] ?? null);
            $db = bbj_eviction_ts($b['bbj_evicted_date'] ?? null);

            // nulls (no real date) go last
            if ($da === $db) {
                // tie-break by name/id to avoid unstable shuffles
                $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                $bn = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
                $cmp = strcasecmp($an, $bn);
                return $cmp !== 0 ? $cmp : ((int)($a['bbj_player'] ?? 0) <=> (int)($b['bbj_player'] ?? 0));
            }
            if ($da === null) return 1;
            if ($db === null) return -1;

            // NEWEST → OLDEST (flip to $da <=> $db for oldest → newest)
            return $db <=> $da;
        }

        // Other buckets: keep stable-ish by name
        $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
        $bn = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
        return strcasecmp($an, $bn);
    });

    


    ob_start();
    ?>
    <div class="bbj-spoiler-bar flex">
         <div class="flex items-center">
            <button type="button" class="spoilerbar-vertical-left hidden text-center font-semibold p-1 text-primary500 font-sans flex items-center lg:hidden">
                <img
                    src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/left-arrow.svg' ); ?>"
                    alt=""
                    width="15" height="15"
                    aria-hidden="true"
                    class="inline mt-1"
                    />     
                    Swipe    
                    <img
                    src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/left-arrow.svg' ); ?>"
                    alt=""
                    width="15" height="15"
                    aria-hidden="true"
                    class="inline mb-1"
                    />   
            </button>
        </div>
        <?php if ( ! empty( $season_players ) ) : ?>
            <div class="w-full overflow-x-auto py-1 bbj-track scroll-smooth">
                <div class="flex flex-nowrap gap-1 lg:flex mx-auto w-max">
                <?php foreach ( $season_players as $player ) : ?>
                    
                    <?php 
                    $evicted = bbj_spoiler_label( $player ) === 'Evicted' ? 'spoilerbar-evicted-img' : '';
                    $jury = bbj_spoiler_label( $player ) === 'Jury' ? 'spoilerbar-jury-img' : '';
                    $display_name = $player['official_nickname'] 
                        ? '"' . $player['official_nickname'] . '"' 
                        : $player['first_name'];
                     ?>
              
                        <div class="w-[52px] lg:w-14">
                            <!-- Status Banner -->
                            <div class="top-0 left-0 <?php echo esc_attr( bbj_status_prefix( $player ) ); ?>  text-white text-center text-[10px] w-full border-t-2 border-r-2 border-l-2 font-sans rounded-t-md"><?php echo esc_html( bbj_spoiler_label( $player ) ); ?></div> 
                            
                            <!-- Profile Image  -->
                            <div class="relative block w-full  h-12 lg:h-[80px]  font-mainHead overflow-hidden border-l-2 border-r-2 <?php echo esc_attr( bbj_status_prefix( $player ) ); ?>">
                            <a href="<?php echo esc_url( $player['permalink'] ); ?>" title="<?php echo esc_attr( $player['first_name'] . ' ' . $player['last_name'] ); ?>">
                                
                                <img src="<?php echo esc_url( $player['profile_picture_url'] ); ?>" alt="<?= esc_attr( $player['first_name'] . ' ' . $player['last_name'] ); ?>" class="w-full h-12 lg:h-[80px] object-cover block <?php echo $evicted ?> <?php echo $jury ?>">
                                
                                </a>                              
                            </div>  

                            <!-- Name Bar -->
                            <div class="<?php echo esc_attr( bbj_status_prefix( $player ) ); ?> rounded-b-md border-r-2 border-l-2 border-b-2 text-white text-[10px] flex items-center justify-center font-sans "><?php echo esc_html( $display_name ); ?></div>
                            <?php if ( $bbj_is_admin ) : ?>
                                <div class="text-xs text-center"><a href="/wp-admin/admin.php?page=bbj-v2-add-edit-player&method=edit&player_id=<?php echo esc_attr( $player['bbj_player'] ); ?>" class="text-[10px]">Edit</a></div>
                            <?php endif; ?>
                        </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php else : ?>
            <p class="text-center">No players found for this season.</p>
            <?php if ( $bbj_is_admin ) : ?>
                <p class="text-center"><a href="<?= esc_url( admin_url( 'admin.php?page=bbj-v2-settings' ) ); ?>">Edit Current Season</a></p>
            <?php endif; ?>
        <?php endif; ?>  
        <div class="flex items-center">
            <button type="button" class="spoilerbar-vertical-right hidden text-center font-semibold p-1 text-primary500 font-sans flex lg:hidden items-center">
                <img
                src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/right-arrow.svg' ); ?>"
                alt=""
                width="15" height="15"
                aria-hidden="true"
                class="inline mb-1"
                />     
                Swipe    
                <img
                src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/right-arrow.svg' ); ?>"
                alt=""
                width="15" height="15"
                aria-hidden="true"
                class="inline mt-1"
                />             
            </button>
        </div>
        
    </div>
    <?php
    $html = ob_get_clean();
    wp_cache_set($cache_key, $html, BBJ_CACHE_GROUP, BBJ_CACHE_TTL);
    return $html;
}

/**
 * Return a numeric “weight” for sorting.
 * Lower numbers come first.
 */
function bbj_spoiler_weight(array $player): int {
    if (!empty($player['current_hoh']))     return 1; // HoH
    if (!empty($player['current_pov']))     return 2; // PoV

    // Active = no HoH/PoV/Nom/Jury/Evicted
    if (
        empty($player['current_hoh']) &&
        empty($player['current_pov']) &&
        empty($player['current_nom']) &&
        empty($player['current_jury']) &&
        empty($player['current_evicted'])
    ) {
        return 3; // Active
    }

    if (!empty($player['current_nom']))     return 4; // Nom
    if (!empty($player['current_jury']))    return 5; // Jury
    if (!empty($player['current_evicted'])) return 6; // Evicted

    return 3; // fallback = Active
}

// Updated label function to show multiple statuses
function bbj_spoiler_label( array $player ): string {
    $labels = [];
    if ( ! empty( $player['current_hoh'] ) )     { $labels[] = 'HoH'; }
    if ( ! empty( $player['current_pov'] ) )     { $labels[] = 'PoV'; }
    if ( ! empty( $player['current_nom'] ) )     { $labels[] = 'Nom'; }
    if ( ! empty( $player['current_havenot'] ) ) { $labels[] = 'HaveNot'; }
    if ( ! empty( $player['current_jury'] ) )   { $labels[] = 'Jury'; }
    if ( ! empty( $player['current_evicted'] ) ){ $labels[] = 'Evicted'; }
    if ( empty( $labels ) && ! empty( $player['current_safe'] ) ) { $labels[] = 'Safe'; }
    if ( empty( $labels ) && ! empty( $player['current_misc'] ) ) { 
        $labels[] = $player['misc_notes'] ?: 'Misc';
    }
    return ! empty( $labels ) ? implode( ', ', $labels ) : '&nbsp;'; // return empty string if no labels
}

function bbj_status_color ( array $player ): string {
   if ( ! empty( $player['current_hoh'] ) )     return 'hoh';
   if ( ! empty( $player['current_pov'] ) )     return 'pov';
   if ( ! empty( $player['current_evicted'] ) ) return 'evicted';
   if ( ! empty( $player['current_jury'] ) )    return 'jury';
   if ( ! empty( $player['current_nom'] ) )     return 'nom';
   if ( ! empty( $player['current_havenot'] ) ) return 'havenot';
   if ( ! empty( $player['current_safe'] ) )    return 'safe';
   if ( ! empty( $player['current_misc'] ) )    return 'havenot';

    
    // Default to active
   return 'active';
}

function bbj_status_prefix ( array $player ): string {
    return 'spoilerbar-' . bbj_status_color( $player );
}


// CHANGED: treat '0000-00-00', '0000-00-00 00:00:00', '', '0', and pre-epoch/0 as "no date"
function bbj_eviction_ts($v) {
    if ($v === null) return null;

    if (is_string($v)) {
        $v = trim($v);
        if ($v === '' || $v === '0' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
            return null;
        }
    }

    if (is_numeric($v)) {
        $iv = (int) $v;
        // ms → s if needed
        if ($iv > 2000000000) $iv = (int) floor($iv / 1000);
        return $iv > 0 ? $iv : null; // pre-epoch/0 → null
    }

    $ts = strtotime($v);
    return ($ts !== false && $ts > 0) ? $ts : null;
}

