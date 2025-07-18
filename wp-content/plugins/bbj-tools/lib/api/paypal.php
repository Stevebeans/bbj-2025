<?php 

add_action('frm_payment_paypal_ipn', 'change_paid_user_role');
function change_paid_user_role($args){

  //bbj_log2(print_r($args, true));

  $pay_vars = $args['pay_vars'];

  $unserialized_ipn = maybe_unserialize($pay_vars['meta_value']);

  // bbj_log2(print_r('UNSERIALIZED', true));
  // bbj_log2(print_r($unserialized_ipn, true));

  $ipn_data = array_shift($unserialized_ipn);

  // bbj_log2(print_r('IPN DATA', true));
  // bbj_log2(print_r($ipn_data, true));
  if (isset($ipn_data['txn_type']) && $ipn_data['txn_type'] == 'recurring_payment_expired') {
    // Handle the subscription expiration
    // Retrieve the user ID from the IPN data
    $user_id = $args['entry']->user_id;
    if ($user_id) {
        // Change the user role back to 'subscriber'
        $user = new WP_User($user_id);
        $user->set_role('subscriber');

        // Send email notification
        $to = 'stevebeans@bigbrotherjunkies.com';
        $subject = 'User expired, check role';
        $message = 'The user (' . $user->user_login . ') had a subscription with PayPal and it has expired. Please check the user role to ensure that it has gone through.';
        $headers = 'Content-Type: text/plain; charset=UTF-8';
        
        wp_mail($to, $subject, $message, $headers);
    }
}

    if ( $args['entry']->form_id != 14 ) {
        return;
    }
    $new_role = 'supporter'; //change this to the role paid users should have
    if(!$args['pay_vars']['completed']) {
       return; //don't continue if the payment was not completed
    }
    if(!$args['entry']->user_id or !is_numeric($args['entry']->user_id)) {
       return; //don't continue if not linked to a user
    }
    $user = get_userdata($args['entry']->user_id);
    if(!$user) {
        return; //don't continue if user doesn't exist
    }
    $updated_user = (array) $user;
    // Get the highest/primary role for this user
    $user_roles = $user->roles;
    $user_role = array_shift($user_roles);
    if ( $user_role == 'administrator' ) {
        return; //make sure we don't downgrade any admins
    }
    $updated_user['role'] = $new_role;
    wp_update_user($updated_user);
}



// Webhook

function register_paypal_webhooks() {
  register_rest_route("paypal/v1", "/live/webhook", [
    "methods" => "POST",
    "callback" => "handle_paypal_live_webhook",
    "permission_callback" => "__return_true"
  ]);

  register_rest_route("paypal/v1", "/sandbox/webhook", [
    "methods" => "POST",
    "callback" => "handle_paypal_sandbox_webhook",
    "permission_callback" => "__return_true"
  ]);

  register_rest_route("paypal/v1", "/ipn", [
    "methods" => "POST",
    "callback" => "handle_paypal_ipn",
    "permission_callback" => "__return_true"
  ]);
}

add_action("rest_api_init", "register_paypal_webhooks");



function handle_paypal_ipn(WP_REST_Request $request) {
  // Process the IPN data here
  // ...

  // bbj_log2(print_r('IPN REQUEST', true));
  // bbj_log2(print_r($request, true));
}




function handle_paypal_live_webhook(WP_REST_Request $request) {
  // Process the live webhook data here
  $payload = json_decode($request->get_body());

  process_paypal_webhook($payload);
}

function handle_paypal_sandbox_webhook(WP_REST_Request $request) {

  $payload = json_decode($request->get_body());
  // Process the sandbox webhook data here
  process_paypal_webhook($payload);
}


function process_paypal_webhook($payload) {
  // Process the webhook data here

  // Check the event type

  switch ($payload->event_type) {
    case "BILLING.SUBSCRIPTION.EXPIRED":
      // Handle the subscription cancellation event
      //bbj_log2(print_r('trigger sub cancel', true));
      bbj_switch_role($payload->resource->subscriber->email_address);
      break;   
      
    case "BILLING.SUBSCRIPTION.CREATED":
      // Handle the subscription creation event
      //bbj_log2(print_r('trigger sub create', true));
      bbj_get_creation_info($payload->resource);
      break;
    }





  // Return a response to acknowledge receipt of the webhook
  return new WP_REST_Response("Webhook received", 200);
}


function bbj_get_creation_info($info){
  //bbj_log2(print_r($info, true));
}


function bbj_switch_role($user_id) {
  // Change the user role from 'supporter' back to 'subscriber'
  $user = new WP_User($user_id);
  $user->set_role('subscriber');
}


