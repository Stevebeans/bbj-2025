<?php
/**
 * Single Player Profile — editorial redesign (Sprint B).
 *
 * Spec: docs/superpowers/specs/2026-04-24-player-profile-design.md
 * Design: .claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html
 */

if (!have_posts()) {
    get_header();
    echo '<main class="wrap"><p>Player not found.</p></main>';
    get_footer();
    return;
}

the_post();
$post_id  = get_the_ID();
$player   = bbj_v2_player_profile_player_data($post_id);
$seasons  = bbj_v2_player_profile_seasons($post_id);
$totals   = bbj_v2_player_profile_career_totals($seasons);
$derived  = bbj_v2_player_profile_derive($player ?: [], $seasons);
$latest   = $derived['latest_season'] ?? null;

if (!$player) {
    get_header();
    echo '<main class="wrap"><p>Player data not available.</p></main>';
    get_footer();
    return;
}

get_header();
?>

<main class="wrap">

  <!-- Breadcrumb -->
  <nav class="crumb" aria-label="Breadcrumb">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span class="sep">/</span>
    <a href="<?php echo esc_url(home_url('/houseguests/')); ?>">Houseguests</a><span class="sep">/</span>
    <?php if ($latest && !empty($latest['season_slug'])) : ?>
      <a href="<?php echo esc_url(home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/')); ?>">
        <?php echo esc_html($latest['season_abbr'] ?: $latest['season_name']); ?>
      </a>
      <span class="sep">/</span>
    <?php endif; ?>
    <b><?php echo esc_html($player['full_name'] ?: get_the_title()); ?></b>
  </nav>

  <!-- HERO -->
  <div class="hero">
    <div class="inner">
      <div class="portrait">
        <?php if ($player['profile_picture']) : ?>
          <?php echo wp_get_attachment_image($player['profile_picture'], 'bbj_v2_profile_image', false, [
              'alt'   => sprintf('%s, %s houseguest', $player['full_name'], $latest['season_abbr'] ?? 'Big Brother'),
              'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
          ]); ?>
        <?php endif; ?>
        <?php if (!empty($derived['is_afp_anywhere']) && $latest) : ?>
          <div class="badge-placement">AFP<small>Season <?php echo esc_html(preg_replace('/^BB/', '', $latest['season_abbr'] ?? '')); ?></small></div>
        <?php endif; ?>
      </div>

      <div class="meta">
        <span class="kk"><span class="dot"></span><?php echo esc_html($derived['status_kicker']); ?></span>
        <h1><?php echo esc_html($player['full_name'] ?: get_the_title()); ?></h1>
        <?php if (!empty($player['nickname'])) : ?>
          <div class="nick">&ldquo;<?php echo esc_html($player['nickname']); ?>&rdquo;</div>
        <?php endif; ?>
        <div class="hgmeta">
          <?php if (!empty($player['hometown'])) : ?>
            <span><span class="k">From</span><b><?php echo esc_html($player['hometown']); ?></b></span>
          <?php endif; ?>
          <?php if (!empty($derived['age_in_house'])) : ?>
            <span><span class="k">Age</span><b><?php echo (int) $derived['age_in_house']; ?></b></span>
          <?php endif; ?>
          <?php if (!empty($player['occupation'])) : ?>
            <span><span class="k">Occupation</span><b><?php echo esc_html($player['occupation']); ?></b></span>
          <?php endif; ?>
          <?php if (!empty($derived['days_in_house'])) : ?>
            <span><span class="k">Days in house</span><b><?php echo (int) $derived['days_in_house']; ?></b></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($derived['chips'])) : ?>
          <div class="tags">
            <?php foreach ($derived['chips'] as $chip) : ?>
              <span class="t <?php echo esc_attr($chip['class']); ?>"><?php echo esc_html($chip['text']); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="actions">
        <span class="b prim" aria-disabled="true" title="Coming soon">⇆ Compare</span>
        <?php if ($latest && !empty($latest['season_slug'])) : ?>
          <a class="b alt" href="<?php echo esc_url(home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/')); ?>">
            ↗ View <?php echo esc_html($latest['season_abbr'] ?: 'Season'); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- BIO STRIP -->
  <?php
  $strip_cells = array_filter([
    !empty($player['hometown'])            ? ['k' => 'Hometown',      'v' => $player['hometown']]                         : null,
    !empty($player['occupation'])          ? ['k' => 'Occupation',    'v' => $player['occupation']]                       : null,
    !empty($derived['age_in_house'])       ? ['k' => 'Age in house',  'v' => (string) $derived['age_in_house']]           : null,
    !empty($derived['placement_label'])    ? ['k' => 'Placement',     'v' => $derived['placement_label']]                 : null,
    ($latest && !empty($derived['eviction_day']))
      ? ['k' => 'Eviction', 'v' => sprintf('Day %d · Week %d', $derived['eviction_day'], $derived['eviction_week'])]
      : ($latest ? ['k' => 'Status', 'v' => 'Still in house'] : null),
  ]);
  if (!empty($strip_cells)) : ?>
    <div class="biostrip" style="grid-template-columns:repeat(<?php echo count($strip_cells); ?>,1fr);">
      <?php foreach ($strip_cells as $cell) : ?>
        <div class="c"><div class="k"><?php echo esc_html($cell['k']); ?></div><div class="v"><?php echo esc_html($cell['v']); ?></div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="grid">

    <!-- MAIN COLUMN -->
    <div>
      <!-- BIO & BACKGROUND -->
      <section>
        <div class="sech">
          <h2>Bio &amp; Background</h2>
          <span class="sub">The long version</span>
        </div>
        <div class="biocard">
          <?php
          $glance = array_filter([
              !empty($player['hometown'])      ? ['k' => 'Hometown',   'v' => $player['hometown']]                                : null,
              !empty($player['date_of_birth'])
                  ? ['k' => 'Birthday',   'v' => date_i18n('M j, Y', strtotime($player['date_of_birth']))]
                  : null,
              !empty($player['occupation'])    ? ['k' => 'Occupation', 'v' => $player['occupation']]                              : null,
          ]);
          ?>
          <?php if (!empty($glance)) : ?>
            <aside class="at-a-glance">
              <h4>At a glance</h4>
              <dl>
                <?php foreach ($glance as $row) : ?>
                  <dt><?php echo esc_html($row['k']); ?></dt>
                  <dd><?php echo esc_html($row['v']); ?></dd>
                <?php endforeach; ?>
              </dl>
            </aside>
          <?php endif; ?>
          <div class="copy">
            <?php
            $content = get_the_content();
            if (trim($content) === '') {
                echo '<p class="lead">Bio coming soon.</p>';
            } else {
                echo apply_filters('the_content', $content);
            }
            ?>
          </div>
        </div>
      </section>
    </div>

    <!-- SIDEBAR -->
    <aside>
      <div class="stick">
        <!-- Sidebar cards land in Task 10 -->
      </div>
    </aside>

  </div>

</main>

<?php get_footer(); ?>
