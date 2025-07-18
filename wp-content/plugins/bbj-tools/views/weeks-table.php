<?php 

// Path: views\weeks-table.php


function edit_weeks_table($season, $week, $seasonName, $weekID) {
    global $wpdb;

    $weeks_table = $wpdb->prefix . 'bbj_weeks';
    $season_table = $wpdb->prefix . 'bbj_seasons';

    $weeks = $wpdb->get_results("SELECT * FROM $weeks_table WHERE season_id = $season ORDER BY week_num");
    $season_dates = $wpdb->get_results("SELECT start_date, end_date FROM $season_table WHERE ID = $season");

    $seasonStart = $season_dates[0]->start_date;
    $seasonEnd = $season_dates[0]->end_date;

    // bbj_log2(print_r($season_dates, true));

    // bbj_log2('week' . $week);
    // bbj_log2('weekID' . $weekID);
    



    $query = $wpdb->prepare("
    SELECT weeks.*, players.first_name, players.last_name, 
           weeks_table.start_date, weeks_table.end_date, 
           play_season_rel.evict_date
    FROM {$wpdb->prefix}bbj_weeks_players AS weeks
    LEFT JOIN {$wpdb->prefix}bbj_players AS players ON weeks.player_id = players.ID
    LEFT JOIN {$wpdb->prefix}bbj_weeks AS weeks_table ON weeks.week_id = weeks_table.id
    LEFT JOIN {$wpdb->prefix}bbj_play_season_rel AS play_season_rel ON weeks.player_id = play_season_rel.player_id AND weeks.season_id = play_season_rel.season_id
    WHERE weeks.week_id = %d
    ", $weekID);




    $results = $wpdb->get_results($query);

    //bbj_log2(print_r($results, true));

    $noms = $wpdb->prepare("
    SELECT weeks.*, players.first_name, players.last_name 
    FROM {$wpdb->prefix}bbj_weeks_players AS weeks
    LEFT JOIN {$wpdb->prefix}bbj_players AS players ON player_id = players.ID
    WHERE week_id = %d AND nom = true
    ", $weekID);

    $current_noms = $wpdb->get_results($noms);


    function generate_nom_options($noms, $player) {


        //bbj_log2('wateber');
    // bbj_log2(print_r($player, true));
        $nom_options = '<option value="0">No Vote</option>';
        foreach ($noms as $nom) {
            //bbj_log2('nom');
            //bbj_log2(print_r($nom, true));
            $selected = ($nom->player_id == $player->voted_for ? 'selected' : '');
            // $selected = ($nom->player_id == $player_id && $nom->voted_for == $player_id) ? 'selected' : '';
            $nom_options .= '<option value="' . $nom->player_id . '" ' . $selected . '>' . $nom->first_name . ' ' . $nom->last_name . '</option>';
        }
        return $nom_options;
    }




$rem_players = $wpdb->prepare("
SELECT weeks.*, players.first_name, players.last_name
FROM {$wpdb->prefix}bbj_weeks_players AS weeks
LEFT JOIN {$wpdb->prefix}bbj_players AS players ON player_id = players.ID
LEFT JOIN {$wpdb->prefix}bbj_play_season_rel AS seasons ON players.ID = seasons.player_id
WHERE week_id = %d AND seasons.evicted = false AND seasons.jury = false

", $weekID);

$rem_play = $wpdb->get_results($rem_players);


$rem_jury = $wpdb->prepare("
SELECT weeks.*, players.first_name, players.last_name
FROM wp_bbj_weeks_players AS weeks
LEFT JOIN wp_bbj_players AS players ON player_id = players.ID
LEFT JOIN wp_bbj_play_season_rel AS seasons ON players.ID = seasons.player_id
WHERE week_id = %d AND (seasons.evicted = FALSE OR seasons.jury = TRUE)
", $weekID);

$remaining_jury = $wpdb->get_results($rem_jury);

// bbj_log2(print_r($remaining_jury, true));

// bbj_log2(print_r($rem_jury, true));

$win_options = '<option value="0">No Vote</option>';
foreach ($rem_play as $result) {
    $win_options .= '<option value="' . $result->player_id . '">' . $result->first_name . ' ' . $result->last_name . '</option>';
}

    ?>



    <h2 class="admin-subhead"><?= $seasonName?>  - Week <?= $week ?> Table</h2>



    <!-- Output the table header -->
    <form method="post">
<table class="w-full border bbj-admin-table">
  <thead>
    <tr>
    <th style="width: 20%;">Name</th>
        
      <th class="bbj-center-td">Active</th>
      <th class="bbj-center-td">HOH</th>
      <th class="bbj-center-td">POV</th>
      <th class="bbj-center-td">Nominated</th>
      <th class="bbj-center-td">Saved</th>
      <th class="bbj-center-td">Played in Veto</th>
      <th class="bbj-center-td">Misc Comp Win</th>
      <th class="bbj-center-td">Evicted</th>
      <th class="bbj-center-td">VOTE TO EVICT</th>
      <th class="bbj-center-td">VOTE TO WIN</th>
    </tr>
  </thead>
  <tbody>
    
    <!-- Loop through the results and output each row -->

  
        <?php foreach ($results as $result): ?>

            <?php $active = evicted_check($result->evict_date, $result->end_date) ?>
            
            <?php //bbj_log2(print_r($result, 1)); ?>
            <tr>
      <td><?= $result->first_name ?> <?= $result->last_name ?></td>

        <td class="bbj-center-td"><input type="checkbox" name="active[]" data-active="<?= $active ? 1 : 0 ?>" value="<?= $result->player_id ?>" <?= $result->active ? 'checked' : '' ?>>
    </td>
      
      <td class="bbj-center-td">
        <input type="checkbox" name="hoh[]" value="<?= $result->player_id ?>" <?= $result->hoh ? 'checked' : '' ?>>
        </td>
      <td class="bbj-center-td"><input type="checkbox" name="pov[]" value="<?= $result->player_id ?>" <?= $result->pov ? 'checked' : '' ?>></td>
      <td class="bbj-center-td"><input type="checkbox" name="nom[]" value="<?= $result->player_id ?>" <?= $result->nom ? 'checked' : '' ?>></td>
      <td class="bbj-center-td"><input type="checkbox" name="saved[]" value="<?= $result->player_id ?>" <?= $result->saved ? 'checked' : '' ?>></td>
      <td class="bbj-center-td"><input type="checkbox" name="veto_played[]" value="<?= $result->player_id ?>" <?= $result->veto_played ? 'checked' : '' ?>></td>
      <td class="bbj-center-td"><input type="checkbox" name="misc_comp[]" value="<?= $result->player_id ?>" <?= $result->misc_comp ? 'checked' : '' ?>></td>
      <td class="bbj-center-td"><input type="checkbox" name="evicted[]" value="<?= $result->player_id ?>" <?= $result->evicted ? 'checked' : '' ?>></td>
      <td class="bbj-center-td">
      <select name="voted_for[]">
            <?= generate_nom_options($current_noms, $result) ?>
        </select>

        </td>
        <td class="bbj-center-td">
            <select name="voted_to_win[]">
                <?= $win_options ?>
            </select>
        </td>
        </tr>

        <input type="hidden" name="playerID[]" value="<?= $result->player_id ?>">
        <?php endforeach; ?>


    
    
  </tbody>
</table>

<button id="active-fill">Fill in active</button>

<input type="hidden" name="week_id" value="<?= $weekID ?>">
<button type="submit" name="update_table" class="btn-primary">Update</button>
</form>
<br />
<br />
<br />
<input type="hidden" name="week_id" value="<?= $weekID ?>">
<a href="/wp-admin/admin.php?page=bbj-tools-edit-player-seasons&season=<?= $season ?>&method=weeks">Go back to weeks</a> <br />
<a href="/wp-admin/admin.php?page=bbj-tools-edit-player-seasons&season=<?= $season ?>&method=edit">Go back to editing <b><?= $seasonName ?></b></a>

<?php 

if (isset($_POST['update_table'])) {
    global $wpdb;

    $week_id = $_POST['week_id'];

    // bbj_log2('POST DATA');
    // bbj_log2(print_r($_POST, true));

    $player_id = isset($_POST['playerID']) ? $_POST['playerID'] : array();
    $active = isset($_POST['active']) ? $_POST['active'] : array();
    $hoh = isset($_POST['hoh']) ? $_POST['hoh'] : array();
    $pov = isset($_POST['pov']) ? $_POST['pov'] : array();
    $nom = isset($_POST['nom']) ? $_POST['nom'] : array();
    $saved = isset($_POST['saved']) ? $_POST['saved'] : array();
    $play_veto = isset($_POST['veto_played']) ? $_POST['veto_played'] : array();
    $evicted = isset($_POST['evicted']) ? $_POST['evicted'] : array();
    $misc_comp = isset($_POST['misc_comp']) ? $_POST['misc_comp'] : array();
    $voted_for = isset($_POST['voted_for']) ? $_POST['voted_for'] : array();


    //bbj_log2('variables');
    //bbj_log2('hoh' . print_r($voted_for, true));

//     bbj_log2('variables');
//      bbj_log2('hoh' . print_r($hoh, true));
//     // bbj_log2('voted for' . print_r($voted_for, true));

//     // $all_players = array_merge($hoh, $pov, $nom, $saved, $evicted);
// // Set unchecked checkboxes to 0 in the database



foreach ($player_id as $player) {

    $active_value = in_array($player, $active) ? 1 : 0;
    $hoh_value = in_array($player, $hoh) ? 1 : 0;
    $pov_value = in_array($player, $pov) ? 1 : 0;
    $nom_value = in_array($player, $nom) ? 1 : 0;
    $saved_value = in_array($player, $saved) ? 1 : 0;
    $played_veto = in_array($player, $play_veto) ? 1 : 0;
    $evicted_value = in_array($player, $evicted) ? 1 : 0;
    $misc_comp_value = in_array($player, $misc_comp) ? 1 : 0;

    $wpdb->update(
        "{$wpdb->prefix}bbj_weeks_players",
        array(
            'active' => $active_value,
            'hoh' => $hoh_value,
            'pov' => $pov_value,
            'nom' => $nom_value,
            'saved' => $saved_value,
            'evicted' => $evicted_value,
            'veto_played' => $played_veto,
            'misc_comp' => $misc_comp_value
        ),
        array('week_id' => $week_id, 'player_id' => $player),
        array('%d', '%d', '%d', '%d', '%d'),
        array('%d', '%d')
    );
    //bbj_log2('last query');
    //bbj_log2(print_r($wpdb->last_query, true));



    foreach ($voted_for as $i => $voted_player) {
        //bbj_log2('player id loop');
        //bbj_log2($player_id[$i]);
        // bbj_log2($remaining_jury[$i]->player_id);
         //bbj_log2($i);
        $wpdb->update(
            "{$wpdb->prefix}bbj_weeks_players",
            array('voted_for' => $voted_player),
            array('week_id' => $week_id, 'player_id' => $player_id[$i]),
            array('%d'),
            array('%d', '%d')
        );
    }
}


    // Redirect back to the current page to avoid form resubmission
    wp_redirect($_SERVER['REQUEST_URI']);
    exit;
}


?>

<?php
}

function show_weeks_table($season) {

    global $wpdb;

    echo '<pre>',print_r($season,1),'</pre>';

    $weeks_table = $wpdb->prefix . 'bbj_weeks';
    $season_table = $wpdb->prefix . 'bbj_seasons';

    $weeks = $wpdb->get_results("SELECT * FROM $weeks_table WHERE season_id = $season ORDER BY week_num");
    $season_dates = $wpdb->get_results("SELECT start_date, end_date FROM $season_table WHERE ID = $season");

    $seasonStart = $season_dates[0]->start_date;
    $seasonEnd = $season_dates[0]->end_date;

    ?>
    
    <h2>Weeks Table</h2>


    <table>
        <thead>
            <tr>
                <th>Week</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Player Count</th>
                <th colspan="3">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($weeks as $week) { ?>
                <tr>
                    <form method="post">
                        <td>
                            <input type="number" name="week_num" value="<?= $week->week_num ?>" min="1" required>
                            <input type="hidden" name="week_id" value="<?= $week->id ?>">
                        </td>
                        <td>
                            <input type="date" name="start_date" value="<?= $week->start_date ?>" required>
                        </td>
                        <td>
                            <input type="date" name="end_date" value="<?= $week->end_date ?>" required>
                        </td>
                        <td>
                            <?php
                                $count = $wpdb->get_var($wpdb->prepare("
                                    SELECT COUNT(*)
                                    FROM {$wpdb->prefix}bbj_weeks_players
                                    WHERE week_id = %d
                                ", $week->id));
                                echo $count;
                            ?>
                        </td>
                        <td>
                            <input type="submit" name="update_week" value="Update">
                        </td>
                    </form>
                    <td>
                        <form action="" method="get">
                            <input type="hidden" name="page" value="bbj-tools-edit-player-seasons">
                            <input type="hidden" name="season" value="<?= $season ?>">
                            <input type="hidden" name="week_id" value="<?= $week->id ?>">
                            <input type="hidden" name="week_row" value="<?= $week->week_num ?>">
                            <input type="hidden" name="method" value="edit_weeks">
                            <input type="submit" value="Edit">
                        </form>
                    </td>
                    <td>
                        <form action="" method="post">
                            <input type="hidden" name="week_id" value="<?= $week->week_num ?>">
                            <input type="hidden" name="week_row" value="<?= $week->id ?>">
                            <input type="submit" name="delete_week" value="Delete">
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>


    <h2>Add Week</h2>

    <form action="" method="post">
    <table>
    <tr>
        <td>Week Number:</td>
        <td><input type="number" name="week" min="1" required></td>
        <td>Start Date:</td>
        <td><input type="date" name="start_date" value="<?= esc_attr($seasonStart) ?>" min="<?= esc_attr($seasonStart) ?>" max="<?= esc_attr($seasonEnd) ?>" required></td>
        <td>End Date:</td>
        <td><input type="date" name="end_date" value="<?= esc_attr($seasonEnd) ?>" min="<?= esc_attr($seasonStart) ?>" max="<?= esc_attr($seasonEnd) ?>" required></td>
        <td></td>
        <?php wp_nonce_field( 'enter_week', 'enter_week_nonce' ); ?>
        <td><button type="submit" name="enter_week">Enter</button></td>
        </tr>
    </table>
                note - Week should end on Thursday

    </form>


    <a href="/wp-admin/admin.php?page=bbj-tools-seasons">Back to seasons</a>
    <?php
}


