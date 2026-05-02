<?php
/**
 * Player profile · Week by Week display block.
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

$weeks = bbj_v2_player_weeks($player_post_id, $season_post_id);
// Hide weeks where nothing happened to this player (no comp wins, no nom,
// no eviction, no saved_by, no vote cast). If everything is empty, suppress
// the entire section heading too.
$weeks = array_values(array_filter($weeks, 'bbj_v2_player_week_has_activity'));
if (empty($weeks)) {
    return;
}
?>

<section class="player-week-by-week">
  <h3 class="font-osw text-xl text-primary-500 mb-3">
    Week by Week<?php echo $season_label ? ' — ' . esc_html($season_label) : ''; ?>
  </h3>
  <div class="space-y-3">
    <?php foreach ($weeks as $w): ?>
      <article class="week-card border border-stone-200 bg-white p-4 rounded">
        <header class="flex items-baseline justify-between gap-3 mb-1">
          <h4 class="font-osw text-base text-stone-900">Week <?php echo (int) $w['week_num']; ?></h4>
          <span class="font-mono text-[11px] text-stone-500"><?php echo esc_html($w['start_date']); ?> – <?php echo esc_html($w['end_date']); ?></span>
        </header>

        <div class="flex flex-wrap gap-1 mb-2">
          <?php foreach ($w['comps'] as $c): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-amber-100 text-amber-900">
              <?php echo esc_html($c['name']); ?>
            </span>
          <?php endforeach; ?>
          <?php if ((int) $w['nom']): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-rose-100 text-rose-900">Nom</span>
          <?php endif; ?>
          <?php if (!empty($w['saved_by_label'])): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-green-100 text-green-900">
              Saved by <?php echo esc_html($w['saved_by_label']); ?>
            </span>
          <?php endif; ?>
          <?php if ((int) $w['evicted']): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-stone-700 text-white">Evicted</span>
          <?php endif; ?>
          <?php if (!empty($w['voted_for_name']) && !((int) $w['evicted'])): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-mono text-stone-500">
              Voted to evict: <?php echo esc_html($w['voted_for_name']); ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if (!empty($w['summary'])): ?>
          <p class="text-sm text-stone-700"><?php echo esc_html($w['summary']); ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
