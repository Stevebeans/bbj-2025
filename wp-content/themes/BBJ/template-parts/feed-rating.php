<?php 


$post_id = get_query_var('post_id', 1);
            
global $wpdb;

$total_rating = 0;

$table_name = $wpdb->prefix . 'bbj_feed_ratings';

$query = "SELECT SUM(rating) AS total_rating FROM $table_name WHERE update_id = $post_id";

$total_rating = $wpdb->get_var($query);

if (!$total_rating) {
  $total_rating = 0;
}

$rating_color = "text-gray-500";

if ($total_rating > 0) {
  $rating_color = "positive";
} else if ($total_rating < 0) {
  $rating_color = "negative";
}


?>
<div id="feed-updates-left-<?= $post_id ?>" class="p-2 bg-gray-200 flex flex-col w-10 rounded-b-md rounded-tl-md feed-update-ratings" data-feed-rating="<?= $post_id ?>">

<div class="feed-update-id-up hover:cursor-pointer text-center"><i class="fa-solid fa-chevron-up text-sky-600"></i></div>
<div class="feed-update-count text-center font-ibm text-lg <?= $rating_color ?>" data-count-for="<?= $post_id ?>"><?= $total_rating ?></div>

<div class="feed-update-id-down hover:cursor-pointer  text-center"><i class="fa-solid fa-chevron-down text-sky-600"></i></div> 
            
</div>    