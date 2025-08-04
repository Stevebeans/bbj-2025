<?php 
defined ( 'ABSPATH' ) || exit;

?>

<div class="wrap">
    <h1>BBJ Settings</h1>
    <p>Configure your Big Brother Junkies plugin settings below.</p>

    <form method="post" action="options.php">
        <?php
        // Output security fields for the registered setting "bbj_v2_options"
        settings_fields( 'bbj_v2_options' );
        
        // Output setting sections and their fields
        do_settings_sections( 'bbj-v2-settings' );
        
        // Output save settings button
        submit_button();
        ?>
    </form>
</div>
