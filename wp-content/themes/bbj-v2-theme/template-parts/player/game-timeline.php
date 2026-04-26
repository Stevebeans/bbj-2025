<?php
/**
 * Player profile · Game Timeline (week-by-week power map).
 *
 * Expected args:
 *   - player_post_id (int)
 *   - season_post_id (int)
 *   - season_label   (string)  — e.g. "Big Brother 26"
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = $args ?? [];
$player_post_id = (int) ($args['player_post_id'] ?? 0);
$season_post_id = (int) ($args['season_post_id'] ?? 0);
$season_label   = (string) ($args['season_label'] ?? '');

if ($player_post_id <= 0 || $season_post_id <= 0) {
    return;
}

$data = bbj_v2_player_game_timeline_data($player_post_id, $season_post_id);
if (!$data['has_activity'] || $data['max_week'] <= 0) {
    return;
}

$max_week = (int) $data['max_week'];
// +1 column for FIN
$total_cols = $max_week + 1;
?>

<section class="bbj-tl-section">
  <div class="sech">
    <h2>Game Timeline<?php echo $season_label ? ' <small>— ' . esc_html($season_label) . '</small>' : ''; ?></h2>
    <span class="sub">Week-by-week power map</span>
  </div>

  <div class="bbj-tl">
    <!-- Ruler -->
    <div class="bbj-tl-ruler" style="--bbj-tl-cols: <?php echo $total_cols; ?>;">
      <?php for ($i = 1; $i <= $max_week; $i++): ?>
        <span>W<?php echo $i; ?></span>
      <?php endfor; ?>
      <span>Fin</span>
    </div>

    <?php
    $rows = [
      ['label' => 'HoH',      'class' => 'hoh', 'weeks' => $data['cells']['hoh']],
      ['label' => 'Veto',     'class' => 'pov', 'weeks' => $data['cells']['pov']],
      ['label' => 'On block', 'class' => 'nom', 'weeks' => $data['cells']['nom']],
    ];
    ?>
    <?php foreach ($rows as $row): ?>
      <div class="bbj-tl-row">
        <div class="bbj-tl-lbl"><?php echo esc_html($row['label']); ?></div>
        <div class="bbj-tl-track" style="--bbj-tl-cols: <?php echo $total_cols; ?>;">
          <?php foreach ($row['weeks'] as $wn):
              // Convert 1-based week_num to 0-based column index
              $col = (int) $wn - 1;
              if ($col < 0 || $col >= $max_week) continue;
          ?>
            <div class="bbj-tl-cell bbj-tl-cell--<?php echo esc_attr($row['class']); ?>"
                 style="left:calc(<?php echo $col; ?> / <?php echo $total_cols; ?> * 100%);
                        width:calc(1 / <?php echo $total_cols; ?> * 100% - 4px);">
              W<?php echo (int) $wn; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Finale row -->
    <div class="bbj-tl-row">
      <div class="bbj-tl-lbl">Finale</div>
      <div class="bbj-tl-track" style="--bbj-tl-cols: <?php echo $total_cols; ?>;">
        <?php if (!empty($data['finale'])):
            $col = $max_week; // FIN column
        ?>
          <div class="bbj-tl-cell bbj-tl-cell--<?php echo esc_attr($data['finale']['class']); ?>"
               style="left:calc(<?php echo $col; ?> / <?php echo $total_cols; ?> * 100%);
                      width:calc(1 / <?php echo $total_cols; ?> * 100% - 4px);">
            <?php echo esc_html($data['finale']['label']); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Legend -->
    <div class="bbj-tl-legend">
      <span><i class="bbj-tl-sw bbj-tl-sw--hoh"></i>HoH week</span>
      <span><i class="bbj-tl-sw bbj-tl-sw--pov"></i>Veto win</span>
      <span><i class="bbj-tl-sw bbj-tl-sw--nom"></i>On the block</span>
      <span><i class="bbj-tl-sw bbj-tl-sw--winner"></i>Winner / America's Favorite</span>
    </div>
  </div>
</section>
