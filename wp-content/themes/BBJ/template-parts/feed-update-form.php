<?php
if (is_user_logged_in()):
  if (current_user_can("administrator") || current_user_can("editor")): ?>

<div class="feed-update-box"><?php echo FrmFormsController::get_form_shortcode(["id" => 5, "title" => false, "description" => true]); ?></div>

<?php endif;
endif; ?>
