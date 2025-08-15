<?php 

defined( 'ABSPATH' ) || exit;

$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;
$method    = $player_id > 0 ? 'edit' : 'add';

// get page type based on method

$all_players = bbj_v2_get_all_players('first_name', 'ASC');


// get player ID if editing
$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;

$page_type = $player_id > 0
    ? 'Edit Player'
    : 'Add Player';

$player = [];


$first_name        = '';
$last_name         = '';
$official_nickname = '';
$date_of_birth     = '';
$occupation        = '';
$gender            = '';
$player_image      = '';
$facebook          = '';
$twitter           = '';
$tiktok            = '';
$instagram         = '';
$address_street    = '';
$lat               = '';
$lng               = '';
$state             = '';
$city              = '';

if ( $player_id > 0 ) {
    $player = bbj_v2_get_player( $player_id );
bbj_log3(print_r($player, true));
    $first_name        = $player['first_name']        ?? '';
    $last_name         = $player['last_name']         ?? '';
    $official_nickname = $player['official_nickname'] ?? '';
    $date_of_birth     = $player['date_of_birth']     ?? '';
    $occupation        = $player['occupation']        ?? '';
    $gender            = $player['player_gender']     ?? '';
    $player_image      = $player['profile_picture']   ?? '';
    $facebook          = $player['facebook']          ?? '';
    $twitter           = $player['twitter']           ?? '';
    $tiktok            = $player['tiktok']            ?? '';
    $instagram         = $player['instagram']         ?? '';
    $address_street    = $player['address_street']    ?? '';
    $lat               = $player['lat']               ?? '';
    $lng               = $player['lng']               ?? '';
    $state             = $player['administrative_area_level_1']      ?? '';
    $city              = $player['locality']       ?? '';

}


// get profile picture URL
if ( $player_image ) {
    $player_image = wp_get_attachment_image_url( $player_image, 'bbj_v2_profile_image' );
} else {
    $player_image = BBJ_V2_URL . 'assets/images/default-player.png'; // default image if none set
}

?>


<div class="wrap  bbj-admin">
    <h1><?php echo $page_type?></h1>
    <p>Use this page to add or edit player information for the Big Brother Junkies plugin.</p>


    <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post"  enctype="multipart/form-data">
      <input type="hidden" name="action" value="bbj_v2_add_edit_player">
      <?php if ( $player_id > 0 ) : ?>
        <input type="hidden" name="player_id" value="<?php echo esc_attr( $player_id ); ?>">
        <input type="hidden" name="player_city" id="player_city" value="<?php echo esc_attr( $city ?? '' ); ?>">
        <input type="hidden" name="player_state" id="player_state" value="<?php echo esc_attr( $state ?? '' ); ?>">
      <?php endif; ?>
      <?php wp_nonce_field( 'bbj_add_edit_player_action', 'bbj_add_edit_player_nonce' ); ?>
      <h2><?php echo $page_type?></h2>

      <table class="bbj-table">
        <tr class="text-sm">
          <td class="bg-slate-200">First Name</td>
          <td class="w-40"><input type="text" class="w-full text-xs p-0" name="first_name" value="<?php echo esc_attr( $first_name ?? '' ); ?>" required></td>
          <td class="bg-slate-200">Last Name</td>
          <td class="w-40"><input type="text" class="w-full text-xs p-0" name="last_name" value="<?php echo esc_attr( $last_name ?? '' ); ?>" required></td>

          <td rowspan="7">
            <?php if ( $player_image ) : ?>
              <img src="<?php echo esc_url( $player_image ); ?>" alt="<?php echo esc_attr( $first_name . ' ' . $last_name ); ?>" class="bbj-player-image h-[260px] w-[260px] object-cover mx-auto">
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td class="bg-slate-200">Official Nickname</td>
          <td><input type="text" name="official_nickname" value="<?php echo esc_attr( $official_nickname ?? '' ); ?>"></td>
          <td class="bg-slate-200">Date of Birth</td>
          <td>
            <input type="date" name="date_of_birth" value="<?php echo esc_attr( $date_of_birth ?? '' ); ?>" class="bbj-input-text !w-full">
          </td>
        </tr>
        <tr>
          <td class="bg-slate-200">Gender</td>
          <td>
            <?php $is_new = ( $method === 'add' ); ?>
            <select name="player_gender" class="bbj-input-select w-full">
              <option value="" disabled <?php echo $is_new ? 'selected' : ''; ?>>
                Select Gender
              </option>
              <option value="male"   <?php selected( $gender, 'male' ); ?>>Male</option>
              <option value="female" <?php selected( $gender, 'female' ); ?>>Female</option>
              <option value="other"  <?php selected( $gender, 'other' ); ?>>Other</option>
            </select>
          </td>
          <td class="bg-slate-200">Occupation</td>
          <td><input type="text" name="occupation" value="<?php echo esc_attr( $occupation ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>

        </tr>
        <tr>
          <td class="bg-slate-200">Profile Picture</td>
          <td colspan="3">
            <input type="file" name="profile_picture_file" accept="image/*"><button type="button" class="button button-primary">Upload</button>  
          </td>
        </tr>
        <tr>
          <td class="bg-slate-200">Google Address</td>
          <td colspan="3"><input type="text" id="address_street" name="address_street" value="<?php echo esc_attr( $address_street ?? '' ); ?>" class="bbj-input-text w-full text-left"  autocomplete="off"></td>
        </tr>
        <tr>
          <td class="bg-slate-200">Lat</td>
          <td><input type="text" name="lat" id="lat" value="<?php echo esc_attr( $lat ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>
          <td class="bg-slate-200">Lng</td>
          <td><input type="text" name="lng" id="lng" value="<?php echo esc_attr( $lng ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>
        </tr>
        <tr>
          <td colspan="3">Lat/Lng should auto populate when Google Address is selected</td>
        </tr>
      </table>
      <h2>Socials</h2>
      <table class="bbj-table w-3/4">

        <tr>
          <th>Facebook</th>
          <th>Twitter</th>
          <th>TikTok</th>
          <th>Instagram</th>
        </tr>
        <tr>
          <td><input type="url" name="facebook" value="<?php echo esc_url( $facebook ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>
          <td><input type="url" name="twitter" value="<?php echo esc_url( $twitter ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>
          <td><input type="url" name="tiktok" value="<?php echo esc_url( $tiktok ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>
          <td><input type="url" name="instagram" value="<?php echo esc_url( $instagram ?? '' ); ?>" class="bbj-input-text w-full text-left"></td>
        </tr>
        
      </table>

      
      <button type="submit"  class="button button-primary"><?php echo esc_html( $page_type ); ?></button>
    </form>

    <!-- Select Player Dropdown -->
    <h2 class="mt-6">Select Player</h2>
    <form method="get" action="<?php echo esc_url( admin_url('admin.php') ); ?>">
      <input type="hidden" name="page" value="bbj-v2-add-edit-player" />
      <select name="player_id" id="bbj_v2_player">
        <?php foreach ( $all_players as $p ) : ?>
          <option
            value="<?php echo esc_attr( $p['id'] ); ?>"
            <?php selected( $player_id, $p['id'] ); ?>
          >
            <?php echo esc_html( $p['first_name'] . ' ' . $p['last_name'] ); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="button button-primary">Select Player</button>
    </form>

</div>
