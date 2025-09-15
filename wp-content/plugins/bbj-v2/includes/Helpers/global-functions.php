<?php 

// Cache group + TTL (5m safety net)
if ( ! defined('BBJ_CACHE_GROUP') ) define('BBJ_CACHE_GROUP', 'bbj_v2');
if ( ! defined('BBJ_CACHE_TTL') )   define('BBJ_CACHE_TTL', 300);

// Helpers
function bbj_spoiler_bar_cache_key( $season_id, $is_admin ) {
    return sprintf('spoiler_bar_html:s%u:a%s', (int)$season_id, $is_admin ? '1' : '0');
}
function bbj_players_cache_key( $season_id, $size ) {
    return sprintf('season_players:s%u:size:%s', (int)$season_id, $size);
}

// cache key for summary table
function bbj_spoiler_bar_summary_cache( $season_id, $is_admin ) {
    return sprintf('spoiler_bar_summary:s%u:a%s', (int)$season_id, $is_admin ? '1' : '0');
}

function bbj_standings_table_cache( $season_id, $is_admin ) {
    return sprintf('standings_table:s%u:a%s', (int)$season_id, $is_admin ? '1' : '0');
}

function bbj_hot_posts_cache( $count, $is_admin, $days = 14 ) {
  $ver = (int) ( wp_cache_get('hot_posts:ver', BBJ_CACHE_GROUP) ?: 1 );
  return sprintf('hot_posts:v%u:c%u:d%u:a%s', $ver, (int) $count, (int) $days, $is_admin ? '1' : '0');
}


function bbj_spoiler_bar_bust_cache( $season_id ) {
    // bust both admin/non-admin HTML
    wp_cache_delete( bbj_spoiler_bar_cache_key($season_id, false), BBJ_CACHE_GROUP );
    wp_cache_delete( bbj_spoiler_bar_cache_key($season_id, true),  BBJ_CACHE_GROUP );
    // bust common players queries (add more sizes if you use them)
    wp_cache_delete( bbj_players_cache_key($season_id, 'bbj_v2_spoiler_bar'), BBJ_CACHE_GROUP );
    wp_cache_delete( bbj_players_cache_key($season_id, 'bbj_v2_profile_image'), BBJ_CACHE_GROUP );
    // bust summary table cache
    wp_cache_delete( bbj_spoiler_bar_summary_cache($season_id, false), BBJ_CACHE_GROUP );
    wp_cache_delete( bbj_spoiler_bar_summary_cache($season_id, true),  BBJ_CACHE_GROUP );
    // bust standings table cache
    wp_cache_delete( bbj_standings_table_cache($season_id, false), BBJ_CACHE_GROUP );
    wp_cache_delete( bbj_standings_table_cache($season_id, true),  BBJ_CACHE_GROUP );

    // delete hot posts 
    bbj_hot_posts_bump_cache_ver();

}

function bbj_hot_posts_bump_cache_ver() {
  $k = 'hot_posts:ver';

  // Prefer an atomic increment when available.
  if ( function_exists('wp_cache_incr') ) {
    $v = wp_cache_incr($k, 1, BBJ_CACHE_GROUP);
    if ( false === $v ) {
      // initialize if missing
      wp_cache_set($k, 1, BBJ_CACHE_GROUP);
    }
  } else {
    $v = (int) wp_cache_get($k, BBJ_CACHE_GROUP);
    wp_cache_set($k, $v ? ($v + 1) : 1, BBJ_CACHE_GROUP);
  }
}

// New or status-changed comments can reorder results
add_action('comment_post',               'bbj_hot_posts_bump_cache_ver', 10, 3);
add_action('edit_comment',               'bbj_hot_posts_bump_cache_ver');
add_action('deleted_comment',            'bbj_hot_posts_bump_cache_ver');
add_action('trashed_comment',            'bbj_hot_posts_bump_cache_ver');
add_action('transition_comment_status',  'bbj_hot_posts_bump_cache_ver', 10, 3);
add_action('wp_set_comment_status',      'bbj_hot_posts_bump_cache_ver', 10, 2);

// New/updated posts within the window can affect the set
add_action('save_post',                  'bbj_hot_posts_bump_cache_ver', 10, 3);

