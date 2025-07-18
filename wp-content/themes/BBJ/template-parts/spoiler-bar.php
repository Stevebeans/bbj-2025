    <div class="playerDiv">
      <?php
      $currentSeason = rwmb_meta("current_season", ["object_type" => "setting"], "bbj_settings");
      $players = $wpdb->get_results(
        'SELECT sn.*, s.full_name FROM wp_bbj_player_season_new AS sn
      LEFT JOIN wp_bbj_seasons s ON s.ID = sn.ID
        WHERE sn.ID = "' .
          $currentSeason .
          '"'
      );

      foreach ($players as $player):
        $unserializedPlayer = unserialize($player->player_list2);

        foreach ($unserializedPlayer as $innerArray):

          $playerId = (int) $innerArray["player_id"];
          $playerData = $wpdb->get_row($wpdb->prepare("SELECT profile_picture, first_name, last_name, official_nickname AS nick FROM wp_bbj_players WHERE ID = %d", $playerId));

          //bbj_log2(print_r($playerData, true));
          $imgUrl = wp_get_attachment_image_src($playerData->profile_picture, "profile-picture");
          $firstName = $playerData->first_name;
          $lastName = $playerData->last_name;
          $displayName = $firstName;
          $fontClass = 'font-mainHead';
          $fontSizeClass = 'text-xs md:text-base';

          if(!empty($playerData->nick)) {
              $displayName = '"' . $playerData->nick . '"';
              $fontClass = 'font-hand';
              $fontSizeClass = 'text-xs md:text-sm';
          }

          $name_map = [
            "afp" => "AFP",
            "winner" => "Winner",
            "hoh" => "HoH",
            "second" => "2ND",
            "pov" => "Veto",
            "jury" => "Jury",
            "nom" => "Nom",
            "evic" => "Evicted",
          ];
          $new_names = [];

          if (isset($innerArray["current_house_status"])) {
            foreach ($innerArray["current_house_status"] as $status):
              if (isset($name_map[$status])) {
                $new_value = $name_map[$status];
              } else {
                $new_value = $status;
              }
              $new_names[] = $new_value;
            endforeach;
          } else {
            $new_names[] = "Active";
          }

          $names_string = implode(", ", $new_names);
          ?>
    
      <div class="profile-contain">
        <div class="profile-outline flex justify-center items-center <?= isset($innerArray["current_house_status"][0]) ? $innerArray["current_house_status"][0] : "active" ?>"><a href="<?php the_permalink($innerArray["player_id"]); ?>"><img src="<?= $imgUrl[0] ?>" alt="Player profile for <?= $firstName . " " . $lastName ?>" class="w-6 h-8 md:w-10 md:h-12 rounded-xl overflow-hidden"></a></div>
        <div class="text-gray-700 <?= $fontClass ?> text-center font-medium <?= $fontSizeClass ?> pt-0.5 leading-4 md:!leading-4 dark:text-gray-200 "><?= $displayName ?></div>
        <div class="text-xs font-mainHead text-center !leading-3 md:!leading-4 tracking-tighter <?= isset($innerArray["current_house_status"][0]) ? $innerArray["current_house_status"][0] : "active" ?>"><?= $names_string ?></div>
      </div>
    

      <?php
        endforeach;
      endforeach;
      ?>
</div>

      </div>
      <div class="closing-bar"></div>
    </div>
