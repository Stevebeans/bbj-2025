<?php
/**
 * Template Name: Edit Season
 */

get_header();

// 1. Grab the season ID from the query string, e.g. ?season=60026
$season_id = isset( $_GET['season'] ) 
    ? absint( $_GET['season'] ) 
    : 0;

if ( ! $season_id ) {
    echo '<p>Please specify a season ID in the URL, like <code>?season=60026</code>.</p>';
    get_footer();
    return;
}

// 2. Permission check
if ( ! current_user_can( 'edit_post', $season_id ) ) {
    wp_die( 'Sorry, you don’t have permission to edit this season.' );
}

// Get season information 

global $wpdb;
$season_table = $wpdb->prefix . 'bbj_seasons';
$player_table = $wpdb->prefix . 'bbj_players';
$link_table = $wpdb->prefix . 'bbj_v2_player_season';



$season = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $season_table WHERE id = %d", $season_id ) );

// get players for the current season using the link table
if ( ! $season ) {
    echo '<p>Season not found.</p>';
    get_footer();
    return;
}
$season_players = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT 
            p.id, 
            p.first_name, 
            p.last_name, 
            l.* 
        FROM {$player_table} p
        INNER JOIN {$link_table} l 
            ON p.id = l.bbj_player
        WHERE l.bbj_season = %d
        ORDER BY l.bbj_evicted_date ASC",
        $season_id
    ),
    ARRAY_A
);




