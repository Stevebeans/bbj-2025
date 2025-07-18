    <?php
    $currentSeason = rwmb_meta("current_season", ["object_type" => "setting"], "bbj_settings");
    $players = $wpdb->get_results(
      'SELECT sn.*, s.full_name FROM wp_bbj_player_season_new AS sn
      LEFT JOIN wp_bbj_seasons s ON s.ID = sn.ID
        WHERE sn.ID = "' .
        $currentSeason .
        '"'
    );

    $playerList = unserialize($players[0]->player_list2);
    $seasonName = $players[0]->full_name;
    ?>
    
    <div class="spoilerBar">
      <div class="playerDiv">
asfdsdfds
      <?php foreach ($playerList as $player):

        // if (isAdmin()):
        //   echo "hey";
        //   echo "<pre>", print_r($player, 1), "</pre>";
        // endif;

        echo "<pre>", print_r($player, 1), "</pre>";
        var_dump($player);

        $addInfo = $wpdb->get_results('SELECT profile_picture, first_name, last_name FROM wp_bbj_players WHERE ID = "' . $player["player_id"] . '"');
        $imgUrl = wp_get_attachment_image_src($addInfo[0]->profile_picture, "profile-picture");
        $firstName = $addInfo[0]->first_name;

        $cHOH = isset($player["current_hoh"]) ? $player["current_hoh"] : null;
        $cPOV = isset($player["current_pov"]) ? $player["current_pov"] : null;
        $cNOM = isset($player["current_nom"]) ? $player["current_nom"] : null;
        $cNO2 = isset($player["current_nom2"]) ? $player["current_nom2"] : null;
        $cJUR = isset($player["current_jury"]) ? $player["current_jury"] : null;
        $cEVI = isset($player["current_evic"]) ? $player["current_evic"] : null;
        $cWIN = isset($player["current_winner"]) ? $player["current_winner"] : null;
        $c2ND = isset($player["current_second"]) ? $player["current_second"] : null;
        $cAFP = isset($player["current_afp"]) ? $player["current_afp"] : null;
        ?>      
          <?php
          $status = "";
          switch (true) {
            case $cHOH:
              $status = "HoH";
              break;
            case $cPOV:
              $status = "PoV";
              break;
            case $cNOM:
              $status = "Nom";
              break;
            case $cNO2:
              $status = "Nom";
              break;
            case $cJUR:
              $status = "Jury";
              break;
            case $cEVI:
              $status = "Evicted";
              break;
            case $cWIN:
              $status = "Winner";
              break;
            case $c2ND:
              $status = "Second";
              break;
            case $cAFP:
              $status = "AFP";
              break;
          }
          ?>
          <div class="profile-contain">
            <div class="header-thumb <?php echo $status; ?>"><a href="<?php the_permalink($player["player_id"]); ?>"><img src="<?php echo $imgUrl[0]; ?>" alt="Player profile for <?php echo $seasonName . " - " . $firstName . " " . $addInfo[0]->last_name; ?>"></a></div>
            <div>
              <h3><?php echo $firstName; ?></h3>
              <h4 class="<?php echo $status; ?>"><?php echo $status; ?></h4>
            </div>
          </div>
      <?php
      endforeach; ?>

      </div>
      <div class="closingBar"></div>
    </div>