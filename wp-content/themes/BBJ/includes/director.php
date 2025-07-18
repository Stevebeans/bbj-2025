<?php

define("BBJ_DIR", dirname(__FILE__));
define("META_BOX_FILES", BBJ_DIR . "/MB");
define("STRIPE", BBJ_DIR . "/Stripe");
define("SMALL_FUNCTIONS", BBJ_DIR . "/small-functions");

require_once META_BOX_FILES . "/current-status.php";
require_once META_BOX_FILES . "/seasons.php";
require_once META_BOX_FILES . "/feed-updates.php";
require_once META_BOX_FILES . "/season-stats.php";
require_once META_BOX_FILES . "/geolocation.php";
require_once META_BOX_FILES . "/site-settings.php";

require_once SMALL_FUNCTIONS . "/logfile.php";
require_once SMALL_FUNCTIONS . "/ad-insert.php";
require_once SMALL_FUNCTIONS . "/feed-route.php";
require_once SMALL_FUNCTIONS . "/front-page-stats.php";

require_once STRIPE . "/payment-complete.php";
require_once STRIPE . "/payment-fail.php";

//require_once STRIPE . "/ff-stripe.php";
