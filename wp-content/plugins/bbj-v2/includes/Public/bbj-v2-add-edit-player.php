<?php 

defined( 'ABSPATH' ) || exit;

$method = $_GET['method'] ?? 'add';

// get page type based on method
$page_type = $method === 'edit' ? 'Edit Player' : 'Add Player';

bbj_log3(print_r('hi', true));
// get player ID if editing
$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;

bbj_log3(print_r($player_id, true));

$player = [];

/// if player ID is set, fetch player data
if ( $player_id > 0 ) {
  $player = bbj_v2_get_player( $player_id );
  $first_name = $player['first_name'] ?? '';
  $last_name = $player['last_name'] ?? '';
  $player_image = $player['profile_picture'] ?? '';
  $date_of_birth = $player['date_of_birth'] ?? '';
  $occupation = $player['occupation'] ?? '';
  $gender = $player['player_gender'] ?? '';
  $official_nickname = $player['official_nickname'] ?? '';
  $facebook = $player['facebook'] ?? '';
  $twitter = $player['twitter'] ?? '';
  $tiktok = $player['tiktok'] ?? '';
  $instagram = $player['instagram'] ?? '';


}



bbj_log3(print_r($player, true));
?>


<div class="wrap  bbj-admin">
    <h1><?php echo $page_type?></h1>
    <p>Use this page to add or edit player information for the Big Brother Junkies plugin.</p>


    <form action="" >
      <h2><?php echo $page_type?></h2>

      <table class="bbj-table">
        <tr>
          <th>First Name</th>
          <td><input type="text" name="first_name" value="<?php echo esc_attr( $first_name ?? '' ); ?>" required></td>
          <th>Last Name</th>
          <td><input type="text" name="last_name" value="<?php echo esc_attr( $last_name ?? '' ); ?>" required></td>
        </tr>
        <tr>
          
        </tr>
        <tr>
          <th>Official Nickname</th>
          <td><input type="text" name="official_nickname" value="<?php echo esc_attr( $official_nickname ?? '' ); ?>"></td>
          <th>Date of Birth</th>
          <td>
            <input type="date" name="date_of_birth" value="<?php echo esc_attr( $date_of_birth ?? '' ); ?>" class="bbj-input-text !w-full">
          </td>
        </tr>
        <tr>
          <th>Player Gender</th>
          <td>
            <select name="player_gender" class="bbj-input-select w-full">
              <option value="male" <?php selected( $gender, 'male' ); ?>>Male</option>
              <option value="female" <?php selected( $gender, 'female' ); ?>>Female</option>
              <option value="other" <?php selected( $gender, 'other' ); ?>>Other</option>
            </select>
          </td>
          <th>Occupation</th>
          <td><input type="text" name="occupation" value="<?php echo esc_attr( $occupation ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>

        </tr>
        <tr>
          <th>Profile Picture</th>
          <td>
            <input type="text" name="profile_picture" value="<?php echo esc_url( $player_image ?? '' ); ?>" class="bbj-input-text">
            <button type="button" class="bbj-upload-button">Upload</button>
          </td>
        </tr>
        
      </table>
    </form>

    <form method="post" action="">
        <?php
        // Output security fields for the registered setting "bbj_v2_player_options"
        settings_fields( 'bbj_v2_player_options' );
        
        // Output setting sections and their fields
        do_settings_sections( 'bbj-v2-edit-player' );
        
        // Output save settings button
        submit_button();
        ?>
    </form>
</div>