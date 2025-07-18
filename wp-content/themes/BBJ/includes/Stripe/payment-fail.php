<?php
add_action("frm_payment_status_canceled", "cancel_role");
function cancel_role($atts)
{
  bbj_log(print_r($atts, 1));
  bbj_log("TRIGGERED PAYMENT CANCEL");
  bbj_log("new");
  $new_role = "subscriber"; // change contributor to the new user role
  $entry = isset($atts["entry"]) ? $atts["entry"] : $atts["payment"]->item_id;
  if (is_numeric($entry)) {
    $entry = FrmEntry::getOne($entry);
  }

  $user_id = $entry->user_id;
  if (!empty($user_id)) {
    $user = get_userdata($user_id);
    if (!$user) {
      return; //don't continue if user doesn't exist
    }

    $updated_user = (array) $user;

    // Get the highest/primary role for this user
    $user_roles = $user->roles;
    $user_role = array_shift($user_roles);
    if ($user_role == "administrator") {
      return; //make sure we don't downgrade any admins
    }

    $updated_user["role"] = $new_role;

    wp_update_user($updated_user);
  }
}

/*
4242 4242 4242 4242
*/
