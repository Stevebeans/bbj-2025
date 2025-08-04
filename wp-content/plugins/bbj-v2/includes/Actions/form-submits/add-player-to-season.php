<?php
// Helpers/form-submits/add-player-to-season.php
function bbj_v2_add_player() {
    global $wpdb;
    $link_table = $wpdb->prefix . 'bbj_v2_player_season';
    
    // 1) Security
    if (
        empty( $_POST['add_player_nonce'] ) ||
        ! wp_verify_nonce( $_POST['add_player_nonce'], 'add_player_action' ) ||
        ! current_user_can( 'edit_post', absint( $_POST['season_id'] ) )
    ) {
        wp_die( 'Permission check failed' );
    }


    // 2) Sanitize + Dedupe
    $season_id = absint( $_POST['season_id'] );
    $player_id = absint( $_POST['new_player'] );
    $evicted_date = !empty($_POST['evicted_date']) ? sanitize_text_field( $_POST['evicted_date'] ) : '';
    $exists    = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$link_table} WHERE bbj_player = %d AND bbj_season = %d",
        $player_id,
        $season_id
    ) );

    if ( $player_id && ! $exists ) {
        $wpdb->insert(
            $link_table,
            [
                'bbj_player' => $player_id,
                'bbj_season' => $season_id,
                'bbj_evicted_date' => $evicted_date,
            ],
            [ '%d', '%d', '%s' ]
        );
    }

    // 3) Redirect back
    $redirect = wp_get_referer() ?: home_url();
    wp_safe_redirect( add_query_arg( 'added', '1', $redirect ) );
    exit;
}