function bbj_v2_get_season_players($season_id, $size = 'bbj_v2_profile_image') {
    global $wpdb;



    $pk = bbj_players_cache_key( (int)$season_id, (string)$size );

    

    if ( false !== ($players = wp_cache_get($pk, BBJ_CACHE_GROUP)) ) {
        return $players;
    }

    
    // 1) Fetch your players exactly like before
    $players = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT *
             FROM " . BBJ_V2_TABLE_PLAYERS . " p
             INNER JOIN " . BBJ_V2_TABLE_LINKS . " l
               ON p.id = l.bbj_player
             LEFT JOIN " . BBJ_V2_TABLE_GEO . " g
               ON p.id = g.ID
             WHERE l.bbj_season = %d
             ORDER BY l.bbj_evicted_date ASC",
            $season_id
        ),
        ARRAY_A
    );

    
    

    // 2) Loop & pull in the MetaBox image
    foreach ( $players as &$player ) {
        
        $post_id = $player['id'];
        $player_id = $player[ 'bbj_player' ];

        

        // get permalink 
        $player['permalink'] = get_permalink($player_id);

       // if $player['profile_picture' > 0] then look up the image
        if ( ! empty( $player['profile_picture'] ) && is_numeric( $player['profile_picture'] ) ) {
            $image = wp_get_attachment_image_src( $player['profile_picture'], $size );
           
            if ( $image ) {
                $player['profile_picture_url'] = $image[0]; // URL of the image
                $player['profile_picture_width'] = $image[1]; // Width of the image
                $player['profile_picture_height'] = $image[2]; // Height of the image
            } else {
                // If no image found, set to default or empty
                $player['profile_picture_url'] = '';
                $player['profile_picture_width'] = 0;
                $player['profile_picture_height'] = 0;
            }
        } else {
            // If no profile picture, set to default or empty
            $player['profile_picture_url'] = '';
            $player['profile_picture_width'] = 0;
            $player['profile_picture_height'] = 0;
        }
        
    }
    
    wp_cache_set($pk, $players, BBJ_CACHE_GROUP, BBJ_CACHE_TTL);
    
    return $players;
}



function bbj_v2_get_all_players($order_by = 'name', $order = 'ASC') {
    global $wpdb;

    // Validate order_by and order parameters
    $valid_order_by = ['name', 'first_name', 'last_name', 'id'];
    $valid_order = ['ASC', 'DESC'];

    if ( ! in_array($order_by, $valid_order_by, true) ) {
        $order_by = 'first_name'; // Default to first_name if invalid
    }

    if ( ! in_array($order, $valid_order, true) ) {
        $order = 'ASC'; // Default to ASC if invalid
    }

    // Prepare the ORDER BY clause
    $order_by = in_array( $order_by, $valid_order_by, true )
        ? $order_by
        : 'name';
    $order = strtoupper( $order );
    $order = in_array( $order, $valid_order, true )
        ? $order
        : 'ASC';

    $sql = "
        SELECT *
          FROM " . BBJ_V2_TABLE_PLAYERS . " bbjP
          LEFT JOIN " . BBJ_V2_TABLE_GEO . " bbjG ON bbjG.ID = bbjP.id
         ORDER BY {$order_by} {$order}";

    $players = $wpdb->get_results($sql, ARRAY_A);

    

    return $players;
}


function bbj_v2_get_player ($player_id) {
    global $wpdb;

    // Validate player_id
    if ( ! is_numeric($player_id) || $player_id <= 0 ) {
        return null; // Invalid player ID
    }


    // Fetch player data
    $player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM " . BBJ_V2_TABLE_PLAYERS . " bbjp
            LEFT JOIN " . BBJ_V2_TABLE_GEO . " bbjg ON bbjg.ID = bbjp.id
            WHERE bbjp.id = %d",
            $player_id
        ),
        ARRAY_A
    );

    

    return $player;
}


function bbj_v2_get_seasons ($order_by = 'season_number', $order = 'DESC') {
    global $wpdb;

    // Validate order_by and order parameters
    $valid_order_by = ['season_number', 'full_name', 'start_date', 'end_date'];
    $valid_order = ['ASC', 'DESC'];

    if ( ! in_array($order_by, $valid_order_by, true) ) {
        $order_by = 'season_number'; // Default to season_number if invalid
    }

    if ( ! in_array($order, $valid_order, true) ) {
        $order = 'DESC'; // Default to DESC if invalid
    }

    // Prepare the ORDER BY clause
    $order_by = in_array( $order_by, $valid_order_by, true )
        ? $order_by
        : 'season_number';
    $order = strtoupper( $order );
    $order = in_array( $order, $valid_order, true )
        ? $order
        : 'DESC';

    $sql = "
        SELECT *
          FROM " . BBJ_V2_TABLE_SEASONS . "
         ORDER BY {$order_by} {$order}";

    $seasons = $wpdb->get_results($sql, ARRAY_A);

    

    return $seasons;
}

