<?php 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

?>

<div class="wrap bbj-admin">

  <h1><?php esc_html_e( 'Edit Ads', 'bbj' ); ?></h1>

  <form method="post" action="options.php">
    <?php
      settings_fields('bbj_ads_group');   // Nonce + option group
      do_settings_sections('bbj-ads');    // Sections + fields
      submit_button(__('Save Ads', 'bbj'));
    ?>
  </form>

</div>