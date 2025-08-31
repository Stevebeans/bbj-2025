<?php
/**
 * Plugin Name:     BBJ v2
 * Plugin URI:      https://yourdomain.com/bbj-v2
 * Description:     Version 2 of the BBJ plugin.
 * Version:         1.0.0
 * Author:          Steve Beans
 * Text Domain:     bbj-v2
 * Domain Path:     /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin version
if ( ! defined( 'BBJ_V2_VERSION' ) ) {
    define( 'BBJ_V2_VERSION', '1.0.0' );
}

define( 'BBJ_V2_PATH',      plugin_dir_path( __FILE__ ) );
define( 'BBJ_V2_URL',       plugin_dir_url(  __FILE__ ) );
define( 'BBJ_V2_INCLUDES',  BBJ_V2_PATH . 'includes/' );
define( 'BBJ_V2_PUBLIC',    BBJ_V2_INCLUDES . 'Public/' );
define ("BBJ_V2_ROUTES",    BBJ_V2_INCLUDES . 'Routes/');

// global table variables 
define( 'BBJ_V2_TABLE_PLAYERS', 'wp_bbj_players' );
define( 'BBJ_V2_TABLE_SEASONS', 'wp_bbj_seasons' );
define( 'BBJ_V2_TABLE_LINKS',   'wp_bbj_v2_player_season' );
define( 'BBJ_V2_TABLE_GEO',   'wp_bbj_geo' );
define( 'GOOGLE_API_KEY', 'AIzaSyBLQ2ii4adt-2cQzOtoPXxjWTxjEUb6Tsw' ); 

// Media Sizes 
add_action( 'after_setup_theme', 'bbj_register_player_image_sizes' );
function bbj_register_player_image_sizes() {
    // hard crops
    add_image_size( 'bbj_v2_spoiler_bar',    100, 100, true );
    add_image_size( 'bbj_v2_profile_image',  375, 375, true );

    add_image_size( 'bbj_v2_index_hero',  1350, 450, array( 'center', 'top' ) );
    add_image_size( 'bbj_v2_index_mobile', 400, 333, array( 'center', 'top' ) );
    add_image_size ( 'bbj_hot_thumbnail', 128, 80, true );
    add_image_size("featured-thumbnail", 400, 200, true);  
        // Old, need to review
        add_theme_support("title-tag");
        add_theme_support("post-thumbnails");
        add_theme_support("block-templates");

              
        add_image_size('featured-feed-update', 700, 0, false);
        add_image_size("featured-image-header", 1600, 500, true);
        add_image_size("player-banner", 1200, 350, true);
        add_image_size("profile-picture", 200, 200, true);
        add_image_size("prof-pic-lg", 375, 375, true);
        add_image_size("tiny", 50, 50, true);
}


// Action variables 
define ('BBJ_ACTION_LIST', BBJ_V2_INCLUDES . 'Actions/' );
define ('BBJ_FORM_SUBMITS', BBJ_ACTION_LIST . 'form-submits/');

// Activation hook
function bbj_v2_activate() {
   // Reserved for future use, e.g. creating custom tables, flushing rewrite rules
}
register_activation_hook( __FILE__, 'bbj_v2_activate' );

add_action( 'admin_enqueue_scripts', 'bbj_v2_enqueue_admin_assets' );
function bbj_v2_enqueue_admin_assets( $hook ) {
    // only load on our BBJ pages:
    $allowed = [
        'toplevel_page_bbj-main',
        'bbj_page_bbj-v2-settings',
        'bbj_page_bbj-v2-seasons',
        'bbj_page_bbj-v2-edit-season',
        'bbj_page_bbj-v2-add-edit-player'
    ];
    if ( ! in_array( $hook, $allowed, true ) ) {
        return;
    }

    $ver = defined( 'BBJ_V2_VERSION' ) ? BBJ_V2_VERSION : false;



    // enqueue common admin styles and scripts
    wp_enqueue_style(
        'bbj-v2-admin-css',
        BBJ_V2_URL . 'build/index.css',
        [],
        $ver
    );
    wp_enqueue_script(
        'bbj-v2-admin-js',
        BBJ_V2_URL . 'build/index.js',
        [ 'wp-element' ],
        $ver,
        true
    );  
    
    /**
     * 
     * Enqueue Scripts and Styles for Specific Pages
     * 
     * 
     */

    // Enqueue for Add/Edit Player page
    if ( 'bbj_page_bbj-v2-add-edit-player' === $hook ) {
        wp_enqueue_script(
            'bbj-v2-admin-player-js',
            BBJ_V2_URL . 'build/player-admin.js',
            [ 'wp-element' ],
            $ver,
            true
        );

        // enqueue Google Maps API
        wp_enqueue_script(
            'bbj-v2-google-maps',
            'https://maps.googleapis.com/maps/api/js?key=' . GOOGLE_API_KEY . '&libraries=places',
            [],
            null,
            true
        );
    }


}


