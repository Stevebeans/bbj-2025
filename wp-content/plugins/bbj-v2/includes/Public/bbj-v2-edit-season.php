<?php 
global $wpdb;


// get season ID from POST or GET request
$season_id = isset($_GET['season_id']) ? intval($_GET['season_id']) : 0;


// look up season info 

$season = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . BBJ_V2_TABLE_SEASONS . " WHERE id = %d", $season_id ) );


$season_name = $season ? $season->full_name : '';
$season_start_date = $season ? $season->start_date : '';
$season_end_date = $season ? $season->end_date : '';
$season_number = $season ? $season->season_number : '';
$season_abbreviation = $season ? $season->abbreviation : '';
$season_winner = $season ? $season->season_winner : '';
$season_runner_up = $season ? $season->runner_up : '';
$season_afp = $season ? $season->afp : '';

$season_players = bbj_v2_get_season_players($season_id);
$all_players = bbj_v2_get_all_players('first_name', 'ASC');

bbj_log3(print_r($all_players, true));

bbj_log3(print_r($season_players, true));
?>

<div class="wrap bbj-admin">
    <h1>Edit <?php echo esc_html( $season->full_name ); ?></h1>

    <!-- Back Link -->
    <p><a href="<?php echo admin_url( 'admin.php?page=bbj-v2-seasons' ); ?>" class="button button-secondary">Back to Seasons</a></p>
    <p>Here you can edit the details of the selected season.</p>
    
 <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
      <h2>Update Season Players</h2>
      <?php wp_nonce_field( 'add_player_action', 'add_player_nonce' ); ?>
      <input type="hidden" name="season_id" value="<?php echo esc_attr( $season_id ); ?>">
      <input type="hidden" name="action" value="bbj_v2_update_season">
    <table class="bbj-table">
      <thead>
        <tr>
          <th colspan="10"></th>
          <th class=" text-sm bg-slate-200 border-l border-gray-300" colspan="8">Current Roles</th>
        </tr>
        <tr class="text-xs bg-slate-200">
          <th>Name</th>
          <th>Evicted</th>
          <th>HoH</th>
          <th># Veto</th>   
          <th>Misc</th>
          <th>Saved</th>
          <th>Nom</th>
          <th>H/N</th>
          <th>Votes (R)</th>
          <th>Played</th>
          <th>HoH</th>
          <th>PoV</th>
          <th>Nom</th>
          <th>H/N</th>
          <th>Evic</th>
          <th>Misc</th>
          <th>Jury</th>
          <th>Safe</th>  
          <th>Misc Type</th>
          <th>Actions</th>

        </tr>
      </thead>
      <tbody>
        <?php foreach ( $season_players as $player ) : ?>
       
          
          <tr>
            <td class="!text-left"><?php echo esc_html( $player['first_name'] ); ?></td>
            <td>
              <input
                type="date"
                name="evicted_date[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_evicted_date'] ?: '' ); ?>"
                min="<?php echo esc_attr( $season_start_date ); ?>"
                max="<?php echo esc_attr( $season_end_date ); ?>"
                class="bbj-input-date"
              >
            </td>
            <!-- Number of HoH Wins -->
            <td>
              <input
                type="number"
                name="hoh_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_hoh'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Number of Veto Wins -->
            <td>
              <input
                type="number"
                name="veto_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_pov'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Number of Misc Wins -->
            <td>
              <input
                type="number"
                name="misc_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_misc'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Number of Total Saved From Block -->
            <td>
              <input
                type="number"
                name="saved_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_saved'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Number of Nominations -->
            <td>
              <input
                type="number"
                name="nom_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_nom'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Number of Havenot Weeks -->
            <td>
              <input
                type="number"
                name="havenot_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_havenot'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Votes Received -->
            <td>
              <input
                type="number"
                name="votes_received[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_votes_received'] ?: 0 ); ?>"
                min="0"
                class="bbj-input-number"
              >
            </td>

            <!-- Number of Veto Played -->
            <td>
              <input
                type="number"
                name="veto_played[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_veto_played'] ?: 0 ); ?>"
                class="bbj-input-number"
              >
            </td>

            <!-- Current HoH -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_hoh[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_hoh'], 1 ); ?>
              ></div>              
            </td>

            <!-- Current PoV -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_pov[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_pov'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Nomination -->
            <td>  
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_nom[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_nom'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Havenot -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_havenot[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_havenot'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Evicted -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_evicted[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_evicted'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Misc -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_misc[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_misc'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Jury -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_jury[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_jury'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Safe -->
            <td>
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_safe[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_safe'], 1 ); ?>
              ></div>
            </td>

            <!-- Misc Type -->
            <td>
              <input
                type="text"
                name="misc_notes[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['misc_notes'] ?: '' ); ?>"
                class="bbj-input-text"
              >
            </td>

            <!-- Actions -->
            <td>
              <div class="v2-cc">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbj-v2-add-edit-player&method=edit&player_id=' . $player['bbj_player'] ) ); ?>" class="button button-secondary">Edit Player</a>
                <button type="submit" name="remove_player[<?php echo intval( $player['bbj_player'] ); ?>]" class="button button-danger">Remove</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
     <button type="submit" class="button button-primary">Save Changes</button>
    </form>
    
      <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
        <?php wp_nonce_field( 'add_player_action', 'add_player_nonce' ); ?>
        <input type="hidden" name="action"    value="bbj_v2_add_player">
        <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id ); ?>">
        <input type="hidden" name="evicted_date" value="<?php echo esc_attr( $initial_evicted_date ); ?>">
        <h2>Add Player</h2>
        <div class="flex gap-4">
          <div>
            <select name="new_player" class="v2-input-select">
              <option value="">Select a player</option>
              <?php foreach ( $all_players as $player ) : ?>
                <option value="<?php echo esc_attr( $player['id'] ); ?>">

                <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button type="submit" class="button button-primary">Add Player</button>

          </div>
        </div>
      </form>
      
      
      <!-- edit season section -->
     <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
      <input type="hidden" name="action" value="bbj_v2_edit_season_info">
      <?php wp_nonce_field( 'edit_season_action', 'edit_season_nonce' ); ?>
      <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id ); ?>">
      <h2>Edit Season</h2>
      <table class="bbj-table">
        <thead>
          <tr>
            <th>Season Name</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Season Number</th>
            <th>Abbreviation</th>
            <th>Winner</th>
            <th>Runner Up</th>
            <th>America's Favorite</th>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td><input type="text" name="season_name" id="season_name" value="<?php echo esc_attr($season_name ); ?>" class="bbj-input-text"></td>
            <td><input type="date" name="season_start_date" id="season_start_date" value="<?php echo esc_attr($season_start_date ); ?>" class="bbj-input-date"></td>
            <td><input type="date" name="season_end_date" id="season_end_date" value="<?php echo esc_attr($season_end_date ); ?>" class="bbj-input-date"></td>
            <td><input type="text" name="season_number" id="season_number" value="<?php echo esc_attr($season_number ); ?>" class="bbj-input-number"></td>
            <td><input type="text" name="season_abbreviation" id="season_abbreviation" value="<?php echo esc_attr($season_abbreviation ); ?>" class="bbj-input-number"></td>
            <td>
              <select name="season_winner" id="season_winner" class="bbj-input-select">
            <option value="">Select Winner</option>
            <?php foreach ( $season_players as $player ) : ?>
              
              <option value="<?php echo esc_attr( $player['id'] ); ?>" <?php selected( $season_winner, $player['id'] ); ?>>
                <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
            </td>
            <td>
              <select name="season_runner_up" id="season_runner_up" class="bbj-input-select">
                <option value="">Select Runner Up</option>
                <?php foreach ( $season_players as $player ) : ?> 
                  <option value="<?php echo esc_attr( $player['id'] ); ?>" <?php selected( $season_runner_up, $player['id'] ); ?>>
                    <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select name="season_afp" id="season_afp" class="bbj-input-select">
                <option value="">Select AFP</option>
                <?php foreach ( $season_players as $player ) : ?>
                  <option value="<?php echo esc_attr( $player['id'] ); ?>" <?php selected( $season_afp, $player['id'] ); ?>>
                    <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        </tbody>
      </table>

     
        <div>
          <button type="submit" class="button button-primary">Save Season</button>
        </div>
     </form>

</div>


