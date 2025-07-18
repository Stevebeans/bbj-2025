<?php get_header(); ?>

<?php
global $wpdb;

$season_table = $wpdb->prefix . "bbj_seasons";
$relationship = $wpdb->prefix . "bbj_play_season_rel";
$players = $wpdb->prefix . "bbj_players";

// Fetch data from the wp_bbj_play_season_rel table with the desired format
$results = $wpdb->get_results("
SELECT 
    s.full_name, 
    s.start_date, 
    s.end_date,
    s.ID,
    CONCAT(p1.first_name, ' ', p1.last_name, ' ') as winner,
    CONCAT(p2.first_name, ' ', p2.last_name, ' ') as afp,
    CONCAT(p3.first_name, ' ', p3.last_name, ' ') as runner_up,
    p1.ID as winner_id,
    p2.ID as afp_id,
    p3.ID as runner_up_id
FROM $season_table AS s
LEFT JOIN $relationship AS r1 ON s.ID = r1.season_id AND r1.winner = 1
LEFT JOIN $players AS p1 ON p1.ID = r1.player_id
LEFT JOIN $relationship AS r2 ON s.ID = r2.season_id AND r2.afp = 1
LEFT JOIN $players AS p2 ON p2.ID = r2.player_id
LEFT JOIN $relationship AS r3 ON s.ID = r3.season_id AND r3.runner_up = 1
LEFT JOIN $players AS p3 ON p3.ID = r3.player_id
GROUP BY s.ID
ORDER BY s.start_date DESC
", ARRAY_A);

?>

<div class="bbj-container-inner">

  <div class="mt-2 flex w-full flex-col bg-white lg:flex-row overflow-hidden">
    <div class="flex-grow">
      <div class="p-2">
        <h1 class="font-mainHead text-2xl text-primary500">Seasons</h1>
        <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>


<div class="relative overflow-x-auto shadow-md sm:rounded-lg">
    <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">
                    Season
                </th>
                <th scope="col" class="px-6 py-3">
                    Start Date
                </th>
                <th scope="col" class="px-6 py-3">
                    End Date
                </th>
                <th scope="col" class="px-6 py-3">
                    Winner
                </th>
                <th scope="col" class="px-6 py-3">
                    America's Favorite
                </th>
                <th scope="col" class="px-6 py-3">
                    Runner Up
                </th>
                <?php // if wordpress admin 
                if (current_user_can('administrator')) { ?>
                
                
                <th scope="col" class="relative px-6 py-3">
                    <span class="sr-only">Edit</span>
                </th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
        <?php 
        
        foreach ($results as $result) { ?>

            
            

            <tr class="bg-white border-b dark:bg-gray-900 dark:border-gray-700">
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    <a href="<?=  get_permalink( $result['ID']) ?>"><?= $result['full_name']; ?></a>
                </th>
                
                <td class="px-6 py-4">
                  <?= (new DateTime($result['start_date']))->format('m/d/Y'); ?>
                </td>
                
                <td class="px-6 py-4">
                  <?= (new DateTime($result['end_date']))->format('m/d/Y'); ?>
                </td>

                <td class="px-6 py-4">
                  <a href="<?=  get_permalink( $result['winner_id']) ?>"><?= $result['winner']; ?></a>
                </td>
                <td class="px-6 py-4">
                  <a href="<?=  get_permalink( $result['afp_id']) ?>"><?= $result['afp']; ?></a>
                </td>
                <td class="px-6 py-4">
                <a href="<?=  get_permalink( $result['runner_up_id']) ?>"><?= $result['runner_up']; ?></a>
                </td>
                <td class="px-6 py-4">
                    <a href="<?=  get_permalink( $result['ID']) ?>?bbjMode=edit" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                </td>
            </tr>
            
        

        <?php } ?>
      </tbody>
    </table>
</div>
      </div>
    </div>
    <div class="w-full md:w-[320px]  flex-shrink-0">
		<?php get_template_part("template-parts/sidebar-default"); ?>
		</div>
  </div>
</div>


<?php get_footer(); ?>