function bbj_v2_get_season_by_id($season_id) {
    global $wpdb;

    // Validate season_id
    if ( ! is_numeric($season_id) || $season_id <= 0 ) {
        return null; // Invalid season ID
    }

    // Fetch season data
    $season = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
            FROM " . BBJ_V2_TABLE_SEASONS . "
            WHERE id = %d",
            $season_id
        ),
        ARRAY_A
    );

    

    return $season;
}


function bbj_v2_get_seasons_by_player($player_id) {
    global $wpdb;

    // Validate player_id
    if ( ! is_numeric($player_id) || $player_id <= 0 ) {
        return []; // Invalid player ID
    }

    // Fetch seasons for the given player
    $seasons = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.*, l.*
            FROM " . BBJ_V2_TABLE_SEASONS . " s
            INNER JOIN " . BBJ_V2_TABLE_LINKS . " l ON s.id = l.bbj_season
            WHERE l.bbj_player = %d
            ORDER BY s.season_number DESC",
            $player_id
        ),
        ARRAY_A
    );

    

    return $seasons;
}


// Helper Function for ads 

function bbj_get_ad( string $slot ): string {
    $opts = get_option('bbj_ads', []);
    return isset($opts[$slot]) ? (string) $opts[$slot] : '';
}


// This is the code to show the ads and blocked by user role or page
function bbj_echo_ad( string $slot ): void {
    // Hide based on user role
    if ( bbj_user_has_role( 'updater' ) || bbj_user_has_role( 'administrator' ) || bbj_user_has_role( 'supporter' ) || bbj_user_has_role( 'comment_mod' ) || bbj_user_has_role( 'second_in_command' ) ) {
        return; 
    }

    // Hide based on page 
    if ( is_page( 'log-in' ) || is_admin() ) {
        return; 
    }

    $code = bbj_get_ad($slot);
    if ($code) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $code;
    }
}

function bbj_echo_ad_responsive( string $desktop_slot, string $mobile_slot = '', string $extra_class = '', bool $hide_mobile = false ): void {
    // keep your gating exactly the same
    if ( bbj_user_has_role('updater') || bbj_user_has_role('administrator') ||
         bbj_user_has_role('supporter') || bbj_user_has_role('comment_mod') ||
         bbj_user_has_role('second_in_command') || is_page('log-in') || is_admin() ) {
        return;
    }

    // Desktop (md+)
    $desktop_code = bbj_get_ad( $desktop_slot );
    if ( $desktop_code ) {
        echo '<div class="hidden md:block ' . esc_attr($extra_class) . '">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $desktop_code;
        echo '</div>';
    }

    // Mobile (< md): if mobile slot empty, fall back to desktop (unless hide)
    if ( ! $hide_mobile ) {
        $mobile_code = $mobile_slot ? bbj_get_ad( $mobile_slot ) : '';
        if ( ! $mobile_code ) {
            $mobile_code = $desktop_code; // fallback
        }
        if ( $mobile_code ) {
            echo '<div class="block md:hidden ' . esc_attr($extra_class) . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $mobile_code;
            echo '</div>';
        }
    }
}



// This is showing the code that is not blocked by user role or page.  Mostly meaning like google analyitcs
function bbj_echo_code( string $slot ): void {
    
    $code = bbj_get_ad($slot);
    if ( $code ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $code;
    }
}


function bbj_user_has_role( string $role ): bool {
    $user = wp_get_current_user();
    return in_array( $role, (array) $user->roles, true );
}