// Deactivation hook
function bbj_v2_deactivate() {
    // Reserved for future use, e.g. cleanup transient data, flushing rewrite rules
}
register_deactivation_hook( __FILE__, 'bbj_v2_deactivate' );

// Include the main plugin file
require_once BBJ_V2_PATH . 'includes/bbj-plugin.php';

// Registration hook for creating database tables
register_activation_hook( __FILE__, 'bbj_create_player_season_table' );
register_activation_hook( __FILE__, 'bbj_create_players_table' );
register_activation_hook( __FILE__, 'bbj_create_seasons_table' );

add_filter( 'aioseo_schema_output', function ( $schema ) {
	if ( ! ( is_front_page() || is_home() ) ) {
		return $schema;
	}

	// If AIOSEO returns a @graph structure
	if ( isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ) {
		foreach ( $schema['@graph'] as $i => $node ) {
			$types = [];
			if ( ! empty( $node['@type'] ) ) {
				$types = is_array( $node['@type'] ) ? $node['@type'] : [ $node['@type'] ];
				$types = array_map( 'strtolower', $types );
			}
			if ( array_intersect( $types, [ 'article', 'blogposting', 'newsarticle' ] ) ) {
				unset( $schema['@graph'][ $i ]['datePublished'], $schema['@graph'][ $i ]['dateModified'] );
			}
		}
		return $schema;
	}

	// Fallback if it’s an array of nodes
	foreach ( $schema as $i => $node ) {
		if ( ! is_array( $node ) ) { continue; }
		$type = ! empty( $node['@type'] )
			? ( is_array( $node['@type'] ) ? strtolower( reset( $node['@type'] ) ) : strtolower( $node['@type'] ) )
			: '';
		if ( in_array( $type, [ 'article', 'blogposting', 'newsarticle' ], true ) ) {
			unset( $schema[ $i ]['datePublished'], $schema[ $i ]['dateModified'] );
		}
	}
	return $schema;
} );


// Show an "Open in your browser" banner inside in‑app browsers (FB, IG, etc).
add_action('wp_body_open', function () { ?>
  <div id="iab-banner" style="display:none;position:sticky;top:0;z-index:9999;background:#222;color:#fff;padding:8px;text-align:center;font-size:14px;">
    You’re viewing this inside an in‑app browser. Logins may not stick.
    <a id="iab-open" href="" style="text-decoration:underline;color:#fff;">Open in your browser</a>
  </div>
  <script>
  (function(){
    var ua = navigator.userAgent||'';
    var inApp = /(FBAN|FBAV|FB_IAB|Instagram|Twitter|LinkedInApp|Line|Snapchat|WhatsApp|Pinterest)/i.test(ua);
    if(!inApp) return;
    var banner=document.getElementById('iab-banner'); if(banner){banner.style.display='block';}
    var a=document.getElementById('iab-open'); if(!a) return;
    var url=window.location.href;
    if(/Android/i.test(ua)){
      a.href='intent://' + url.replace(/^https?:\/\//,'') + '#Intent;scheme=https;package=com.android.chrome;end';
    } else {
      a.href=url; // iOS will show "Open in Safari" from the share menu; this keeps the URL handy.
    }
  })();
  </script>
<?php });


// Extend login cookie lifetime: 14 days normal, 30 days if "Remember Me".
add_filter('auth_cookie_expiration', function ($length, $user_id, $remember) {
  return $remember ? 30 * DAY_IN_SECONDS : 14 * DAY_IN_SECONDS;
}, 10, 3);
