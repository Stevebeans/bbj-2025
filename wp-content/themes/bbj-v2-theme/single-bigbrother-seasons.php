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

  <!-- Sections — added in subsequent tasks -->

</main>

<?php
get_footer();
