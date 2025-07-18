<?php 




function show_player_table($entries, $player_array, $season_id) {

  bbj_log2(print_r($entries, true));
    global $wpdb;

    //echo '<pre>',print_r($entries,1),'</pre>';
    // Display entries in a table
    if (!empty($entries)) {
        ?>
        <h2>Player-Season Relationships</h2>
        <table>
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Winner</th>
                    <th>Runner-up</th>
                    <th>AFP</th>
                    <th>Jury</th>
                    <th>Evicted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($entries as $entry) {
                ?>
                    <tr>
                        <td><?php echo esc_html($player_array[$entry->player_id]); ?></td>
                        <td><?php echo $entry->winner ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $entry->runner_up ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $entry->afp ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $entry->jury ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $entry->evicted ? 'Yes' : 'No'; ?></td>
                        <td>
                            <form action="" method="post">
                                <input type="hidden" name="delete_id" value="<?php echo $entry->id; ?>">
                                <button type="submit"><span class="dashicons dashicons-dismiss"></span></button>
                            </form>
                        </td>
                    </tr>

                <?php
                }
                ?>
            </tbody>
        </table>

        <?php
    }

    ?>


<form action="" method="post">
    <table>
  <tr>
    <td>Player:</td>
    <td>
      <select name="player">
        <?php foreach ($player_array as $key => $player) { ?>
          <option value="<?= $key ?>"><?= $player ?></option>
        <?php } ?>
      </select>
    </td>
    <td>Winner:</td>
    <td>
      <input type="checkbox" name="winner">
    </td>
    <td>Runner-up:</td>
    <td>
      <input type="checkbox" name="runner_up">
    </td>
    <td>AFP:</td>
    <td>
      <input type="checkbox" name="afp">
    </td>
    <td>Jury:</td>
    <td>
      <input type="checkbox" name="jury">
    </td>
    <td>Evicted:</td>
    <td>
      <input type="checkbox" name="evicted">
    </td>
    <td><input type="submit" name="submit" value="Add"></td>
  </tr>
</table>
</form>

        <Br><br>

        <a href="/wp-admin/admin.php?page=bbj-tools-edit-player-seasons&season=<?= $season_id ?>&method=edit">Edit Players </a> <br />
<a href="/wp-admin/admin.php?page=bbj-tools-seasons">Back to seasons</a>
<?php
}


function show_edit_table($entries, $player_array, $season_id) {
    ?>
    <h2>Player-Season Relationships</h2>
    <?php 
    $season_date = get_season ($season_id);

    $s_start = $season_date->start_date;
    $s_end = $season_date->end_date;

    echo $s_start;
    ?>
    <form action="" method="post">
        <table>
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Finish</th>
                    <th>Winner</th>
                    <th>Runner-up</th>
                    <th>AFP</th>
                    <th>Jury</th>
                    <th>Evicted</th>
                    <th>Evicted Date</th>
                </tr>
            </thead>


            <tbody>
                <?php 
                foreach ($entries as $entry):
                  bbj_log2(print_r($entry, true));
                ?>
                    <tr>

                    <td width="200"><?= esc_html($player_array[$entry->player_id]); ?></td>
                    <td width="50" style="text-align: center">
                        <select name="finish_<?= $entry->player_id ?>">
                            <?php for ($i = 1; $i <= 20; $i++): ?>
                                <option value="<?= $i ?>" <?= $entry->finish == $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </td>
                    <td width="50" style="text-align: center"><input type="checkbox" name="winner_<?= $entry->player_id ?>" <?= $entry->winner ? 'checked': '' ?> id=""></td>
                    <td width="100" style="text-align: center"><input type="checkbox" name="runner_up_<?= $entry->player_id ?>" <?= $entry->runner_up ? 'checked': '' ?> id=""></td>
                    <td width="50" style="text-align: center"><input type="checkbox" name="afp_<?= $entry->player_id ?>" <?= $entry->afp ? 'checked': '' ?> id=""></td>
                    <td width="50" style="text-align: center"><input type="checkbox" name="jury_<?= $entry->player_id ?>" <?= $entry->jury ? 'checked': '' ?> id=""></td>
                    <td width="50" style="text-align: center"><input type="checkbox" name="evicted_<?= $entry->player_id ?>" <?= $entry->evicted ? 'checked': '' ?> id=""></td>
                    <?php
                      $evict_date = $entry->evict_date;
                      if ($evict_date == '0000-00-00') {
                          $evict_date = $s_end;
                      }
                      ?>
                    <td><input type="date" name="evict_date_<?= $entry->player_id ?>" value="<?= $evict_date ?>" id="" min="<?= $s_start ?>" max="<?= $s_end ?>"></td>
                    </tr>
               <?php endforeach; ?>
            </tbody>

        </table>
        <button type="submit" name="update_entries" value="Update">Update</button>
    </form>
    

    <Br><br>

    <a href="/wp-admin/admin.php?page=bbj-tools-edit-player-seasons&season=<?= $season_id ?>&method=add">Add Players </a> <br />
<a href="/wp-admin/admin.php?page=bbj-tools-seasons">Back to seasons</a>
    
    <?php
}











