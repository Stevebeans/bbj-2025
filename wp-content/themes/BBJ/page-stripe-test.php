<?php

require_once BBJ_ROOT . "/vendor/autoload.php";

\Stripe\Stripe::setApiKey("sk_test_51LHLXmGjb8i2IhlbTgA2HyuwNTWkZoSeofmsz02TxFiW9WIl0vOqr29UiHEA9FFnoc0UksoBreB4lwcZyxrnZit500I6yynvsV");

// try {
//   $plans = \Stripe\Plan::all();
//   //echo "<pre>", print_r($plans, 1), "</pre>";
// } catch (\Stripe\Error\Base $e) {
//   // Handle errors
// }

// Retrieve the customer
$customer = \Stripe\Customer::retrieve("cus_NFw6BPTqqLH1ZX");

// $customer = \Stripe\Customer::create([
//   "email" => "testing@test.com",
// ]);

echo "<pre>", print_r($customer, 1), "</pre>";

$customer->source = "tok_visa";
$customer->save();

$subscription = \Stripe\Subscription::create([
  "customer" => $customer->id,
  "items" => [
    [
      "plan" => "price_1MVJKyGjb8i2IhlbIjMxNKYK",
    ],
  ],
]);

echo "<pre>", print_r($subscription, 1), "</pre>";

// $subscription_id = "sub_1MVK1dGjb8i2IhlbcgXzoGoQ";

// try {
//   $subscription = \Stripe\Subscription::retrieve($subscription_id);
//   $subscription->cancel(["at_period_end" => false]);

//   echo "Subscription canceled successfully.";
// } catch (\Stripe\Error\InvalidRequest $e) {
//   echo "Error: " . $e->getMessage();
// }
