<?php 

function my_plugin_block_categories( $categories, $post ) {
  return array_merge(
      $categories,
      array(
          array(
              'slug' => 'bbj-blocks',
              'title' => __( 'BBJ Blocks', 'my-plugin' ),
              'icon'  => 'wordpress',
          ),
      )
  );
}
add_filter( 'block_categories_all', 'my_plugin_block_categories', 10, 2 );



function s_results_week($season, $type)
{

// bbj_log2(print_r($season, true));
// bbj_log2(print_r($type, true));

  if ($type == 'player-list') {

    $winner = $season->winner;
    $runner_up = $season->runner_up;
    $afp = $season->afp;
    $evicted = $season->evicted;
    $jury = $season->jury;
  } else if ($type == 'player-page') {

  $winner = $season[0]->winner;
  $runner_up = $season[0]->runner_up;
  $afp = $season[0]->afp;
  $evicted = $season[0]->evicted;
  $jury = $season[0]->jury;
  }




  switch(true) {
    case $winner == 1:
      $result = '<div class="result-winner">Winner</div>';
      break;
    case $runner_up == 1:
      $result = '<div class="result-runner-up">Runner Up</div>';
      break;
    case $afp == 1:
      $result = "<div class='result-afp'>America's Fav</div>";
      break;
    case $jury == 1:
      $result = '<div class="result-jury">Jury</div>';
      break;
    case $evicted == 1:
      $result = '<div class="result-evicted">Evicted</div>';
      break;
    default:
      $result = '<div class="result-tbd">TBD</div>';
  }

  return $result;
  
}




function days_calc_new($enter, $exit)
{
  $earlier = new DateTime($enter);
  $later = new DateTime($exit);

  $abs_diff = $later->diff($earlier)->format("%a"); //3

  return $abs_diff;
}

function new_age_calc($dob, $start) {

  // bbj_log2(print_r($dob, true));
  // bbj_log2(print_r($start, true));

  $dob = new DateTime($dob);
  $start = new DateTime($start);
  $age = $start->diff($dob)->y;
  return $age;
}

function season_percentage_calc($start, $end, $evict) {

  $total_days = days_calc_new($start, $end);
  $evict_days = days_calc_new($start, $evict);

  $percentage = round(($evict_days / $total_days) * 100);

  //bbj_log2(print_r($percentage, true));

  return $percentage;

  // bbj_log2(print_r($start, true));
  // bbj_log2(print_r($end, true));
  // bbj_log2(print_r($evict, true));

}


function current_age_calc($dob) {

  $dob = new DateTime($dob);
  $today = new DateTime();
  $age = $today->diff($dob)->y;
  return $age;
}


function s_results_overall($season)
{

  //bbj_log2(print_r($season, true));
  $results = [];

  // Check if the $season array contains a winner
  if (in_array(1, array_column($season, 'winner'))) {
    $results[] = 1;
  }

  // Check if the $season array contains an afp
  if (in_array(1, array_column($season, 'afp'))) {
    $results[] = 2;
  }

  // Check if the $season array contains a runner_up
  if (in_array(1, array_column($season, 'runner_up'))) {
    $results[] = 3;
  }

  // If no winner, runner_up, or afp is found, return an empty array
  return $results;
}


function w_check_season($player) {
  $results = array();
    
  if ( $player->winner ) {
      $results[] = 1; // winner
  }
  
  if ( $player->afp ) {
      $results[] = 2; // AFP
  }
  
  if ( $player->runner_up ) {
      $results[] = 3; // runner-up
  }
  
  if ( $player->jury ) {
      $results[] = 4; // juror
  }
  
  if ( $player->evicted ) {
      $results[] = 5; // evicted
  }
  
  return $results;

}