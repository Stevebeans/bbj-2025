<?php

if (empty($attributes["data"])) {
  return;
}

// Unique HTML ID if available.
$id = "hero-" . ($attributes["id"] ?? "");
if (!empty($attributes["anchor"])) {
  $id = $attributes["anchor"];
}

// Fields
$ppp = mb_get_block_field("posts_per_page");
// Main ID
$mID = mb_get_block_field("random_id");
// Cache ID
$cID = mb_get_block_field("cache_ID");
$postType = mb_get_block_field("customPT");
$comPT = implode(",", $postType);
$feedDate = mb_get_block_field("feed_date");
$newDate = strtotime($feedDate);
$newDate = getDate($newDate);
$year = $newDate["year"];
$month = $newDate["mon"];
$day = $newDate["mday"];
$monthFull = $newDate["month"];

$postsPerPage = mb_get_block_field("posts_per_page");

$ajaxCode = '[ajax_load_more id="' . $mID . '" container_type="div" css_classes="update-post" cache="true" cache_id="' . $cID . '" preloaded="true" preloaded_amount="' . $ppp . '" seo="true" post_type="' . $comPT . '" posts_per_page="' . $ppp . '" destroy_after="200" no_results_text="No updates yet, check back shortly!" year="' . $year . '" month="' . $month . '" day="' . $day . '" order="DESC" cta="true" cta_position="after:5" cta_theme_repeater="adsense-feed-updates.php" theme_repeater="feed-list.php"]';

//
?>
<div><h3>Live Feed Updates for <?= $monthFull ?> <?= $day ?>, <?= $year ?></h3></div>
<?= do_shortcode($ajaxCode) ?>
