<?php

add_action( 'init', 'prefix_create_table' );
function prefix_create_table() {
  if ( ! class_exists( 'MB_Custom_Table_API' ) ) {
    return;
  }

  MB_Custom_Table_API::create( 'wp_bbj_player_season_results', array(
    'player_id'       => 'BIGINT(20) NOT NULL',
    'current_hoh'     => 'TINYINT NOT NULL',
    'current_pov'     => 'TINYINT NOT NULL',
    'current_jury'    => 'TINYINT NOT NULL',
    'current_nom'     => 'TINYINT NOT NULL',
    'current_evic'    => 'TINYINT NOT NULL',
    'current_winner'  => 'TINYINT NOT NULL',
    'current_second'  => 'TINYINT NOT NULL',
    'current_afp'     => 'TINYINT NOT NULL',   
    'evicted'         => 'DATE NOT NULL',
  ));


  MB_Custom_Table_API::create( 'bbj_player_season_stats', array(
    'hohwins'             => 'SMALLINT(20) NOT NULL',
    'povwins'             => 'SMALLINT(20) NOT NULL',    
    'miscwins'            => 'SMALLINT(20) NOT NULL',    
    'saved_block'         => 'SMALLINT(20) NOT NULL',    
    'nominated'           => 'SMALLINT(20) NOT NULL',    
    'evicted_date'        => 'DATE NOT NULL',    
    'place_finished'      => 'TEXT NOT NULL',
  ));


  MB_Custom_Table_API::create( 'bbj_geo', array(
    'address_street'              => 'TEXT NOT NULL',
    'locality'                    => 'TEXT NOT NULL',   
    'administrative_area_level_1' => 'TEXT NOT NULL',
    'lng'                         => 'TEXT NOT NULL',    
    'lat'                         => 'TEXT NOT NULL',  
    'address_map'                 => 'TEXT NOT NULL',  
  ));

  MB_Custom_Table_API::create( 'wp_bbj_players', array(
    'profile_picture'              => 'TEXT NOT NULL',
    'player_banner'                    => 'TEXT NOT NULL',   
    'right_side'              => 'TEXT NOT NULL',
    'date_of_birth'                         => 'DATE NOT NULL',    
    'occupation'                         => 'TEXT NOT NULL',  
    'facebook'                 => 'TEXT NOT NULL',  
    'instagram'                 => 'TEXT NOT NULL',  
    'twitter'                 => 'TEXT NOT NULL',  
    'tiktok'                 => 'TEXT NOT NULL',  
    'first_name'                 => 'TEXT NOT NULL',  
    'last_name'                 => 'TEXT NOT NULL',  
    'official_nickname'                 => 'TEXT NOT NULL',  
    'player_gender'                 => 'TEXT NOT NULL',  
  ));
}