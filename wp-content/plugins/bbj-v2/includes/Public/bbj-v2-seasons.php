<?php 

global $wpdb; 

defined( 'ABSPATH' ) || exit;

// pull seasons from the database
$seasons = $wpdb->get_results( "SELECT * FROM " . BBJ_V2_TABLE_SEASONS . " ORDER BY season_number DESC" );




?>
<div class="wrap">
    <h1>BBJ Seasons</h1>
    <p>Manage your Big Brother Junkies seasons below.</p>

    <?php if ( ! empty( $seasons ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Season Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $seasons as $season ) : ?>
                    <tr>
                        <td><?php echo esc_html( $season->full_name ); ?></td>
                        <td><?php echo esc_html( $season->start_date ); ?></td>
                        <td><?php echo esc_html( $season->end_date ); ?></td>
                        <td>
                            <a href="<?php echo admin_url( 'admin.php?page=bbj-v2-edit-season&season_id=' . $season->id ); ?>" class="button button-primary">Edit</a>
                            
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No seasons found.</p>
    <?php endif; ?>
    <p><a href="<?php echo admin_url( 'admin.php?page=bbj-v2-settings' ); ?>" class="button button-primary">Add New Season</a></p>
</div>