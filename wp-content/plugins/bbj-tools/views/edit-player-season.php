<?php
/**
 * Plugin Name: BBJ Tools
 * Description: A collection of tools for managing Big Brother reality show data.
 */

// Ensure only administrators can access this page
if (!current_user_can('manage_options')) {
    wp_redirect(wp_login_url(admin_url('admin.php?page=bbj-plugin&subpage=edit-player-season')));
    exit;
}

// Load the WordPress environment
require_once ABSPATH . 'wp-load.php';
require_once (VIEWS_PATH . 'add-player-season.php');
require_once (VIEWS_PATH . 'weeks-table.php');


// Get the season ID from the URL parameter
$season_id = isset($_GET['season']) ? intval($_GET['season']) : 0;

// Lookup the season name
$season_name = '';
if ($season_id) {
    global $wpdb;
    $season_name = $wpdb->get_var($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}bbj_seasons WHERE ID = %d", $season_id));
}


        // Get all entries for this season
        $entries = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bbj_play_season_rel WHERE season_id = %d ORDER by finish ASC", $season_id));

        $players = $wpdb->get_results("SELECT ID, CONCAT(first_name, ' ', last_name) as name FROM {$wpdb->prefix}bbj_players ORDER BY name");



        // build an array with the results of $players 

        $player_array = array();
        foreach ($players as $player) {
            $player_array[$player->ID] = $player->name;
        }


       //bbj_log2(print_r($_POST, true));

       // Get the pages with the scripts 

       require_once(LIB_PATH . 'edit-weeks.php');


        if (isset($_POST['delete_id']) && current_user_can('manage_options')) {

            delete_relationship_entry($_POST['delete_id']);
        }


        if (isset($_POST['update_entries'] ) && current_user_can('manage_options')) {
            //bbj_log('update entries');
            echo 'hey';
            update_relationship_entries($entries, $player_array);
        }   

        if (isset($_POST['enter_week']) && current_user_can('manage_options')) {
           // bbj_log2('Add week');
            add_week($_POST, $season_id);
        }

        if (isset($_POST['delete_week']) && current_user_can('manage_options')) {
            //bbj_log2('Delete week');
            //($_POST);
            delete_week($_POST['week_row'], $season_id);
        }   

        if (isset($_POST['edit_week']) && current_user_can('manage_options')) {
            //bbj_log2('Edit weeks');
            //bbj_log2($_POST);
            edit_weeks_table($season_id, $_POST['week_row']);
        }

        if (isset($_POST['update_week']) && current_user_can('manage_options')) {
            //bbj_log2('Update week');
            //bbj_log2($_POST);
            update_week($_POST, $season_id);
        }
        

        
            
?>

    <h1 class="admin-head"><?php echo $season_name; ?> - Player-Season Relationships</h1>

    <?php 

        //bbj_log2('get info');
    //bbj_log2(print_r($_GET, true));

    //bbj_log2('post info');
    if (isset($_GET['week_row'])) {
        $weekRow = $_GET['week_row'];
        $weekID = $_GET['week_id'];
    } else {
        $weekRow = '';
    }
    //bbj_log2(print_r( $weekRow, true));
    //bbj_log2(print_r($season_id, true));
    //bbj_log2('end info');
    
    switch ($_GET['method']) {
    case 'add':
        add_player_season_relationship($season_id);
        show_player_table($entries, $player_array, $season_id);
        break;
    case 'edit':
        show_edit_table($entries, $player_array, $season_id);
        break;
    case 'weeks':
        show_weeks_table($season_id);
        break;
    case 'edit_weeks':
        edit_weeks_table($season_id,  $weekRow, $season_name, $weekID);
        break;
    default:
        echo 'No method called';
        break;
}
    ?>





    
    