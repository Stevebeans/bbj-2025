<?php
/**
 * Plugin Name: BBJ Tools
 * Description: A collection of tools for managing Big Brother reality show data.
 */

// Ensure only administrators can access this page
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized user');
}

// Load the header
require_once ABSPATH . 'wp-admin/admin-header.php';


if (isset($_POST['update_stats']) && current_user_can('manage_options')) {

    require_once (LIB_PATH . 'update-stats.php');
    //bbj_log2('Update stats');
   // bbj_log2($_POST);
    update_player_stats($_POST['season']);
}


?>

<div class="wrap">
    <h1>Season Relationships</h1>

    <?php
    global $wpdb;
    $seasons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bbj_seasons ORDER BY start_date DESC");
    if (count($seasons) > 0) {
    ?>
    <table>
        <thead>
            <tr>
                <th>Season Name</th>
                <th>Start Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($seasons as $season): ?>
            <tr>
                <td><?php echo $season->full_name; ?></td>
                <td><?php echo $season->start_date; ?></td>
                <td><a href="<?= 'admin.php?page=bbj-tools-edit-player-seasons&season=' . $season->ID; ?>&method=add">Add / Remove Players</a> |
                <td><a href="<?= 'admin.php?page=bbj-tools-edit-player-seasons&season=' . $season->ID; ?>&method=edit">Edit Players</a> |
                <td><a href="<?= 'admin.php?page=bbj-tools-edit-player-seasons&season=' . $season->ID; ?>&method=weeks">Weeks</a> |
                </td>
                <form action="" method="post">
                <td>
                    <input type="hidden" name="season" value="<?= $season->ID ?>">
                    <input type="submit" name="update_stats" value="update stats">
                </td></form>

            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    } else {
        echo '<p>No seasons found.</p>';
    }
    ?>
</div>



<?php
// Load the footer
require_once ABSPATH . 'wp-admin/admin-footer.php';
?>
