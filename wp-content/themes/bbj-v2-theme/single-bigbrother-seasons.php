<?php
/**
 * Single Season Profile — magazine-style redesign.
 *
 * Spec:   docs/superpowers/specs/2026-04-24-season-profile-design.md
 * Design: .claude/claude-design/bbj-season-profile/bbj-home-page/project/BBJ Season Profile.html
 */

if (!have_posts()) {
    get_header();
    echo '<main class="wrap"><p>Season not found.</p></main>';
    get_footer();
    return;
}

the_post();
$post_id = get_the_ID();
$season  = bbj_v2_season_profile_data($post_id);

get_header();
?>

<main class="wrap">

  <!-- Breadcrumb -->
  <nav class="crumb" aria-label="Breadcrumb">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span class="sep">/</span>
    <a href="<?php echo esc_url(home_url('/bigbrother-seasons/')); ?>">Seasons</a><span class="sep">/</span>
    <b><?php echo esc_html($season['title'] ?: get_the_title()); ?></b>
  </nav>

  <!-- HERO -->
  <div class="hero">
    <div class="inner">
      <div>
        <div class="kk"><b>Season <?php echo (int) $season['number']; ?></b>USA · CBS</div>
        <h1><?php echo esc_html($season['name']); ?></h1>
        <?php if (!empty($season['content'])) : ?>
          <p class="sub"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($season['content'] ?? ''), 38)); ?></p>
        <?php endif; ?>
        <div class="stripstats">
          <?php if ($season['winner_name']) : ?>
            <div class="s"><span class="k">Winner</span><span class="v"><?php echo esc_html($season['winner_name']); ?></span></div>
          <?php endif; ?>
          <?php if ($season['prize']) : ?>
            <div class="s"><span class="k">Prize</span><span class="v"><b><?php echo esc_html($season['prize']); ?></b></span></div>
          <?php endif; ?>
          <?php if ($season['days']) : ?>
            <div class="s"><span class="k">Days</span><span class="v"><?php echo (int) $season['days']; ?></span></div>
          <?php endif; ?>
          <?php if ($season['hg_count']) : ?>
            <div class="s"><span class="k">Houseguests</span><span class="v"><?php echo (int) $season['hg_count']; ?></span></div>
          <?php endif; ?>
        </div>
        <div class="actions">
          <a class="b prim" href="<?php echo esc_url(home_url('/feed-updates/')); ?>">&#9654; Live Feed Updates</a>
        </div>
      </div>
      <div class="poster">
        <span class="tag">Season</span>
        <div class="num"><?php echo (int) $season['number']; ?></div>
        <div class="ttl"><?php echo esc_html($season['abbr']); ?></div>
        <div class="chip"><?php echo esc_html($season['abbr']); ?> · <?php echo esc_html(date_i18n('F Y', strtotime($season['start_date'] ?? $season['post_date']))); ?></div>
      </div>
    </div>
  </div>

  <?php $neighbors = bbj_v2_season_profile_neighbors($post_id, 5); ?>
  <?php if (!empty($neighbors)) : ?>
  <div class="switcher">
    <span class="k">Switch season</span>
    <div class="pills">
      <?php foreach ($neighbors as $n) : ?>
        <a href="<?php echo esc_url($n['url']); ?>" class="<?php echo $n['is_current'] ? 'on' : ''; ?>">
          <?php echo esc_html($n['abbreviation']); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <a class="all" href="<?php echo esc_url(home_url('/bigbrother-seasons/')); ?>">All seasons &rarr;</a>
  </div>
  <?php endif; ?>

  <!-- Sections — added in subsequent tasks -->

</main>

<?php
get_footer();
