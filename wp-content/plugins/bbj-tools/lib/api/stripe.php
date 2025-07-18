<?php 


require_once PLUGIN_ROOT . 'vendor/autoload.php';



$stripe_live_api_key = getenv('STRIPE_LIVE_API_KEY');
$stripe_test_api_key = getenv('STRIPE_TEST_API_KEY');


$stripe_mode = getenv('STRIPE_MODE');
$stripe_api_key = ($stripe_mode === 'live') ? $stripe_live_api_key : $stripe_test_api_key;
\Stripe\Stripe::setApiKey($stripe_api_key);


// bbj_log2(print_r($stripe_api_key, true));

// bbj_log2(print_r('Hook', true));

add_action("rest_api_init", "register_stripe_webhook");

function register_stripe_webhook()
{
  register_rest_route("stripe/v1", "/webhook", [
    "methods" => "POST",
    "callback" => "handle_stripe_webhook",
    "permission_callback" => "__return_true"
  ]);
}

function handle_stripe_webhook(WP_REST_Request $request)
{

      // Retrieve the request's headers and body
      $headers = $request->get_headers();
      $body = $request->get_body();
  
      // Get the signing secret from the environment variable
      $stripe_signing_secret = getenv('STRIPE_SIGNING_SECRET');

     // bbj_log2(print_r($stripe_signing_secret, true));
  
      // Verify the webhook signature
      try {
          $event = \Stripe\Webhook::constructEvent(
              $body,
              $headers['stripe_signature'][0],
              $stripe_signing_secret
          );
      } catch (\Stripe\Exception\SignatureVerificationException $e) {
          // Invalid signature, return an error response
          return new WP_REST_Response('Invalid webhook signature', 400);
      }
  // Retrieve the request body
  $event_json = json_decode($request->get_body(), true);


  bbj_log2(print_r('event type', true));
  bbj_log2(print_r($event_json['type'], true));

  if (!is_null($event_json) && isset($event_json["type"])) {
    $event_type = $event_json["type"];
    // Check the event type
    switch ($event_type) {
      case "customer.subscription.created":
        // Handle the subscription creation event
        bbj_log2(print_r('trigger sub create', true));
        handle_subscription_created($event_json);
        break;
      case "customer.subscription.deleted":
        // Handle the subscription deletion event
        bbj_log2(print_r('triggre sub delete', true));
        handle_subscription_deleted($event_json);
        break;
      case "customer.subscription.updated":
        // Handle the subscription update event
        bbj_log2(print_r('trigger sub update', true));
        handle_subscription_updated($event_json);
        break;
      default:
        // Handle other event types
        break;
    }
  }

  return new WP_REST_Response("Webhook received", 200);
}

// Handle the subscription creation event here
// Update user roles or perform other actions
/*

  stripe trigger customer.subscription.updated --customer cus_NFogLhv8ow9YR8


  */
function handle_subscription_created($event_json)
{
  // Get the customer email
  $customer_id = $event_json["data"]["object"]["customer"];

  $customer = \Stripe\Customer::retrieve($customer_id);

  $customer_email = $customer->email;

  bbj_log2("Stripe Sub Created");
  bbj_log2(print_r($customer_email, true));

  // Get the user by email
  $user = get_user_by("email", $customer_email);

  if ($user) {
    // Update the user role to 'supporter'
    $user->set_role("supporter");
  }
}

function handle_subscription_deleted($event_json)
{
  try {
    $customer_id = $event_json["data"]["object"]["customer"];
    $customer = \Stripe\Customer::retrieve($customer_id);
    $customer_email = $customer->email;
    $user = get_user_by("email", $customer_email);
    if ($user) {
      // Update the user role to 'subscriber'
      $user->set_role("subscriber");
      bbj_log2("User with email " . $customer_email . " role has changed to subscriber");
    }
  } catch (Exception $e) {
    bbj_log2("There was a failure changing the role on cancel: " . $e->getMessage());
  }
}

function handle_subscription_updated($event_json)
{
  // Handle the subscription update event here
  // Update user roles or perform other actions
  bbj_log2("Stripe Sub Updated");
  bbj_log2(print_r($event_json, true));
}
