<?php

function bbj_stats_shortcode()
{
  global $wpdb;

  $sql = "SELECT play.ID, play.first_name, play.last_name, play.official_nickname, stats.total_hoh, stats.total_pov, stats.total_nom, stats.total_votes
  FROM wp_bbj_play_season_rel as stats
  LEFT JOIN wp_bbj_players as play
  ON stats.player_id = play.ID";
  $results = $wpdb->get_results($sql, ARRAY_A);


  // Prepare the data for the stats section
  $most_votes_received = [];
  $most_nominated = [];
  $most_veto_wins = [];
  $most_hoh_wins = [];

  foreach ($results as $result) {

    $full_name = "{$result['first_name']} {$result['last_name']}";

    // Most Votes Received
    $most_votes_received[] = [
      "name" => $full_name,
      "votes" => $result['total_votes'],
    ];

    // Most Nominated
    $most_nominated[] = [
      "name" => $full_name,
      "nominations" => $result['total_nom'],
    ];

    // Most Veto Wins
    $most_veto_wins[] = [
      "name" => $full_name,
      "veto_wins" => $result['total_pov'],
    ];

    // Most HOH Wins
    $most_hoh_wins[] = [
      "name" => $full_name,
      "hoh_wins" => $result['total_hoh'],
    ];
  }

  // Sort the data for each category
  usort($most_votes_received, function ($a, $b) {
    return $b["votes"] - $a["votes"];
  });
  usort($most_nominated, function ($a, $b) {
    return $b["nominations"] - $a["nominations"];
  });
  usort($most_veto_wins, function ($a, $b) {
    return $b["veto_wins"] - $a["veto_wins"];
  });
  usort($most_hoh_wins, function ($a, $b) {
    return $b["hoh_wins"] - $a["hoh_wins"];
  });

  // Limit the data to the top 5 for each category
  $most_votes_received = array_slice($most_votes_received, 0, 5);
  $most_nominated = array_slice($most_nominated, 0, 5);
  $most_veto_wins = array_slice($most_veto_wins, 0, 5);
  $most_hoh_wins = array_slice($most_hoh_wins, 0, 5);

  // Generate the HTML for the stats section
  ob_start();
  ?>
  <!-- Stats section -->
<div style="display: grid; grid-template-columns: 1fr; gap: 2rem; padding: 1rem;">
  <?php
  $categories = [["title" => "Most Votes Received", "data" => $most_votes_received, "label" => "votes"], ["title" => "Most Nominated", "data" => $most_nominated, "label" => "nominations"], ["title" => "Most Veto Wins", "data" => $most_veto_wins, "label" => "veto_wins"], ["title" => "Most HOH Wins", "data" => $most_hoh_wins, "label" => "hoh_wins"]];

  foreach ($categories as $category) { ?>
    <div style="background-color: white; border: 1px solid #cbd5e0; border-radius: 0.5rem; overflow: hidden; margin: 0.5rem;">
      <h3 style="font-size: 1rem; font-weight: 600; background-color: #35546e; color: white; padding: 0.5rem;"><?php echo $category["title"]; ?></h3>
      <table style="width: 100%; font-size: 0.875rem; text-align: left; color: #718096;">
        <thead style="font-size: 0.75rem; color: #4a5568; background-color: #edf2f7;">
          <tr>
            <th style="width: 100%; text-align: left; padding: 0.5rem; text-transform: uppercase;">Name</th>
            <th style="width: 50px; padding: 0.5rem; text-align: center; text-transform: uppercase;">Wins</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($category["data"] as $index => $item) { ?>
            <tr style="<?php echo ($index % 2 === 0) ? 'background-color: #f7fafc;' : 'background-color: white;'; ?>">
              <td style="padding: 0.5rem;"><?php echo $item["name"]; ?></td>
              <td style="padding: 0.5rem; text-align: center;"><?php echo $item[$category["label"]]; ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
</div>

  <?php return ob_get_clean();
}

add_shortcode("bbj_stats", "bbj_stats_shortcode");