function bbj_time_tags( $post_id = null, $show_updated_text = false, $opts = [] ) {
    $post_id = $post_id ?: get_the_ID();

    // Options (sensible defaults)
    $defaults = [
        // Only add microdata itemprop=... on singular pages.
        'microdata'  => is_singular(),
        // Never print OG meta from here if AIOSEO is active (AIOSEO adds OG in <head>).
        'og'         => false,
        // Use the site timezone (ISO 8601 with offset, not Zulu).
        'use_site_tz'=> true,
        // Only show "Updated" if modified is > 5 minutes after published.
        'min_delta_s'=> 300,
        // Visible date format
        'format'     => get_option('date_format'),
    ];
    $o = wp_parse_args( $opts, $defaults );

    // Choose GMT flag based on desired timezone for machine-readable times
    $gmt_flag = $o['use_site_tz'] ? false : true;

    // ISO 8601 + visible strings
    $pub_iso  = get_post_time( DATE_W3C, $gmt_flag, $post_id );
    $mod_iso  = get_post_modified_time( DATE_W3C, $gmt_flag, $post_id );

    $pub_ts   = get_post_time( 'U', false, $post_id );
    $mod_ts   = get_post_modified_time( 'U', false, $post_id );

    $pub_disp = get_the_date( $o['format'], $post_id );
    $mod_disp = get_the_modified_date( $o['format'], $post_id );

    $show_updated = ( $mod_ts - $pub_ts ) > (int) $o['min_delta_s'];

    // Only add itemprop=... when microdata is appropriate
    $ip_pub = $o['microdata'] ? ' itemprop="datePublished"' : '';
    $ip_mod = $o['microdata'] ? ' itemprop="dateModified"'  : '';

    ob_start(); ?>
      <time class="published" datetime="<?= esc_attr($pub_iso) ?>"<?= $ip_pub ?>>
        <?= esc_html($pub_disp) ?>
      </time>
      <?php if ( $show_updated_text && $show_updated ) : ?>
        <span aria-hidden="true" class="px-2">•</span>
        <time class="updated" datetime="<?= esc_attr($mod_iso) ?>"<?= $ip_mod ?>>
          Updated <?= esc_html($mod_disp) ?>
        </time>
      <?php else : ?>
        <?php if ( $o['microdata'] ) : ?>
          <meta<?= $ip_mod ?> content="<?= esc_attr($mod_iso) ?>">
        <?php endif; ?>
      <?php endif; ?>

      <?php
      // Only add OG article times if explicitly requested AND AIOSEO isn't active.
      if ( ! bbj_is_aioseo_active() && ! empty($o['og']) ) : ?>
        <meta property="article:published_time" content="<?= esc_attr($pub_iso) ?>">
        <meta property="article:modified_time"  content="<?= esc_attr($mod_iso) ?>">
      <?php endif;

    return ob_get_clean();
}


// functions.php — replace the function body with the guard + dynamic @type
function bbj_print_article_jsonld( $post_id = null ) {
    // Let AIOSEO own schema on pages where it runs.
    if ( bbj_is_aioseo_active() || ! is_singular() ) {
        return;
    }

    $post_id   = $post_id ?: get_the_ID();
    $author_id = (int) get_post_field('post_author', $post_id);
    $image_url = wp_get_attachment_image_url( get_post_thumbnail_id($post_id), 'full' );

    // Use LiveBlogPosting for your feed CPT, BlogPosting for regular posts
    $ptype = get_post_type( $post_id );
    $schema_type = ( $ptype === 'live-feed-updates' ) ? 'LiveBlogPosting' : 'BlogPosting';

    $data = [
        '@context'          => 'https://schema.org',
        '@type'             => $schema_type,
        'mainEntityOfPage'  => get_permalink($post_id),
        'headline'          => get_the_title($post_id),
        'datePublished'     => get_post_time(DATE_W3C, false, $post_id),
        'dateModified'      => get_post_modified_time(DATE_W3C, false, $post_id),
        'author'            => [
            '@type' => 'Person',
            'name'  => get_the_author_meta('display_name', $author_id),
        ],
        'publisher'         => [
            '@type' => 'Organization',
            'name'  => get_bloginfo('name'),
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => get_site_icon_url() ?: ( get_template_directory_uri() . '/screenshot.png' ),
            ],
        ],
        'image'             => $image_url ?: null,
        'description'       => wp_strip_all_tags( get_the_excerpt($post_id) ),
    ];
    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . '</script>';
}



/**
 * Calculate player's age at the start of a season.
 *
 * @param string|null|DateTime $dob          Player date of birth (Y-m-d string or DateTime)
 * @param string|null|DateTime $season_start Season start date (Y-m-d string or DateTime)
 * @return int|null Player age in years, or null if missing/invalid input
 */
function age_calc_player_season($dob, $season_start) {
    if (empty($dob) || empty($season_start)) {
        return null;
    }

    try {
        // Normalize $dob
        if ($dob instanceof DateTime) {
            $dob_dt = $dob;
        } elseif (is_string($dob) && $dob !== '' && $dob !== '0000-00-00') {
            $dob_dt = new DateTime($dob);
        } else {
            return null;
        }

        // Normalize $season_start
        if ($season_start instanceof DateTime) {
            $season_dt = $season_start;
        } elseif (is_string($season_start) && $season_start !== '' && $season_start !== '0000-00-00') {
            $season_dt = new DateTime($season_start);
        } else {
            return null;
        }

        // Calculate age
        return $dob_dt->diff($season_dt)->y;
    } catch (Exception $e) {
        return null;
    }
}
