<?php
if (!current_user_can("manage_options")) {
  wp_redirect(home_url());
  exit();
}

get_header();

// Get the season ID from the URL parameter
$season_id = isset($_GET["season"]) ? intval($_GET["season"]) : 0;

// Lookup the season name
$season_name = "";
if ($season_id) {
  global $wpdb;
  $season_name = $wpdb->get_var($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}bbj_seasons WHERE ID = %d", $season_id));
}
?>


<?php
function prefill_players_field($field)
{
  // Pre-fill formidable forms field 86 with players from the wp_bbj_players table
  if ($field["id"] == 86) {
    global $wpdb;
    $players = $wpdb->get_results("SELECT ID, CONCAT(first_name, ' ', last_name) as name FROM {$wpdb->prefix}bbj_players ORDER BY name");
    $options = [];
    foreach ($players as $player) {
      if ($player && isset($player->ID)):
        $options[$player->ID] = $player->name;
      endif;
    }
    $field["options"] = $options;
    $field["default_value"] = array_keys($options);
  }
  return $field;
}

add_filter("frm_setup_new_fields_vars", "prefill_players_field");

add_filter("frm_repeater_row_actions", "prefill_players_field_in_repeater", 10, 3);

function prefill_players_field_in_repeater($actions, $field, $args)
{
  // Pre-fill formidable forms field 86 with players from the wp_bbj_players table
  if ($field["id"] == 86) {
    global $wpdb;
    $players = $wpdb->get_results("SELECT ID, CONCAT(first_name, ' ', last_name) as name FROM {$wpdb->prefix}bbj_players ORDER BY name");

    $options = [];
    foreach ($players as $player) {
      if ($player && isset($player->ID)):
        $options[$player->ID] = $player->name;
      endif;
    }

    $actions["default_value"] = array_keys($options);
    $actions["options"] = $options;
  }

  return $actions;
}
?>

<div class="bbj-container-inner">


  <div class="bbj-inner-content-container">
    <div class="bbj-content-container">
      <div class="heading-bg">
        <h1 class="heading-text"><a href="<?= site_url() ?>" class="hover:text-primarySoft">Home</a> >> <?php echo $season_name; ?> - Player-Season Relationships</h1>
      </div>
    
      <div>

      <?php echo do_shortcode('[formidable id="11" param_season="' . $season_id . '"]'); ?>
      
      </div>
    </div>

    <div>
        <?php get_template_part("template-parts/sidebar-default"); ?>
    </div>
  </div>




</div>


<?php get_footer(); ?>
