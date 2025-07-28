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


$all_players = $wpdb->get_results( "SELECT p.id, p.first_name, p.last_name FROM $player_table p 
  ORDER BY p.first_name ASC" );

$season_name = $season ? $season->full_name : '';
$season_start_date = $season ? $season->start_date : '';
$season_end_date = $season ? $season->end_date : '';

bbj_log3(print_r($season, true));
bbj_log3(print_r($season_players, true));


?> 

<div class="max-w-screen-xl mx-auto p-4 border rounded-lg bg-white shadow-md">
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
    <form action="" method="post">
      <input type="hidden" name="season_id" value="<?php echo esc_attr( $season_id ); ?>">
    
    <table class="table-auto border-collapse border border-gray-300 mb-4">
      <thead>
        <tr class="text-xs bg-slate-200">
          <th class="px-1 py-2">Name</th>
          <th class="px-1 py-2 w-32">Evicted</th>
          <th class="px-1 py-2 w-24"># HoH</th>
          <th class="px-1 py-2 w-24"># Veto</th>   
          <th class="px-1 py-2 w-24">Misc</th>
          <th class="px-1 py-2 w-24">Total Saved</th>
          <th class="px-1 py-2  w-24">Nom</th>
          <th class="px-1 py-2  w-24">H/N</th>
          <th class="px-1 py-2  w-24">Votes (R)</th>
          <th class="px-1 py-2  w-24">Veto Played</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ( $season_players as $player ) : ?>
          <?php bbj_log3(print_r($player, true)); ?>
          <?php $combined_name = esc_html( $player['first_name'] ) . ' ' . esc_html( $player['last_name'] ); ?>
          <tr>
            <td class="border px-4 py-2 text-sm"><?php echo esc_html( $combined_name ); ?></td>
            <td class="border px-4 py-2">
              <input
                type="date"
                name="evicted_date[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_evicted_date'] ?: '' ); ?>"
                min="<?php echo esc_attr( $season_start_date ); ?>"
                max="<?php echo esc_attr( $season_end_date ); ?>"
                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg"
              >
            </td>
            <td class="border px-4 py-2">
              <input
                type="number"
                name="hoh_count[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_hoh'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <td class="border px-4 py-2">
              <input
                type="number"
                name="veto_count[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_pov'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <td class="border px-4 py-2">
              <input
                type="number"
                name="misc_count[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_misc'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            
            <td class="border px-4 py-2">
              <input
                type="number"
                name="saved_count[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_saved'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <td class="border px-4 py-2">
              <input
                type="number"
                name="nom_count[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_nom'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <td class="border px-4 py-2">
              <input
                type="number"
                name="havenot_count[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_total_havenot'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <td class="border px-4 py-2">
              <input
                type="number"
                name="votes_received[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_votes_received'] ?: 0 ); ?>"
                min="0"
                class="v2-input-text"
              >
            </td>

            <td class="border px-4 py-2">
              <input
                type="number"
                name="veto_played[<?php echo intval( $player['id'] ); ?>]"
                value="<?php echo esc_attr( $player['bbj_veto_played'] ?: 0 ); ?>"
                class="v2-input-text"
              >
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit" class="v2-btn">Save Changes</button>
    </form>
    
    <form action="" class="mt-6">
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
          <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id ); ?>">
          <button type="submit" class="v2-btn">Add Player</button>
        </div>
      </div>
    </form>

</div>

<?php 

get_footer();
