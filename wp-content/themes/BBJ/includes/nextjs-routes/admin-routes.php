<?php 


/**
 * Permission callback:
 * 1) Verifies the WP REST nonce.
 * 2) Checks if the user is logged in and has 'administrator' OR 'player_updater' role.
 */
function my_check_permissions( WP_REST_Request $request ) {

    // 1. Verify the nonce from the request header 'X-WP-Nonce' (default WP convention)
    $nonce = $request->get_header('X-WP-Nonce');
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error(
            'rest_forbidden',
            __( 'Invalid or expired nonce.', 'textdomain' ),
            array( 'status' => 403 )
        );
    }

    // 2. Ensure user is logged in
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'rest_unauthorized',
            __( 'You must be logged in.', 'textdomain' ),
            array( 'status' => 401 )
        );
    }

    // 3. Check user roles
    $user = wp_get_current_user();
    $allowed_roles = array( 'administrator', 'player_updater' );

    // If user has at least one of these roles, grant access
    if ( array_intersect( $allowed_roles, (array) $user->roles ) ) {
        return true;
    } else {
        return new WP_Error(
            'rest_forbidden',
            __( 'You do not have the necessary role to access this endpoint.', 'textdomain' ),
            array( 'status' => 403 )
        );
    }
}

/**
 * Example callback function for your REST endpoint.
 */
function my_custom_callback( WP_REST_Request $request ) {
    // Handle your logic here.
    // For demonstration, we'll just return a success message.
    return array( 'message' => 'You accessed this route successfully!' );
}

/**
 * Register the route.
 */
add_action( 'rest_api_init', function () {
    register_rest_route(
        'bbj/v3',               
        '/get_players',             
        array(
            array(
                'methods'             => 'GET',        
                'callback'            => 'get_player_route'
            ),
        )
    );

    register_rest_route(
        'bbj/v3',               
        '/get_seasons',             
        array(
            array(
                'methods'             => 'GET',        
                'callback'            => 'get_season_route'
            ),
        )
    );

    register_rest_route(
        'bbj/v3',               
        '/get_player_season',             
        array(
            array(
                'methods'             => 'GET',        
                'callback'            => 'get_player_season_route'
            ),
        )
    );

    // post requests
    register_rest_route(
        'bbj/v3',               
        '/add_player_to_season',             
        array(
            array(
                'methods'             => 'POST',        
                'callback'            => 'handle_post_request',
                'permission_callback' => 'my_check_permissions'
            ),
        )
    );
} );




function get_player_route() {
 include_once (BBJ_ADMIN_ROUTES . '/get-players.php');
  return get_players();
}


function get_player_season_route() {
    include_once (BBJ_ADMIN_ROUTES . '/get-player-season.php');
    return player_season_rel();
}

function get_season_route() {
    bbj_log3(print_r('get seasons', true));
    include_once (BBJ_ADMIN_ROUTES . '/get-seasons.php');
    return get_seasons();
}


//
// Handle POST requests
//
//

function add_player_route($request) {

    include_once (BBJ_ADMIN_ROUTES . '/add-player.php');
    return nxt_add_player_to_season($request);
}

