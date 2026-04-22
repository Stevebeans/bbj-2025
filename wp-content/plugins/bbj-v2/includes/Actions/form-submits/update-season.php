<?php 


function bbj_v2_edit_season_info() {
    global $wpdb;

    // 1) Security & capability check
    if (
        empty( $_POST['edit_season_nonce'] ) ||
        ! wp_verify_nonce( $_POST['edit_season_nonce'], 'edit_season_action' ) ||
        ! current_user_can( 'edit_post', absint( $_POST['season_id'] ) )
    ) {
        wp_die( 'Permission check failed' );
    }

    // 2) Gather & sanitize input
    $season_id           = absint( $_POST['season_id'] );
    $season_name         = sanitize_text_field( $_POST['season_name'] ?? '' );
    $season_start_date   = sanitize_text_field( $_POST['season_start_date'] ?? '' );
    $season_end_date     = sanitize_text_field( $_POST['season_end_date'] ?? '' );
    $season_number       = sanitize_text_field( $_POST['season_number'] ?? '' );
    $season_abbreviation = sanitize_text_field( $_POST['season_abbreviation'] ?? '' );
    $season_winner       = absint( $_POST['season_winner'] ?? 0 );
    $season_runner_up    = absint( $_POST['season_runner_up'] ?? 0 );
    $season_afp          = absint( $_POST['season_afp'] ?? 0 );

    // 3) Update custom table
    $season_table = $wpdb->prefix . 'bbj_seasons';
    $data = [
        'full_name'    => $season_name,
        'start_date'   => $season_start_date,
        'end_date'     => $season_end_date,
        'season_number'=> $season_number,
        'abbreviation' => $season_abbreviation,
        'season_winner'=> $season_winner,
        'runner_up'    => $season_runner_up,
        'afp'          => $season_afp,
    ];
    $where = [ 'id' => $season_id ];
    $formats = [ '%s','%s','%s','%s','%s','%d','%d','%d' ];
    $where_format = [ '%d' ];

    $updated = $wpdb->update( $season_table, $data, $where, $formats, $where_format );

    if ( false === $updated ) {
        bbj_log3( "Failed to update season {$season_id}" );
    } else {
        bbj_log3( "Updated season {$season_id}" );
    }

    // 4) Sync the companion CPT post (title + publish if still draft).
    //    Look up the real post_id from the custom-table row — prior code
    //    was passing the custom-table PK as the post ID, which silently
    //    no-op'd.
    $season_row = bbj_v2_get_season_by_id( $season_id );
    $post_id    = $season_row ? (int) $season_row['post_id'] : 0;

    if ( $post_id > 0 ) {
        $post_update = [
            'ID'         => $post_id,
            'post_title' => $season_name,
        ];
        // Publish the draft on first meaningful save (user set a real name).
        $existing_status = get_post_status( $post_id );
        if ( $existing_status === 'draft' && $season_name !== '' ) {
            $post_update['post_status'] = 'publish';
        }
        wp_update_post( $post_update );
    }

    // Bust spoiler-bar cache only when we edited the currently-active season.
    $current_season_id = (int) get_option( 'bbj_v2_current_season', 0 );
    if ( $current_season_id === (int) $season_id ) {
        bbj_spoiler_bar_bust_cache( $season_id );
    }

    // 5) Redirect back with a success flag
    $redirect = wp_get_referer() ?: home_url();
    wp_safe_redirect( add_query_arg( 'updated', '1', $redirect ) );
    exit;
}

