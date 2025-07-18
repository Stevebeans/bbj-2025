<?php 

wp_enqueue_style('my-plugin-tailwind-frontend', plugins_url('../build/index-style.css', __FILE__), array(), BBJ_TOOLS_VERSION);
wp_enqueue_script('my-plugin-react-script', plugins_url('../build/index.js', __FILE__), array('jquery'), BBJ_TOOLS_VERSION, true);







// Load the plugin page content
global $wpdb;
$weeks_table = $wpdb->prefix . 'bbj_weeks';
$weeks_table_rel = $wpdb->prefix . 'bbj_weeks_players';
$nominations_table = $wpdb->prefix . 'bbj_nominations';
$relationships_table = $wpdb->prefix . 'bbj_play_season_rel';
$feed_rating_table = $wpdb->prefix . 'bbj_feed_ratings';

// Check for form submission and save the settings
if (isset($_POST['bbj_seasons_nonce']) && wp_verify_nonce($_POST['bbj_seasons_nonce'], 'bbj_seasons_form')) {
    $current_season = [
        'ID' => sanitize_text_field($_POST['current_season']),
        'full_name' => sanitize_text_field($_POST['full_name']),
        'abbreviation' => sanitize_text_field($_POST['abbreviation']),
        'season_number' => sanitize_text_field($_POST['season_number'])
    ];
    update_option('current_season', $current_season);
}

// Retrieve seasons from the wp_bbj_seasons table
$seasons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bbj_seasons");

// Get the current season from settings
$current_season = get_option('current_season');
?>
<div class="wrap">



    <h1>BBJ Tools</h1>



    <div id="bbj-settings"></div>


    What Season are we on?

    <?= currentSeason('name') ?>
    
    <?= currentSeason('small_name') ?>

    <?= currentSeason('season_number') ?>

    <form method="post" action="">
            <?php wp_nonce_field('bbj_seasons_form', 'bbj_seasons_nonce'); ?>
            <label for="current_season">Current Season:</label>
            <select name="current_season" id="current_season" onchange="populateSeasonFields(this)">
                <?php foreach ($seasons as $season): ?>
                        <option value="<?php echo esc_attr($season->ID); ?>" <?php echo (is_array($current_season) && $current_season['ID'] == $season->ID) ? 'selected' : ''; ?> data-full_name="<?php echo esc_attr($season->full_name); ?>" data-abbreviation="<?php echo esc_attr($season->abbreviation); ?>" data-season_number="<?php echo esc_attr($season->season_number); ?>"><?php echo esc_html($season->full_name); ?></option>

                <?php endforeach; ?>
            </select>
            <input type="hidden" name="full_name" id="full_name" value="<?php echo esc_attr($current_season['full_name']); ?>">
            <input type="hidden" name="abbreviation" id="abbreviation" value="<?php echo esc_attr($current_season['abbreviation']); ?>">
            <input type="hidden" name="season_number" id="season_number" value="<?php echo esc_attr($current_season['season_number']); ?>">
            <?php submit_button('Save Current Season'); ?>
        </form>

        <script>
    function populateSeasonFields(select) {
        var selectedOption = select.options[select.selectedIndex];
        document.getElementById('full_name').value = selectedOption.getAttribute('data-full_name');
        document.getElementById('abbreviation').value = selectedOption.getAttribute('data-abbreviation');
        document.getElementById('season_number').value = selectedOption.getAttribute('data-season_number');
    }
</script>



<?php 
$stripe_live_api_key = getenv('STRIPE_LIVE_API_KEY');
$stripe_test_api_key = getenv('STRIPE_TEST_API_KEY');

// echo $stripe_test_api_key;
// echo $stripe_live_api_key;


?>





    <h2>Weeks Table</h2>
    <?php
    if ($wpdb->get_var("SHOW TABLES LIKE '$weeks_table'") == $weeks_table) {
        echo "<p>This table exists.</p>";
    } else {
        echo "<p>This table does not exist.</p>";
    }
    ?>
    <form method="post">
        <input type="submit" name="create_weeks_table" value="Create BBJ Weeks table">
    </form>


    <?php
    if ($wpdb->get_var("SHOW TABLES LIKE '$weeks_table_rel'") == $weeks_table_rel) {
        echo "<p>This table exists.</p>";
    } else {
        echo "<p>This table does not exist.</p>";
    }
    ?>
    <form method="post">
        <input type="submit" name="create_weeks_players_table" value="Create BBJ Week/Players table">
    </form>


    <h2>Feed Rating Table</h2>
    <?php

    if ($wpdb->get_var("SHOW TABLES LIKE '$feed_rating_table'") == $feed_rating_table) {
        echo "<p>This table exists.</p>";
    } else {
        echo "<p>This table does not exist.</p>";
    }

    ?>

    <form action="" method="post">
        <input type="submit" name="create_feed_rating_table" value="Create BBJ Feed Rating table">
    </form>
    



    


    <h2>Relationships Table</h2>

    <?php 
    if ($wpdb->get_var("SHOW TABLES LIKE '$relationships_table'") == $relationships_table) {
        echo "<p>This table exists.</p>";
    } else {
        echo "<p>This table does not exist.</p>";
    }
    ?>
    <form method="post">
        <input type="submit" name="create_relationships_table" value="Create BBJ Relationships table">
    </form>


    <h2>Add Fields to relationship table</h2>

<?php
$column_names = ['save_per', 'hoh_per', 'pov_per', 'votes_nom', 'comp_per', 'total_hoh', 'total_pov', 'total_nom', 'total_saved', 'total_weeks', 'total_votes', 'finish'];
$columns_exist = true;

foreach ($column_names as $column_name) {
    $column_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = %s
        AND TABLE_NAME = %s
        AND COLUMN_NAME = %s
    ", DB_NAME, $relationships_table, $column_name));
    
    if (!$column_exists) {
        $columns_exist = false;
        break;
    }
}

if ($columns_exist) {
    echo "<p>The required columns exist in the table.</p>";
} else {
    echo "<p>One or more required columns do not exist in the table.</p>";
}
?>
<form method="post">
    <input type="submit" name="add_fields_relationships_table" value="Add custom columns to BBJ Relationships table">
</form>



</div>

<?php 

if (isset($_POST['create_weeks_players_table'])) {
    create_bbj_week_rel_table();
}

// Check if the create_weeks_table button was clicked
if (isset($_POST['create_weeks_table'])) {
    create_bbj_weeks_table();
}

// Check if the create_nominations_table button was clicked
if (isset($_POST['create_nominations_table'])) {
    create_bbj_nominations_table();
}

// Check if the create_relationships_table button was clicked
if (isset($_POST['create_relationships_table'])) {
    create_bbj_relationships_table();
}

// Check if the create_feed_rating_table button was clicked
if (isset($_POST['create_feed_rating_table'])) {
    create_feed_ratings_table();
}

// Check if the delete_weeks_table button was clicked
if (isset($_POST['delete_weeks_table'])) {
    delete_bbj_weeks_table();
}

// Check if the delete_nominations_table button was clicked
if (isset($_POST['delete_nominations_table'])) {
    delete_bbj_nominations_table();
}

if (isset($_POST['add_fields_relationships_table'])) {
    $result = add_custom_columns_to_bbj_play_season_rel();
    if ($result) {
        echo 'success';
    } else {
        echo 'fail';
    }
}


/*
    <h2>Delete Tables</h2>
    <form method="post">
        <input type="submit" name="delete_weeks_table" value="Delete BBJ Weeks table">
        <input type="submit" name="delete_nominations_table" value="Delete BBJ Nominations table">
    </form>

*/