$all_players = $wpdb->get_results( "SELECT p.* FROM $player_table p 
  ORDER BY p.first_name ASC" );

$season_name = $season ? $season->full_name : '';
$season_start_date = $season ? $season->start_date : '';
$season_end_date = $season ? $season->end_date : '';
$season_number = $season ? $season->season_number : '';
$season_abbreviation = $season ? $season->abbreviation : '';
$season_winner = $season ? $season->season_winner : '';
$season_runner_up = $season ? $season->runner_up : '';
$season_afp = $season ? $season->afp : '';

// create an evicted date of the 'end_date' that will be used for any new player added. However, if there is no end date added yet due to CBS not giving out the date, then use the start date
$initial_evicted_date = $season_end_date ?: $season_start_date;


?> 

<div class="max-w-screen-2xl mx-auto p-4 border rounded-lg bg-white shadow-md">
    <h1 class="text-2xl font-bold mb-2">Edit <?= $season_name?></h1>

    <div class="mb-4"><a href="/bigbrother-seasons/">Return to seasons</a></div>

    <?php
    // 3. Fetch the season data
    $season = get_post( $season_id );

    if ( ! $season || $season->post_type !== 'bigbrother-seasons' ) {
        echo '<p>Season not found or invalid season type.</p>';
        get_footer();
        return;
    }

    // 4. Display the edit form
    ?>
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" >
      <?php wp_nonce_field( 'add_player_action', 'add_player_nonce' ); ?>
      <input type="hidden" name="season_id" value="<?php echo esc_attr( $season_id ); ?>">
      <input type="hidden" name="action" value="bbj_v2_update_season">
    
    <table class="table-auto w-full border-collapse border border-gray-300 mb-4">
      <thead>
        <tr>
          <th class="p-1" colspan="10"></th>
          <th class="p-1 text-sm bg-slate-200 border-l border-gray-300" colspan="8">Current Roles</th>
        </tr>
        <tr class="text-xs bg-slate-200">
          <th class="p-1">Name</th>
          <th class="p-1 w-[100px]">Evicted</th>
          <th class="p-1 w-[70px]">HoH</th>
          <th class="p-1 w-[70px]"># Veto</th>   
          <th class="p-1 w-[70px]">Misc</th>
          <th class="p-1 w-[70px]">Saved</th>
          <th class="p-1 w-[70px]">Nom</th>
          <th class="p-1 w-[70px]">H/N</th>
          <th class="p-1 w-[70px]">Votes (R)</th>
          <th class="p-1 w-[70px]">Played</th>
          <th class="p-1 w-[50px]">HoH</th>
          <th class="p-1 w-[50px]">PoV</th>
          <th class="p-1 w-[50px]">Nom</th>
          <th class="p-1 w-[50px]">H/N</th>
          <th class="p-1 w-[50px]">Evic</th>
          <th class="p-1 w-[50px]">Misc</th>
          <th class="p-1 w-[50px]">Jury</th>
          <th class="p-1 w-[50px]">Safe</th>  
          <th class="p-1 w-[150px]">Misc Type</th>

        </tr>
      </thead>

      <tbody>
        <?php foreach ( $season_players as $player ) : ?>
          
          <?php $combined_name = esc_html( $player['first_name'] ) . ' ' . esc_html( $player['last_name'] ); ?>
          <tr>
            <td class="border p-1  text-sm"><?php echo esc_html( $player['first_name'] ); ?></td>


            <td class="v2-table-cell-edit-player">
              <input
                type="date"
                name="evicted_date[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_evicted_date'] ?: '' ); ?>"
                min="<?php echo esc_attr( $season_start_date ); ?>"
                max="<?php echo esc_attr( $season_end_date ); ?>"
                class="v2-input-date"
              >
            </td>
            <!-- Number of HoH Wins -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="hoh_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_hoh'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Number of Veto Wins -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="veto_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_pov'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Number of Misc Wins -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="misc_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_misc'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Number of Total Saved From Block -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="saved_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_saved'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Number of Nominations -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="nom_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_nom'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Number of Havenot Weeks -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="havenot_count[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_havenot'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Votes Received -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="votes_received[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_votes_received'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <!-- Number of Veto Played -->
            <td class="v2-table-cell-edit-player">
              <input
                type="number"
                name="veto_played[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_veto_played'] ?: 0 ); ?>"
                class="v2-input-text"
              >
            </td>

            <!-- Current HoH -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_hoh[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_hoh'], 1 ); ?>
              ></div>              
            </td>

            <!-- Current PoV -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_pov[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_pov'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Nomination -->
            <td class="v2-table-cell-edit-player">  
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_nom[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_nom'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Havenot -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_havenot[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_havenot'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Evicted -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_evicted[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_evicted'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Misc -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_misc[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_misc'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Jury -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_jury[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_jury'], 1 ); ?>
              ></div>
            </td>

            <!-- Current Safe -->
            <td class="v2-table-cell-edit-player">
              <div class="v2-cc"><input
                type="checkbox"
                class="v2-input-checkbox"
                name="current_safe[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="1"
                <?php checked( $player['current_safe'], 1 ); ?>
              ></div>
            </td>

            <!-- Misc Type -->
            <td class="v2-table-cell-edit-player">
              <input
                type="text"
                name="misc_notes[<?php echo intval( $player['bbj_player'] ); ?>]"
                value="<?php echo esc_attr( $player['misc_notes'] ?: '' ); ?>"
                class="v2-input-text text-left"
              >
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit" class="v2-btn">Save Changes</button>
    </form>
    
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="mt-6">
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
              <option value="<?php echo esc_attr( $player->id ); ?>">
      
              <?php echo esc_html( $player->first_name . ' ' . $player->last_name ); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit" class="v2-btn">Add Player</button>
        </div>
      </div>
    </form>


    <!-- edit season section -->
     <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="my-4 border p-4 rounded-lg bg-gray-50" method="post">
      <input type="hidden" name="action" value="bbj_v2_edit_season_info">
      <?php wp_nonce_field( 'edit_season_action', 'edit_season_nonce' ); ?>
      <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id ); ?>">
      <h2>Edit Season</h2>
      <div class="flex gap-4">
        <div>
          <label for="season_name" class="text-sm">Season Name</label>
          <input type="text" name="season_name" id="season_name" value="<?php echo esc_attr($season_name ); ?>" class="v2-input-text">
        </div>
        <div>
          <label for="season_year" class="text-sm">Start Date</label>
          <input type="date" name="season_start_date" id="season_start_date" value="<?php echo esc_attr($season_start_date ); ?>" class="v2-input-date">
        </div>
        <div>
          <label for="season_end_date" class="text-sm">End Date</label>
          <input type="date" name="season_end_date" id="season_end_date" value="<?php echo esc_attr($season_end_date ); ?>" class="v2-input-date">
        </div>
        <div class="w-[75px]">
          <label for="season_number" class="text-sm">Number</label>
          <input type="text" name="season_number" id="season_number" value="<?php echo esc_attr($season_number ); ?>" class="v2-input-text">
        </div>
        <div class="w-[75px]">
          <label for="season_abbreviation" class="text-sm">Abbv</label>
          <input type="text" name="season_abbreviation" id="season_abbreviation" value="<?php echo esc_attr($season_abbreviation ); ?>" class="v2-input-text">
        </div>
        <!-- Winner Dropdown -->
         <div>
          <label for="season_winner" class="text-sm ">Winner</label>
          <select name="season_winner" id="season_winner" class="v2-input-select">
            <option value="">Select Winner</option>
            <?php foreach ( $season_players as $player ) : ?>
              
              <option value="<?php echo esc_attr( $player['id'] ); ?>" <?php selected( $season_winner, $player['id'] ); ?>>
                <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
         </div>
        <!-- Runner Up Dropdown -->
         <div>
          <label for="season_runner_up" class="text-sm">Runner Up</label>
          <select name="season_runner_up" id="season_runner_up" class="v2-input-select">
            <option value="">Select Runner Up</option>
            <?php foreach ( $season_players as $player ) : ?>
              <option value="<?php echo esc_attr( $player['id'] ); ?>" <?php selected( $season_runner_up, $player['id'] ); ?>>
                <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- AFP Dropdown -->
         <div>
          <label for="season_afp" class="text-sm">America's Favorite</label>
          <select name="season_afp" id="season_afp" class="v2-input-select">
            <option value="">Select AFP</option>
            <?php foreach ( $season_players as $player ) : ?>
              <option value="<?php echo esc_attr( $player['id'] ); ?>" <?php selected( $season_afp, $player['id'] ); ?>>
                <?php echo esc_html( $player['first_name'] . ' ' . $player['last_name'] ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
      </div>
        <div>
          <button type="submit" class="v2-btn my-2">Save Season</button>
        </div>
     </form>

</div>

<?php 

get_footer();
