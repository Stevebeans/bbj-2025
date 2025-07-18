


<div id="update-box" data-update="<?= $args["bbjUpdater"] ?>">
  <div class="update-box-header">Feed Updates
    <div class="update-box-button"><i class="fa fa-toggle-on" id="toggle_feed_update"></i></div>
  </div>
  <div class="update-box-body"><?php echo FrmFormsController::get_form_shortcode(["id" => 5, "title" => false, "description" => true]); ?></div></div>