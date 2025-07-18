<?php

require_once BBJ_ROOT . "/vendor/autoload.php";

\Stripe\Stripe::setApiKey("sk_test_51LHLXmGjb8i2IhlbTgA2HyuwNTWkZoSeofmsz02TxFiW9WIl0vOqr29UiHEA9FFnoc0UksoBreB4lwcZyxrnZit500I6yynvsV");

add_action("rest_api_init", "register_stripe_webhook");

function register_stripe_webhook()
{
  register_rest_route("stripe/v1", "/webhook", [
    "methods" => "POST",
    "callback" => "handle_stripe_webhook",
  ]);
}

function handle_stripe_webhook(WP_REST_Request $request)
{
  // Retrieve the request body
  $event_json = json_decode($request->get_body(), true);

  bbj_log(print_r($event_json["type"], true));

  if (!is_null($event_json) && isset($event_json["type"])) {
    $event_type = $event_json["type"];
    // Check the event type
    switch ($event_type) {
      case "customer.subscription.created":
        // Handle the subscription creation event
        handle_subscription_created($event_json);
        break;
      case "customer.subscription.deleted":
        // Handle the subscription deletion event
        handle_subscription_deleted($event_json);
        break;
      case "customer.subscription.updated":
        // Handle the subscription update event
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
      bbj_log("User with email " . $customer_email . " role has changed to subscriber");
    }
  } catch (Exception $e) {
    bbj_log("There was a failure changing the role on cancel: " . $e->getMessage());
  }
}

function handle_subscription_updated($event_json)
{
  // Handle the subscription update event here
  // Update user roles or perform other actions
  bbj_log("Stripe Sub Updated");
  bbj_log(print_r($event_json, true));
}