function bbj_v2_update_season() {
    global $wpdb;
    $link_table = $wpdb->prefix . 'bbj_v2_player_season';

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }

    if (
        empty( $_POST['add_player_nonce'] ) ||
        ! wp_verify_nonce( $_POST['add_player_nonce'], 'add_player_action' ) ||
        ! current_user_can( 'edit_post', absint( $_POST['season_id'] ) )
    ) {
        wp_die( 'Permission check failed' );
    }

    $season_id = absint( $_POST['season_id'] );

    
    // If Remove Clicked, remove player and exit 
    if ( ! empty( $_POST['remove_player'] ) && is_array( $_POST['remove_player'] ) ) {
        // There will be exactly one key: the clicked player's ID
        $player_ids = array_keys( $_POST['remove_player'] );
        $player_id  = absint( reset( $player_ids ) );

        $deleted = $wpdb->delete(
            $link_table,
            [ 'bbj_player' => $player_id, 'bbj_season' => $season_id ],
            [ '%d', '%d' ]
        );

        // optional logging
        bbj_log3( $deleted ? "Removed player $player_id from season $season_id"
                           : "Failed to remove player $player_id from season $season_id" );

        bbj_spoiler_bar_bust_cache( $season_id );

        $redirect = wp_get_referer() ?: admin_url( 'admin.php?page=bbj-v2-edit-season' );
        wp_safe_redirect( add_query_arg( [
            'removed'   => $deleted ? 1 : 0,
            'player_id' => $player_id,
            'season_id' => $season_id, // keep season selected after redirect
        ], $redirect ) );
        exit;
    }

    // pull in input data
    $evicted_date      = $_POST['evicted_date']      ?? [];
    $hoh_count         = $_POST['hoh_count']         ?? [];
    $veto_count        = $_POST['veto_count']        ?? [];
    $misc_count        = $_POST['misc_count']        ?? [];
    $saved_count       = $_POST['saved_count']       ?? [];
    $nom_count         = $_POST['nom_count']         ?? [];
    $havenot_count     = $_POST['havenot_count']     ?? [];
    $votes_received    = $_POST['votes_received']    ?? [];
    $veto_played       = $_POST['veto_played']       ?? [];

    // new fields
    $current_hoh       = $_POST['current_hoh']       ?? [];
    $current_pov       = $_POST['current_pov']       ?? [];
    $current_nom       = $_POST['current_nom']       ?? [];
    $current_havenot   = $_POST['current_havenot']   ?? [];
    $current_evicted   = $_POST['current_evicted']   ?? [];
    $current_misc      = $_POST['current_misc']      ?? [];
    $current_jury      = $_POST['current_jury']      ?? [];
    $current_safe      = $_POST['current_safe']      ?? [];
    $misc_notes        = $_POST['misc_notes']        ?? [];
    $finish_place      = $_POST['finish_place']      ?? [];

    // loop through each player and update their counts
    foreach ( $evicted_date as $player_id => $date ) {
        $player_id = absint( $player_id );

        // build data array
        $data = [
            'bbj_evicted_date'    => sanitize_text_field( $date ),
            'bbj_total_hoh'       => absint( $hoh_count[ $player_id ]         ?? 0 ),
            'bbj_total_pov'       => absint( $veto_count[ $player_id ]        ?? 0 ),
            'bbj_total_misc'      => absint( $misc_count[ $player_id ]        ?? 0 ),
            'bbj_total_saved'     => absint( $saved_count[ $player_id ]       ?? 0 ),
            'bbj_total_nom'       => absint( $nom_count[ $player_id ]         ?? 0 ),
            'bbj_total_havenot'   => absint( $havenot_count[ $player_id ]     ?? 0 ),
            'bbj_votes_received'  => absint( $votes_received[ $player_id ]    ?? 0 ),
            'bbj_veto_played'     => absint( $veto_played[ $player_id ]       ?? 0 ),
            // new checkbox fields
            'current_hoh'         => isset( $current_hoh[ $player_id ] )     ? 1 : 0,
            'current_pov'         => isset( $current_pov[ $player_id ] )     ? 1 : 0,
            'current_nom'         => isset( $current_nom[ $player_id ] )     ? 1 : 0,
            'current_havenot'     => isset( $current_havenot[ $player_id ] ) ? 1 : 0,
            'current_evicted'     => isset( $current_evicted[ $player_id ] ) ? 1 : 0,
            'current_misc'        => isset( $current_misc[ $player_id ] )    ? 1 : 0,
            'current_jury'        => isset( $current_jury[ $player_id ] )    ? 1 : 0,
            'current_safe'        => isset( $current_safe[ $player_id ] )    ? 1 : 0,
            'misc_notes'          => sanitize_text_field( $misc_notes[ $player_id ] ?? '' ),
            // NULL when blank or 0 so the sort doesn't treat "unset" as 1st place
            'bbj_finish_place'    => ( isset( $finish_place[ $player_id ] ) && (int) $finish_place[ $player_id ] > 0 )
                                       ? (int) $finish_place[ $player_id ]
                                       : null,
        ];

        $where = [
            'bbj_player' => $player_id,
            'bbj_season' => $season_id,
        ];

        // update the row
        $updated = $wpdb->update(
            $link_table,
            $data,
            $where,
            [
                '%s', // bbj_evicted_date
                '%d','%d','%d','%d','%d','%d','%d','%d', // original counts
                '%d','%d','%d','%d','%d','%d','%d','%d', // checkbox flags
                '%s', // misc_notes
                '%d'  // bbj_finish_place (NULL passes through as NULL via $wpdb)
            ],
            [ '%d', '%d' ]
        );

        if ( false === $updated ) {
            // handle error
            bbj_log3( "Failed to update player $player_id in season $season_id" );
        } else {
            bbj_log3( "Updated player $player_id in season $season_id" );
        }
    }

    // cache bust
    bbj_spoiler_bar_bust_cache( $season_id );

    // finished, redirecting
    $redirect = wp_get_referer() ?: home_url();
    wp_safe_redirect( add_query_arg( 'updated', '1', $redirect ) );
    exit;
}
