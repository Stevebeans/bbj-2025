<?php
function get_top_competition_data($competition_type)
{
    global $wpdb;
    $stat_table = $wpdb->prefix . "bbj_play_season_rel";
    $player_table = $wpdb->prefix . "bbj_players";

    $query = "
        SELECT s.player_id, p.first_name, p.official_nickname, SUM(s.total_{$competition_type}) as total_sum
        FROM " . $stat_table . " as s
        LEFT JOIN " . $player_table . " as p ON s.player_id = p.ID
        GROUP BY s.player_id
        ORDER BY total_sum DESC
        LIMIT 10;
    ";
    return $wpdb->get_results($query);
}


function render_competition_leaderboard($competition_data, $competition_title)
{
    echo '<div class="border border-slate-200 w-full sm:w-[200px] mr-2 mb-2 grid grid-cols-2">';

    $counter = 1;
    foreach ($competition_data as $data) {
        $id = $data->player_id;
        $firstName = $data->first_name;
        $nickName = $data->official_nickname;
        $profile_pic = rwmb_meta("profile_picture", ["size" => "profile-picture"], $id);

        $displayName = ($nickName) ? $nickName : $firstName;
        $displayName = esc_html(ucwords(strtolower($displayName)));

        if ($counter == 1) {
            echo render_rank_1($id, $displayName, $data->total_sum, $profile_pic["url"], $competition_title);
        } elseif ($counter <= 3) {
            echo render_rank_2_to_3($id, $displayName, $data->total_sum, $profile_pic["url"]);
        } else {
            echo render_rank_4_to_10($id, $displayName, $data->total_sum);
        }

        $counter++;
    }

    echo '</div>';
}

function render_rank_1($id, $displayName, $total_sum, $profile_pic_url, $competition_title)
{
    return "
        <div class='col-span-2 '>
            <div class='bg-primary500 text-white font-ibm py-1 px-2 text-center'>{$competition_title}</div>
            <div class='relative'>
                <a href='" . get_permalink($id) . "'>
                <img src='{$profile_pic_url}' class='w-[100%] h-[200px]' alt=''>
                <div class='absolute bottom-0 w-full flex justify-between bg-gray-800 bg-opacity-40 text-white px-1 text-lg'>
                    <div>{$displayName}</div>
                    <div class='font-bold'>{$total_sum}</div>
                </div>
                </a>
            </div>
        </div>";
}

function render_rank_2_to_3($id, $displayName, $total_sum, $profile_pic_url)
{
    return "
        <div class='relative'>
            <a href='" . get_permalink($id) . "'>
            <img src='{$profile_pic_url}' class='w-[100%] h-[100px]' alt=''>
            <div class='absolute bottom-0 w-full flex justify-between bg-gray-800 bg-opacity-40 text-white px-1 text-sm'>
                <div>{$displayName}</div>
                <div class='font-bold'>{$total_sum}</div>
            </div>
            </a>
        </div>";
}

function render_rank_4_to_10($id, $displayName, $total_sum)
{
    return "
        <div class='col-span-2 px-1 py-0.5 even:bg-gray-50 text-sm w-full hover:bg-gray-100'>
        <a href='" . get_permalink($id) . "' class='w-full flex justify-between'>
        <div>{$displayName}</div>
        <div>{$total_sum}</div>
        </a>
        </div>";
}
        
$top_comp = get_top_competition_data('comp');
$top_hoh = get_top_competition_data('hoh');
$top_pov = get_top_competition_data('pov');
$top_nom = get_top_competition_data('nom');
$top_votes = get_top_competition_data('votes');
$top_saved = get_top_competition_data('saved');
        
get_header();
?>
     
<div class="bbj-container-inner">
  <div class="mt-2 flex w-full flex-col bg-white lg:flex-row overflow-hidden">
    <div class="flex-grow">
      <div class="p-2">
        <h1 class="font-mainHead text-2xl text-primary500">Big Brother Stats</h1>
        <div class="h-[6px] bg-second500 w-[100px] mb-4"></div>
        <div class="flex flex-col sm:flex-row sm:flex-wrap">
        <?php render_competition_leaderboard($top_comp, 'Total Comp Wins'); ?>  
        <?php render_competition_leaderboard($top_hoh, 'Head of Household'); ?>
        <?php render_competition_leaderboard($top_pov, 'Power of Veto'); ?>
        <?php render_competition_leaderboard($top_nom, 'Nominations'); ?>
        <?php render_competition_leaderboard($top_votes, 'Votes Received'); ?>
        <?php render_competition_leaderboard($top_saved, 'Saved From Block'); ?>
        </div>
      </div>
    </div>
    
    <div class="w-full md:w-[320px] flex-shrink-0">
    <?php get_template_part("template-parts/sidebar-default"); ?>
    </div>
  </div>
</div>
<?php get_footer(); ?>