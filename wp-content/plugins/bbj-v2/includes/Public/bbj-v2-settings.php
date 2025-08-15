<?php 
defined ( 'ABSPATH' ) || exit;

$seasons = bbj_v2_get_seasons();

$current_season_id = get_option( 'bbj_v2_current_season', '' );

$season_name = '';
if ( $current_season_id ) {
    $current_season = bbj_v2_get_season_by_id( $current_season_id );
    if ( $current_season ) {
        $season_name = $current_season['full_name'];
    }
} else {
    $season_name = 'No current season set';
}

if ( isset( $_POST['bbj_v2_set_current_season_nonce'] )
  && wp_verify_nonce( $_POST['bbj_v2_set_current_season_nonce'], 'bbj_v2_set_current_season' )
) {
    update_option(
        'bbj_v2_current_season',
        sanitize_text_field( $_POST['bbj_v2_season'] )
    );
    echo '<div class="notice notice-success is-dismissible"><p>Current season updated.</p></div>';
}

?>

<div class="wrap  bbj-admin">
    <h1>BBJ Settings</h1>
    <p>Configure your Big Brother Junkies plugin settings below.</p>

    <div class="border border-gray-300 p-4 mt-4">Current Season: <?php echo esc_html( $season_name ); ?></div>

    <form method="post">
        <?php wp_nonce_field( 'bbj_v2_set_current_season', 'bbj_v2_set_current_season_nonce' ); ?>

        <h2 class="mt-4">Current Season</h2>
        <select name="bbj_v2_season" id="bbj_v2_season">
            <?php foreach ( $seasons as $season ) : ?>
            <option
                value="<?php echo esc_attr( $season['id'] ); ?>"
                <?php selected( $season['id'], $current_season_id ); ?>
            >
                <?php echo esc_html( $season['full_name'] ); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button button-primary">Set Current Season</button>
        </form>
</div>
