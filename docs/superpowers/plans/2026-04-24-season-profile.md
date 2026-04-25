# Season Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the legacy single-bigbrother-seasons.php template on bbj-v2-theme with a magazine-style profile page rendering the 9 MVP sections + 4-widget sidebar from the approved spec.

**Architecture:** Mirror the player profile pattern shipped 2026-04-24: a single PHP template (`single-bigbrother-seasons.php`), a data-helper file with normalized array returns (`inc/season-profile-data.php`), a page-scoped stylesheet (`css/single-bigbrother-seasons.css`) registered conditionally in `enqueue.php`, and a small vanilla-JS scroll-spy script for the sticky tab nav. Reuses the data-drift conventions documented in `memory/references/bbj_data_schema.md` (LEFT JOIN companion tables, `id OR post_id` matching for `wp_bbj_players`, `finish_place` as canonical column).

**Tech Stack:** PHP 8.x · WordPress · Tailwind v3 (compiled to `build/style.css`) · vanilla JS (IntersectionObserver) · MySQL (custom tables `wp_bbj_v2_player_season`, `wp_bbj_weeks`, `wp_bbj_weeks_players`, `wp_bbj_seasons`, `wp_bbj_players`).

**Test approach:** No PHPUnit/JS test framework in this codebase. Each task uses **PHP lint** (`php -l file.php`) + **curl smoke test** (against `bbj.localhost` first, then post-deploy against `stg-wp.bigbrotherjunkies.com`) as the per-task verification. Output is checked with `grep` for expected markers (section headings, populated values, etc).

**Spec:** `docs/superpowers/specs/2026-04-24-season-profile-design.md` — read this before starting.
**Design source:** `.claude/claude-design/bbj-season-profile/bbj-home-page/project/BBJ Season Profile.html` — the visual reference. CSS at lines 10-312, HTML body at 316-723.

---

## File Structure

```
wp-content/themes/bbj-v2-theme/
├── single-bigbrother-seasons.php           [REWRITE — currently legacy]
├── inc/
│   ├── enqueue.php                         [MODIFY — add CSS + fonts conditional]
│   └── season-profile-data.php             [CREATE — helper functions]
├── css/
│   └── single-bigbrother-seasons.css       [CREATE — port from design CSS]
└── src/js/
    └── season-profile.js                   [CREATE — sticky tab scroll-spy]
```

**Helper function signatures** (all in `season-profile-data.php`, namespaced `bbj_v2_season_profile_*`):

```php
function bbj_v2_season_profile_data(int $post_id): array;
function bbj_v2_season_profile_cast(int $post_id): array;
function bbj_v2_season_profile_evictions(int $post_id): array;
function bbj_v2_season_profile_comps(int $post_id): array;
function bbj_v2_season_profile_top_feed_updates(int $post_id, int $limit = 9): array;
function bbj_v2_season_profile_articles(int $post_id, int $limit = 4): array;
function bbj_v2_season_profile_neighbors(int $post_id, int $window = 5): array;
function bbj_v2_season_profile_ordinal(int $n): string;
```

---

### Task 1: Scaffolding + scoped CSS + enqueue + empty-page render

Create the four new files with empty-but-renderable content. Goal: hitting `/bigbrother-seasons/big-brother-27/` on local returns a 200 with the bbj-v2-theme chrome (header, footer) and an empty `<main>` shell. No data yet.

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php`
- Create: `wp-content/themes/bbj-v2-theme/css/single-bigbrother-seasons.css`
- Create: `wp-content/themes/bbj-v2-theme/src/js/season-profile.js`
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php` (around lines 100-130)
- Rewrite: `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php`

- [ ] **Step 1: Create `inc/season-profile-data.php` with file scaffold**

```php
<?php
/**
 * Season profile data helpers.
 *
 * All functions are prefixed bbj_v2_season_profile_* and live in the global
 * namespace (matching the rest of this theme). They return normalized arrays
 * the template can iterate without further processing.
 *
 * Drift conventions (per memory/references/bbj_data_schema.md):
 * - LEFT JOIN wp_bbj_seasons (BB22+ has no row)
 * - LEFT JOIN wp_bbj_players ON (post_id = X OR (id = X AND post_id = 0))
 * - Use `finish_place` (no prefix) for placement
 * - Prefer finish_place === 1/2 over season_winner/runner_up post-pointers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ordinal formatter: 1 → "1st", 2 → "2nd", 3 → "3rd", etc.
 */
function bbj_v2_season_profile_ordinal(int $n): string
{
    if ($n <= 0) return (string) $n;
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) return $n . 'th';
    switch ($n % 10) {
        case 1:  return $n . 'st';
        case 2:  return $n . 'nd';
        case 3:  return $n . 'rd';
        default: return $n . 'th';
    }
}
```

- [ ] **Step 2: Create `css/single-bigbrother-seasons.css` by porting design CSS**

Copy the entire `<style>` block from `.claude/claude-design/bbj-season-profile/bbj-home-page/project/BBJ Season Profile.html` lines 11-311 (everything between `<style>` and `</style>`) into the new file. **Strip the `:root` block (lines 11-16)** — those CSS variables are already declared in the bbj-v2-theme's main stylesheet via Tailwind. Strip the `*{box-sizing...}`, `html,body`, `a`, `button`, and `.wrap` rules (lines 17-21) — they conflict with theme globals. Strip the `.topbar`, `header.site`, `nav.primary`, `footer` rules (lines 24-30, 31-49, 288-295) — the theme's `header.php` and `footer.php` provide these.

What stays: `.crumb`, `.hero`, `.switcher`, `.sectionnav`, `.grid`, `section`, `.sech`, `.overview`, `.podium`, `.castgrid`, `.evtable`, `.twists`, `.comptable`, `.memories`, `.ratings`, `.artrow`, `aside`, `.stick`, `.card`, `.toc`, `.facts-card`, `.records`, `.poll`, `.seas-mini`, `.ad`, plus the `@media (max-width:1000px)` block.

- [ ] **Step 3: Create `src/js/season-profile.js` — empty file with header**

```js
/**
 * Season profile — sticky tab nav scroll-spy.
 * Implementation lands in Task 4. This file is enqueued conditionally on
 * single-bigbrother-seasons pages.
 */
(function () {
  'use strict';
  // Scroll-spy logic — TBD Task 4
})();
```

- [ ] **Step 4: Modify `inc/enqueue.php` — register CSS + JS + extra fonts on season profile pages**

Find the block that enqueues the player-profile CSS (currently around line 105-112) and add an analogous block immediately after:

```php
    // Single season profile — scoped stylesheet + scroll-spy JS.
    if (is_singular('bigbrother-seasons')) {
        wp_enqueue_style(
            'bbj-v2-single-season',
            BBJ_V2_THEME_URL . '/css/single-bigbrother-seasons.css',
            [],
            bbj_v2_asset_ver('/css/single-bigbrother-seasons.css')
        );
        wp_enqueue_script(
            'bbj-v2-season-profile',
            BBJ_V2_THEME_URL . '/src/js/season-profile.js',
            [],
            bbj_v2_asset_ver('/src/js/season-profile.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }
```

Then in `bbj_v2_preload_fonts()` (around line 122-142), find the player-profile font conditional and add an OR for season pages — the design uses the same Source Serif 4 + Inter Tight + IBM Plex Mono trio:

```php
    // Editorial fonts for the player + season profiles (retiring Yanone on those pages).
    if (is_singular('bigbrother-players') || is_singular('bigbrother-seasons')) {
        $base_url .= '&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=Inter+Tight:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500';
    }
```

- [ ] **Step 5: Rewrite `single-bigbrother-seasons.php` — minimal scaffold**

Back the existing file up first (`mv single-bigbrother-seasons.php single-bigbrother-seasons.php.legacy.bak`), then create the new file:

```php
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

require_once get_theme_file_path('inc/season-profile-data.php');

get_header();
?>

<main class="wrap">

  <!-- Breadcrumb -->
  <div class="crumb">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span class="sep">/</span>
    <a href="<?php echo esc_url(home_url('/bigbrother-seasons/')); ?>">Seasons</a><span class="sep">/</span>
    <b><?php echo esc_html($season['title'] ?: get_the_title()); ?></b>
  </div>

  <!-- Sections — added in subsequent tasks -->

</main>

<?php
get_footer();
```

Add a stub `bbj_v2_season_profile_data()` to `inc/season-profile-data.php` so the require above works:

```php
/**
 * Fetch the core season record. Always returns a shape — falls back to
 * wp_posts.post_title when wp_bbj_seasons has no row (BB22+).
 */
function bbj_v2_season_profile_data(int $post_id): array
{
    return [
        'post_id' => $post_id,
        'title'   => get_the_title($post_id),
    ];
}
```

Also add `require_once` for the helper file from `functions.php`. Find the section where `inc/player-profile-data.php` is required and add:

```php
require_once get_theme_file_path('inc/season-profile-data.php');
```

- [ ] **Step 6: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
php -l wp-content/themes/bbj-v2-theme/inc/enqueue.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-22/?nocache=$(date +%s)" -o /tmp/page.html -w "HTTP %{http_code}\n"
grep -oE "Big Brother 22|<main class=\"wrap\">" /tmp/page.html | head -3
```

Expected: 3 lints "No syntax errors", HTTP 200, grep finds both markers.

- [ ] **Step 7: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/css/single-bigbrother-seasons.css wp-content/themes/bbj-v2-theme/src/js/season-profile.js wp-content/themes/bbj-v2-theme/inc/enqueue.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): scaffold template + css + helper file"
```

---

### Task 2: Hero section + helper

Render the season hero: number/title, 4 strip stats (Winner / Days / HG count / Prize), action buttons, side poster card.

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php`

- [ ] **Step 1: Expand `bbj_v2_season_profile_data()` with full hero data**

Replace the stub. The function must:
- LEFT JOIN `wp_bbj_seasons` so BB22+ still works
- Aggregate Winner from junction (`finish_place=1`)
- Compute days = MAX(`bbj_evicted_date`) − MIN(season_start) when wp_bbj_seasons row exists, else NULL
- Count houseguests = COUNT junction rows
- Derive season_number from post_title via regex if `wp_bbj_seasons.season_number` missing

```php
function bbj_v2_season_profile_data(int $post_id): array
{
    global $wpdb;

    // Core row (LEFT JOIN — BB22+ has no wp_bbj_seasons row)
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                p.ID                AS post_id,
                p.post_title        AS title,
                p.post_name         AS slug,
                p.post_date         AS post_date,
                p.post_content      AS content,
                s.full_name         AS season_full_name,
                s.abbreviation      AS season_abbr,
                s.season_number     AS season_number,
                s.start_date        AS start_date,
                s.end_date          AS end_date,
                s.season_winner     AS season_winner_id,
                s.runner_up         AS runner_up_id,
                s.afp               AS afp_id
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = p.ID
             WHERE p.ID = %d AND p.post_type = 'bigbrother-seasons'
             LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    if (!$row) {
        return ['post_id' => $post_id, 'title' => get_the_title($post_id)];
    }

    // Derive season number from title if not stored ("Big Brother 27" -> 27)
    if (empty($row['season_number']) && !empty($row['title'])) {
        if (preg_match('/(\d+)/', $row['title'], $m)) {
            $row['season_number'] = (int) $m[1];
        }
    }

    // Derive abbreviation if missing ("Big Brother 27" -> "BB27")
    if (empty($row['season_abbr']) && !empty($row['season_number'])) {
        $row['season_abbr'] = 'BB' . $row['season_number'];
    }

    // Strip stats: winner, days, houseguest count
    $hg_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bbj_v2_player_season WHERE bbj_season = %d",
        $post_id
    ));

    $winner_post_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT bbj_player FROM {$wpdb->prefix}bbj_v2_player_season
         WHERE bbj_season = %d AND finish_place = 1
         LIMIT 1",
        $post_id
    ));
    $winner_name = $winner_post_id ? get_the_title($winner_post_id) : '';

    $days = null;
    if (!empty($row['start_date']) && !empty($row['end_date'])) {
        try {
            $d1 = new DateTime($row['start_date']);
            $d2 = new DateTime($row['end_date']);
            $days = max(0, (int) $d1->diff($d2)->days);
        } catch (Exception $e) {}
    }

    // Prize fallback by era — no per-season field exists yet
    $prize = null;
    $sn = (int) ($row['season_number'] ?? 0);
    if ($sn >= 23)      $prize = '$750k';
    elseif ($sn >= 19)  $prize = '$500k';
    elseif ($sn >= 1)   $prize = '$500k';

    return [
        'post_id'        => $post_id,
        'title'          => $row['title'],
        'slug'           => $row['slug'],
        'name'           => $row['season_full_name'] ?: $row['title'],
        'number'         => (int) ($row['season_number'] ?? 0),
        'abbr'           => $row['season_abbr'] ?: '',
        'start_date'     => $row['start_date'] ?: null,
        'end_date'       => $row['end_date'] ?: null,
        'post_date'      => $row['post_date'],
        'content'        => $row['content'],
        'winner_post_id' => $winner_post_id,
        'winner_name'    => $winner_name,
        'runner_up_id'   => (int) ($row['runner_up_id'] ?? 0),
        'afp_id'         => (int) ($row['afp_id'] ?? 0),
        'hg_count'       => $hg_count,
        'days'           => $days,
        'prize'          => $prize,
    ];
}
```

- [ ] **Step 2: Add hero block to `single-bigbrother-seasons.php`**

Insert after the breadcrumb, before the closing `</main>`:

```php
  <!-- HERO -->
  <div class="hero">
    <div class="inner">
      <div>
        <div class="kk"><b>Season <?php echo (int) $season['number']; ?></b>USA · CBS</div>
        <h1><?php echo esc_html($season['name']); ?></h1>
        <?php if (!empty($season['content'])) : ?>
          <p class="sub"><?php echo wp_kses_post(wp_trim_words(strip_tags($season['content']), 38)); ?></p>
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
          <a class="b prim" href="<?php echo esc_url(home_url('/feed-updates/')); ?>">▶ Live Feed Updates</a>
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
```

- [ ] **Step 3: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-22/?nocache=$(date +%s)" 2>&1 | grep -oE "Big Brother 22|<div class=\"hero\">|<span class=\"k\">Winner</span>|Houseguests" | head -10
```

Expected: hero markers + at least one strip stat label visible. The Winner stat may be missing on local for BB22 if the data isn't there — that's OK for now; we'll verify on staging at the end.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): hero block with strip stats + poster"
```

---

### Task 3: Season Switcher pills + neighbors helper

Render the horizontal pill row of nearby seasons.

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php`

- [ ] **Step 1: Add `bbj_v2_season_profile_neighbors()` helper**

```php
/**
 * Return ±$window seasons by season_number around the given season.
 * Falls back to ordering by post_date when season_number is missing.
 */
function bbj_v2_season_profile_neighbors(int $post_id, int $window = 5): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                p.ID         AS post_id,
                p.post_title AS title,
                p.post_name  AS slug,
                p.post_date  AS post_date,
                s.season_number,
                s.abbreviation
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = p.ID
             WHERE p.post_type = 'bigbrother-seasons' AND p.post_status = 'publish'
             ORDER BY COALESCE(s.season_number, 0) DESC, p.post_date DESC",
            $post_id
        ),
        ARRAY_A
    );

    if (!$rows) return [];

    // Find current index
    $current = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['post_id'] === $post_id) { $current = $i; break; }
    }
    if ($current === null) return [];

    $start = max(0, $current - $window);
    $end   = min(count($rows) - 1, $current + $window);
    $slice = array_slice($rows, $start, $end - $start + 1);

    foreach ($slice as &$r) {
        if (empty($r['season_number']) && !empty($r['title'])) {
            if (preg_match('/(\d+)/', $r['title'], $m)) $r['season_number'] = (int) $m[1];
        }
        if (empty($r['abbreviation']) && !empty($r['season_number'])) {
            $r['abbreviation'] = 'BB' . $r['season_number'];
        }
        $r['is_current'] = ((int) $r['post_id'] === $post_id);
        $r['url']        = home_url('/bigbrother-seasons/' . $r['slug'] . '/');
    }
    unset($r);

    return $slice;
}
```

- [ ] **Step 2: Add the switcher block to the template (immediately after the hero)**

```php
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
    <a class="all" href="<?php echo esc_url(home_url('/bigbrother-seasons/')); ?>">All seasons →</a>
  </div>
  <?php endif; ?>

  <?php // Also fetch $season at the top so $post_id is in scope for the call ?>
```

- [ ] **Step 3: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-22/?nocache=$(date +%s)" 2>&1 | grep -oE "<div class=\"switcher\">|class=\"on\">BB22|All seasons →" | head -5
```

Expected: switcher div present, BB22 marked `on`, "All seasons" link present.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): season switcher pills"
```

---

### Task 4: Sticky Tab Nav + scroll-spy + Overview section

The tab nav is structurally tied to which sections render — both arrive together with the Overview as the first real section.

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php`
- Modify: `wp-content/themes/bbj-v2-theme/src/js/season-profile.js`

- [ ] **Step 1: Add a small `bbj_v2_season_profile_facts()` helper for the Overview right-column dl**

```php
/**
 * Return the "Season Facts" dl content for the Overview block.
 * Each item is [label, value]. Skips anything we don't have.
 */
function bbj_v2_season_profile_facts(array $season): array
{
    $facts = [];
    if (!empty($season['start_date'])) {
        $facts[] = ['Premiere', date_i18n('M j, Y', strtotime($season['start_date']))];
    }
    if (!empty($season['end_date'])) {
        $facts[] = ['Finale', date_i18n('M j, Y', strtotime($season['end_date']))];
    }
    if (!empty($season['days'])) {
        $facts[] = ['Days', (string) $season['days']];
    }
    if (!empty($season['hg_count'])) {
        $facts[] = ['Houseguests', (string) $season['hg_count']];
    }
    if (!empty($season['prize'])) {
        $facts[] = ['Prize', $season['prize']];
    }
    return $facts;
}
```

- [ ] **Step 2: Add tab nav + Overview block + main-grid wrapper to template**

Insert after the season switcher:

```php
  <?php
  // Build the list of sections that will render. Used by tab nav + sidebar TOC
  // to avoid dead anchors. Order matters — matches scroll order.
  $sections = [];
  if (!empty($season['content']) || !empty(bbj_v2_season_profile_facts($season))) {
      $sections[] = ['id' => 'overview', 'label' => 'Overview', 'count' => null];
  }
  // Subsequent tasks append: winners, cast, evictions, comps, feed, articles
  ?>

  <?php if (!empty($sections)) : ?>
  <div class="sectionnav">
    <?php foreach ($sections as $i => $sec) : ?>
      <a href="#<?php echo esc_attr($sec['id']); ?>" class="<?php echo $i === 0 ? 'on' : ''; ?>">
        <?php echo esc_html($sec['label']); ?>
        <?php if ($sec['count'] !== null) : ?>
          <span class="ct"><?php echo esc_html((string) $sec['count']); ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid">
    <!-- MAIN -->
    <div>

      <!-- OVERVIEW -->
      <?php if (in_array('overview', array_column($sections, 'id'), true)) : ?>
      <section id="overview">
        <div class="sech">
          <h2>Season Overview</h2>
          <span class="sub">At a glance</span>
        </div>
        <div class="overview">
          <div class="copy">
            <?php if (!empty($season['content'])) : ?>
              <?php echo apply_filters('the_content', $season['content']); ?>
            <?php else : ?>
              <p class="lead">Season recap coming soon.</p>
            <?php endif; ?>
          </div>
          <?php $facts = bbj_v2_season_profile_facts($season); ?>
          <?php if (!empty($facts)) : ?>
          <div class="facts">
            <h4>Season Facts</h4>
            <dl>
              <?php foreach ($facts as $f) : ?>
                <dt><?php echo esc_html($f[0]); ?></dt><dd><?php echo esc_html($f[1]); ?></dd>
              <?php endforeach; ?>
            </dl>
          </div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- additional sections appended by later tasks -->

    </div>

    <!-- SIDEBAR placeholder (Task 8) -->
    <aside><div class="stick"></div></aside>
  </div>
```

- [ ] **Step 3: Implement scroll-spy in `src/js/season-profile.js`**

Replace the file contents:

```js
/**
 * Season profile — sticky tab nav scroll-spy.
 * Marks the tab whose section is currently in view with .on.
 * Also enables smooth scroll on tab clicks.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const nav = document.querySelector('.sectionnav');
    if (!nav) return;

    const tabs = Array.from(nav.querySelectorAll('a[href^="#"]'));
    if (tabs.length === 0) return;

    const sections = tabs
      .map(a => document.getElementById(a.getAttribute('href').slice(1)))
      .filter(Boolean);

    if (sections.length === 0) return;

    function setActive(id) {
      tabs.forEach(a => {
        a.classList.toggle('on', a.getAttribute('href') === '#' + id);
      });
    }

    const observer = new IntersectionObserver(
      function (entries) {
        // Pick the section whose top is closest to the nav (rootMargin offsets the trigger)
        const visible = entries.filter(e => e.isIntersecting);
        if (visible.length === 0) return;
        // Sort by document order, take the first one in view
        visible.sort((a, b) => a.target.offsetTop - b.target.offsetTop);
        setActive(visible[0].target.id);
      },
      { rootMargin: '-50px 0px -60% 0px', threshold: 0 }
    );

    sections.forEach(s => observer.observe(s));

    // Smooth scroll on click (header offset = ~50px)
    tabs.forEach(a => {
      a.addEventListener('click', function (e) {
        const target = document.getElementById(this.getAttribute('href').slice(1));
        if (!target) return;
        e.preventDefault();
        const y = target.getBoundingClientRect().top + window.pageYOffset - 60;
        window.scrollTo({ top: y, behavior: 'smooth' });
        history.replaceState(null, '', this.getAttribute('href'));
      });
    });
  });
})();
```

- [ ] **Step 4: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
node --check wp-content/themes/bbj-v2-theme/src/js/season-profile.js
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-22/?nocache=$(date +%s)" 2>&1 | grep -oE '<div class="sectionnav">|<section id="overview">|<h2>Season Overview</h2>|<dt>' | head -5
```

Expected: sectionnav, overview section, season overview heading, at least one `<dt>` for facts.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php wp-content/themes/bbj-v2-theme/src/js/season-profile.js
git commit -m "feat(season-profile): tab nav + overview + scroll-spy"
```

---

### Task 5: Winners Podium + Cast Grid

Both query the junction table for placement + player data.

**Files:**
- Modify: `inc/season-profile-data.php`
- Modify: `single-bigbrother-seasons.php`

- [ ] **Step 1: Add `bbj_v2_season_profile_cast()` helper**

Returns ALL houseguests for the season, ordered by `finish_place` ASC (NULLs last → still-playing first), with player profile data joined via the `id OR post_id` pattern.

```php
function bbj_v2_season_profile_cast(int $post_id): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_player        AS player_post_id,
                j.finish_place,
                j.bbj_evicted_date,
                j.current_jury,
                j.current_evicted,
                j.bbj_total_hoh,
                j.bbj_total_pov,
                j.bbj_total_nom,
                bp.first_name,
                bp.last_name,
                bp.official_nickname,
                bp.profile_picture,
                p.post_title        AS post_title,
                p.post_name         AS player_slug
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
             LEFT JOIN {$wpdb->prefix}bbj_players bp
                    ON (bp.post_id = j.bbj_player OR (bp.id = j.bbj_player AND bp.post_id = 0))
             WHERE j.bbj_season = %d
               AND p.post_status = 'publish'
             ORDER BY (j.finish_place IS NULL), j.finish_place ASC, p.post_title ASC",
            $post_id
        ),
        ARRAY_A
    );

    if (!$rows) return [];

    foreach ($rows as &$row) {
        if (empty($row['first_name']) && empty($row['last_name'])) {
            $parts = array_values(array_filter(explode(' ', trim((string) ($row['post_title'] ?? '')), 2)));
            $row['first_name'] = $parts[0] ?? '';
            $row['last_name']  = $parts[1] ?? '';
        }
    }
    unset($row);

    return $rows;
}
```

- [ ] **Step 2: Add Winners Podium block to the template**

Append in the main column, after the Overview section:

```php
      <?php
      $cast = bbj_v2_season_profile_cast($post_id);
      $winner    = null;
      $runner_up = null;
      foreach ($cast as $cm) {
          if ((int) $cm['finish_place'] === 1) $winner    = $cm;
          if ((int) $cm['finish_place'] === 2) $runner_up = $cm;
      }
      $afp_id = (int) ($season['afp_id'] ?? 0);
      $afp = null;
      if ($afp_id > 0) {
          foreach ($cast as $cm) {
              if ((int) $cm['player_post_id'] === $afp_id) { $afp = $cm; break; }
          }
      }
      $has_podium = $winner || $runner_up;
      if ($has_podium) {
          $sections[] = ['id' => 'winners', 'label' => 'Top 3 & AFP', 'count' => null];
      }
      ?>

      <?php if ($has_podium) : ?>
      <section id="winners">
        <div class="sech">
          <h2>Top 3 &amp; AFP</h2>
          <span class="sub">How the season ended</span>
        </div>
        <div class="podium">
          <?php if ($runner_up) : ?>
            <?php $ru_full = trim($runner_up['first_name'] . ' ' . $runner_up['last_name']); ?>
            <div class="p r">
              <div class="pc"<?php echo empty($runner_up['profile_picture']) ? ' data-i="' . esc_attr(strtoupper(substr($ru_full, 0, 2))) . '"' : ''; ?>>
                <?php if (!empty($runner_up['profile_picture'])) echo wp_get_attachment_image((int) $runner_up['profile_picture'], 'medium', false, ['style'=>'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;']); ?>
                <span class="lbl">2nd · Runner-up</span>
              </div>
              <div class="body">
                <div class="name"><?php echo esc_html($ru_full); ?></div>
                <div class="role">Final 2</div>
                <div class="nums">
                  <span><b><?php echo (int) $runner_up['bbj_total_hoh']; ?></b>HoH</span>
                  <span><b><?php echo (int) $runner_up['bbj_total_pov']; ?></b>PoV</span>
                  <span><b><?php echo (int) $runner_up['bbj_total_nom']; ?></b>Nom</span>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($winner) : ?>
            <?php $w_full = trim($winner['first_name'] . ' ' . $winner['last_name']); ?>
            <div class="p w">
              <div class="pc"<?php echo empty($winner['profile_picture']) ? ' data-i="' . esc_attr(strtoupper(substr($w_full, 0, 2))) . '"' : ''; ?>>
                <?php if (!empty($winner['profile_picture'])) echo wp_get_attachment_image((int) $winner['profile_picture'], 'medium', false, ['style'=>'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;']); ?>
                <span class="lbl">★ Winner</span>
              </div>
              <div class="body">
                <div class="name"><?php echo esc_html($w_full); ?></div>
                <div class="role"><?php echo esc_html($season['abbr']); ?> Winner</div>
                <div class="nums">
                  <span><b><?php echo (int) $winner['bbj_total_hoh']; ?></b>HoH</span>
                  <span><b><?php echo (int) $winner['bbj_total_pov']; ?></b>PoV</span>
                  <span><b><?php echo (int) $winner['bbj_total_nom']; ?></b>Nom</span>
                </div>
                <?php if (!empty($season['prize'])) : ?>
                  <div class="prize"><?php echo esc_html($season['prize']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($afp) : ?>
            <?php $afp_full = trim($afp['first_name'] . ' ' . $afp['last_name']); ?>
            <div class="p a">
              <a href="<?php echo esc_url(home_url('/bigbrother-players/' . $afp['player_slug'] . '/')); ?>">
                <div class="pc"<?php echo empty($afp['profile_picture']) ? ' data-i="' . esc_attr(strtoupper(substr($afp_full, 0, 2))) . '"' : ''; ?>>
                  <?php if (!empty($afp['profile_picture'])) echo wp_get_attachment_image((int) $afp['profile_picture'], 'medium', false, ['style'=>'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;']); ?>
                  <span class="lbl">AFP</span>
                </div>
                <div class="body">
                  <div class="name"><?php echo esc_html($afp_full); ?></div>
                  <div class="role">America's Favorite</div>
                  <div class="nums">
                    <span><b><?php echo (int) $afp['bbj_total_hoh']; ?></b>HoH</span>
                    <span><b><?php echo (int) $afp['bbj_total_pov']; ?></b>PoV</span>
                    <span><b><?php echo (int) $afp['bbj_total_nom']; ?></b>Nom</span>
                  </div>
                </div>
              </a>
            </div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>
```

**IMPORTANT:** the `$sections[] = ...` line above must execute BEFORE the `.sectionnav` markup is rendered. Since the tab nav is currently above the main grid, you need to RESTRUCTURE so the section list is built FIRST, then the nav renders, then the grid renders. Do this by:
1. Computing all section additions at the TOP of the template (before any output) — call all helper functions there
2. Rendering the nav
3. Rendering the grid (which can reuse the already-computed data)

Refactor the template's top to look like:

```php
the_post();
$post_id   = get_the_ID();
$season    = bbj_v2_season_profile_data($post_id);
$facts     = bbj_v2_season_profile_facts($season);
$cast      = bbj_v2_season_profile_cast($post_id);
$neighbors = bbj_v2_season_profile_neighbors($post_id, 5);

// Determine winners + AFP from cast
$winner = $runner_up = $afp = null;
foreach ($cast as $cm) {
    if ((int) $cm['finish_place'] === 1) $winner    = $cm;
    if ((int) $cm['finish_place'] === 2) $runner_up = $cm;
}
if (!empty($season['afp_id'])) {
    foreach ($cast as $cm) {
        if ((int) $cm['player_post_id'] === (int) $season['afp_id']) { $afp = $cm; break; }
    }
}

// Build section list — order matters, matches scroll order
$sections = [];
if (!empty($season['content']) || !empty($facts))   $sections[] = ['id' => 'overview', 'label' => 'Overview',     'count' => null];
if ($winner || $runner_up)                          $sections[] = ['id' => 'winners',  'label' => 'Top 3 & AFP',   'count' => null];
if (!empty($cast))                                  $sections[] = ['id' => 'cast',     'label' => 'Cast',          'count' => count($cast)];
// Subsequent tasks append: evictions, comps, feed, articles

get_header();
```

Then the body just iterates and conditionally renders. Move the conditional block tests inside each section to use the precomputed variables.

- [ ] **Step 3: Add Cast Grid section to template (after Winners Podium)**

```php
      <?php if (!empty($cast)) : ?>
      <section id="cast">
        <div class="sech">
          <h2>Cast of <?php echo esc_html($season['abbr']); ?></h2>
          <span class="sub"><?php echo count($cast); ?> houseguests</span>
        </div>
        <div class="castgrid">
          <?php foreach ($cast as $cm) :
            $cm_full    = trim($cm['first_name'] . ' ' . $cm['last_name']);
            $cm_display = $cm['official_nickname'] ?: ($cm['first_name'] ?: $cm_full);
            $cm_url     = !empty($cm['player_slug']) ? home_url('/bigbrother-players/' . $cm['player_slug'] . '/') : '#';
            $cm_finish  = (int) ($cm['finish_place'] ?? 0);

            $tag_class = 'pre';
            $tag_text  = 'Out';
            $afp_match = ($afp_id > 0 && (int) $cm['player_post_id'] === $afp_id);
            if ($cm_finish === 1)            { $tag_class = 'win';  $tag_text = 'Winner'; }
            elseif ($cm_finish === 2)        { $tag_class = 'ru';   $tag_text = '2nd'; }
            elseif ($afp_match)              { $tag_class = 'afp';  $tag_text = 'AFP'; }
            elseif (!empty($cm['current_jury'])) { $tag_class = 'jury'; $tag_text = 'Jury'; }

            $days = '';
            if (!empty($cm['bbj_evicted_date']) && !empty($season['start_date'])) {
                try {
                    $d1 = new DateTime($season['start_date']);
                    $d2 = new DateTime($cm['bbj_evicted_date']);
                    $days = max(0, (int) $d1->diff($d2)->days) . 'd';
                } catch (Exception $e) {}
            }
            $sub = $cm_finish > 0
                ? bbj_v2_season_profile_ordinal($cm_finish) . ($days ? ' · ' . $days : '')
                : 'Active';
          ?>
          <a class="c" href="<?php echo esc_url($cm_url); ?>" title="<?php echo esc_attr($cm_full); ?>">
            <div class="face"<?php echo empty($cm['profile_picture']) ? ' data-i="' . esc_attr(strtoupper(substr($cm_display, 0, 2))) . '"' : ''; ?>>
              <?php if (!empty($cm['profile_picture'])) echo wp_get_attachment_image((int) $cm['profile_picture'], 'thumbnail', false, ['style'=>'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;', 'alt' => $cm_full]); ?>
              <span class="tag <?php echo esc_attr($tag_class); ?>"><?php echo esc_html($tag_text); ?></span>
            </div>
            <div class="n"><?php echo esc_html($cm_display); ?></div>
            <div class="s"><?php echo esc_html($sub); ?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
```

- [ ] **Step 4: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-13/?nocache=$(date +%s)" 2>&1 | grep -oE '<section id="winners">|class="pill winner"|<section id="cast">|class="castgrid"|<a class="c"' | head -10
```

Expected (BB13, Rachel was the winner): winners section, cast section, cast grid div, several `<a class="c"` cast cards.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): winners podium + cast grid"
```

---

### Task 6: Eviction Order Table + Comp Winners weekly table

Both are powered by `wp_bbj_weeks` + `wp_bbj_weeks_players`.

**Files:**
- Modify: `inc/season-profile-data.php`
- Modify: `single-bigbrother-seasons.php`

- [ ] **Step 1: Add `bbj_v2_season_profile_evictions()` helper**

```php
/**
 * Eviction order table data. Returns rows ordered by week_num ASC.
 * Vote tally: count rows in wp_bbj_weeks_players where voted_for = evictee
 * for that week (excluding the evictee).
 */
function bbj_v2_season_profile_evictions(int $post_id): array
{
    global $wpdb;

    $weeks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, week_num, start_date, end_date
             FROM {$wpdb->prefix}bbj_weeks
             WHERE season_id = %d
             ORDER BY week_num ASC",
            $post_id
        ),
        ARRAY_A
    );
    if (!$weeks) return [];

    $week_ids = array_column($weeks, 'id');
    $week_id_csv = implode(',', array_map('intval', $week_ids));
    $max_week = max(array_column($weeks, 'week_num'));

    // All evictees in any week of this season
    $evictee_rows = $wpdb->get_results(
        "SELECT wp.id AS wpid, wp.week_id, wp.player_id, p.post_title, p.post_name AS player_slug, bp.profile_picture
         FROM {$wpdb->prefix}bbj_weeks_players wp
         INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
         LEFT JOIN {$wpdb->prefix}bbj_players bp
                ON (bp.post_id = wp.player_id OR (bp.id = wp.player_id AND bp.post_id = 0))
         WHERE wp.evicted = 1 AND wp.week_id IN ({$week_id_csv})",
        ARRAY_A
    );
    if (!$evictee_rows) return [];

    // HoH per week
    $hoh_rows = $wpdb->get_results(
        "SELECT wp.week_id, p.post_title AS hoh_name
         FROM {$wpdb->prefix}bbj_weeks_players wp
         INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
         WHERE wp.hoh = 1 AND wp.week_id IN ({$week_id_csv})",
        ARRAY_A
    );
    $hoh_by_week = [];
    foreach ($hoh_rows as $h) {
        $hoh_by_week[$h['week_id']] = $h['hoh_name'];
    }

    // Index weeks for join
    $weeks_by_id = [];
    foreach ($weeks as $w) $weeks_by_id[$w['id']] = $w;

    // Vote-counts: count voted_for occurrences per (week_id, evictee_id)
    $vote_rows = $wpdb->get_results(
        "SELECT week_id, voted_for, COUNT(*) AS n
         FROM {$wpdb->prefix}bbj_weeks_players
         WHERE week_id IN ({$week_id_csv}) AND voted_for > 0
         GROUP BY week_id, voted_for",
        ARRAY_A
    );
    $votes_by_pair = [];
    foreach ($vote_rows as $v) {
        $votes_by_pair[$v['week_id'] . '_' . $v['voted_for']] = (int) $v['n'];
    }

    // Group evictees by week_num to detect doubles
    $by_week_num = [];
    foreach ($evictee_rows as $e) {
        $w = $weeks_by_id[$e['week_id']] ?? null;
        if (!$w) continue;
        $by_week_num[$w['week_num']][] = $e + ['week_num' => $w['week_num'], 'week_end' => $w['end_date']];
    }

    $out = [];
    foreach ($by_week_num as $week_num => $evs) {
        $type = count($evs) > 1 ? 'Double' : 'Regular';
        if ((int) $week_num === (int) $max_week) $type = 'Finale';
        foreach ($evs as $e) {
            // Day = (eviction date - season start). We don't have a per-week eviction date
            // separately; use week.end_date as the eviction day.
            $day = '';
            if (!empty($e['week_end']) && !empty($weeks[0]['start_date'])) {
                try {
                    $d1 = new DateTime($weeks[0]['start_date']);
                    $d2 = new DateTime($e['week_end']);
                    $day = 'Day ' . max(0, (int) $d1->diff($d2)->days);
                } catch (Exception $e2) {}
            }
            $vote_count = $votes_by_pair[$e['week_id'] . '_' . $e['player_id']] ?? null;
            $out[] = [
                'week_num'    => (int) $week_num,
                'week_label'  => $type === 'Finale' ? 'Fin' : str_pad((string) $week_num, 2, '0', STR_PAD_LEFT),
                'name'        => $e['post_title'],
                'slug'        => $e['player_slug'],
                'profile_pic' => (int) ($e['profile_picture'] ?? 0),
                'day'         => $day,
                'vote'        => $vote_count !== null ? ($vote_count . '–?') : '',
                'type'        => $type,
                'hoh_name'    => $hoh_by_week[$e['week_id']] ?? '',
            ];
        }
    }

    return $out;
}
```

- [ ] **Step 2: Add `bbj_v2_season_profile_comps()` helper**

```php
/**
 * Comp winners weekly table. One row per week with HoH, PoV, nominees,
 * veto used on. Returns ordered by week_num ASC.
 */
function bbj_v2_season_profile_comps(int $post_id): array
{
    global $wpdb;

    $weeks = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, week_num
             FROM {$wpdb->prefix}bbj_weeks
             WHERE season_id = %d
             ORDER BY week_num ASC",
            $post_id
        ),
        ARRAY_A
    );
    if (!$weeks) return [];

    $week_id_csv = implode(',', array_map('intval', array_column($weeks, 'id')));

    $rows = $wpdb->get_results(
        "SELECT wp.week_id, wp.player_id, wp.hoh, wp.pov, wp.nom, wp.saved, p.post_title AS name
         FROM {$wpdb->prefix}bbj_weeks_players wp
         INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
         WHERE wp.week_id IN ({$week_id_csv})
           AND (wp.hoh = 1 OR wp.pov = 1 OR wp.nom = 1 OR wp.saved = 1)",
        ARRAY_A
    );

    $by_week = [];
    foreach ($weeks as $w) {
        $by_week[$w['id']] = ['week_num' => $w['week_num'], 'hoh' => [], 'pov' => [], 'nom' => [], 'saved' => []];
    }
    foreach ($rows as $r) {
        if ($r['hoh'])   $by_week[$r['week_id']]['hoh'][]   = $r['name'];
        if ($r['pov'])   $by_week[$r['week_id']]['pov'][]   = $r['name'];
        if ($r['nom'])   $by_week[$r['week_id']]['nom'][]   = $r['name'];
        if ($r['saved']) $by_week[$r['week_id']]['saved'][] = $r['name'];
    }

    return array_values($by_week);
}
```

- [ ] **Step 3: Add the two sections to the template (after Cast Grid)**

In the precomputed top block:

```php
$evictions = bbj_v2_season_profile_evictions($post_id);
$comps     = bbj_v2_season_profile_comps($post_id);
if (!empty($evictions)) $sections[] = ['id' => 'evictions', 'label' => 'Evictions',   'count' => count($evictions)];
if (!empty($comps))     $sections[] = ['id' => 'comps',     'label' => 'Comp Winners', 'count' => count($comps)];
```

In the body, after the cast section:

```php
      <?php if (!empty($evictions)) : ?>
      <section id="evictions">
        <div class="sech">
          <h2>Eviction Order</h2>
          <span class="sub">Week by week</span>
        </div>
        <div class="evtable">
          <table>
            <thead><tr><th>Wk</th><th>Houseguest</th><th>Day</th><th>Vote</th><th>Type</th><th>Evicted By</th></tr></thead>
            <tbody>
              <?php foreach ($evictions as $e) :
                $type_class = 'reg';
                if ($e['type'] === 'Double') $type_class = 'db';
                if ($e['type'] === 'Finale') $type_class = 'fin';
                $initials = strtoupper(substr($e['name'], 0, 2));
              ?>
              <tr>
                <td class="wk"><?php echo esc_html($e['week_label']); ?></td>
                <td>
                  <a class="hg" href="<?php echo esc_url(home_url('/bigbrother-players/' . $e['slug'] . '/')); ?>">
                    <span class="a"><?php echo esc_html($initials); ?></span><?php echo esc_html($e['name']); ?>
                  </a>
                </td>
                <td class="day"><?php echo esc_html($e['day']); ?></td>
                <td class="vote"><?php echo esc_html($e['vote']); ?></td>
                <td><span class="typ <?php echo esc_attr($type_class); ?>"><?php echo esc_html($e['type']); ?></span></td>
                <td><?php echo $e['hoh_name'] ? 'HoH ' . esc_html($e['hoh_name']) : ''; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($comps)) : ?>
      <section id="comps">
        <div class="sech">
          <h2>Competition Winners</h2>
          <span class="sub">HoH &amp; Veto each week</span>
        </div>
        <div class="comptable">
          <table>
            <thead><tr><th>Week</th><th>Head of Household</th><th>Power of Veto</th><th>Nominees</th><th>Veto Used On</th></tr></thead>
            <tbody>
              <?php foreach ($comps as $c) : ?>
              <tr>
                <td class="wkhead">W<?php echo (int) $c['week_num']; ?></td>
                <td>
                  <?php foreach ($c['hoh'] as $name) : ?>
                    <span class="cell"><span class="a"><?php echo esc_html(strtoupper(substr($name, 0, 2))); ?></span><?php echo esc_html($name); ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($c['hoh'])) : ?><span class="cell empty">—</span><?php endif; ?>
                </td>
                <td>
                  <?php foreach ($c['pov'] as $name) : ?>
                    <span class="cell"><span class="a"><?php echo esc_html(strtoupper(substr($name, 0, 2))); ?></span><?php echo esc_html($name); ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($c['pov'])) : ?><span class="cell empty">—</span><?php endif; ?>
                </td>
                <td><?php echo esc_html(implode(' · ', $c['nom'])); ?></td>
                <td>
                  <?php if (!empty($c['saved'])) : ?>
                    <?php foreach ($c['saved'] as $name) : ?>
                      <span class="cell"><span class="a"><?php echo esc_html(strtoupper(substr($name, 0, 2))); ?></span><?php echo esc_html($name); ?></span>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <span class="cell empty">Not used</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
```

- [ ] **Step 4: Lint + smoke test (use a season known to have wp_bbj_weeks data; BB24=45207 is confirmed)**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-24/?nocache=$(date +%s)" 2>&1 | grep -oE '<section id="evictions">|<section id="comps">|<table>|HoH ' | head -8
```

Expected: both sections present, tables render. If local doesn't have BB24 data either, this section may be empty — that's fine; we'll verify against staging at the end.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): eviction order + comp winners tables"
```

---

### Task 7: Top Feed Updates section + Articles section

Top Feed Updates pulls from `live-feed-updates` post_type within the season's date window, sorted by `total_rating` DESC. Articles pulls from category-tagged posts.

**Files:**
- Modify: `inc/season-profile-data.php`
- Modify: `single-bigbrother-seasons.php`

- [ ] **Step 1: Add `bbj_v2_season_profile_top_feed_updates()` helper**

```php
/**
 * Top feed updates for the season, by total_rating DESC.
 * Date window: wp_bbj_seasons.start_date/end_date if available, else
 * season post_date as start + 100 days as end (covers BB22+).
 */
function bbj_v2_season_profile_top_feed_updates(int $post_id, int $limit = 9): array
{
    global $wpdb;

    $season = bbj_v2_season_profile_data($post_id);
    $start = $season['start_date'] ?: $season['post_date'];
    if (!$start) return [];

    if (!empty($season['end_date'])) {
        $end = $season['end_date'];
    } else {
        try {
            $end = (new DateTime($start))->modify('+100 days')->format('Y-m-d');
        } catch (Exception $e) {
            return [];
        }
    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                p.ID, p.post_title, p.post_excerpt, p.post_content, p.post_date, p.post_name,
                CAST(IFNULL(pm.meta_value, '0') AS UNSIGNED) AS rating
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID AND pm.meta_key = 'total_rating'
             WHERE p.post_type = 'live-feed-updates'
               AND p.post_status = 'publish'
               AND p.post_date BETWEEN %s AND %s
             ORDER BY rating DESC, p.post_date DESC
             LIMIT %d",
            $start . ' 00:00:00',
            $end . ' 23:59:59',
            $limit
        ),
        ARRAY_A
    ) ?: [];
}
```

- [ ] **Step 2: Add `bbj_v2_season_profile_articles()` helper**

Convention check during implementation: query the season's wp_terms category by slug (e.g., "bb27"). If your articles use a different scheme (postmeta, custom taxonomy), adapt this query before continuing.

```php
/**
 * Articles tagged with this season's category.
 * Convention: each season has a category slug like "bb27".
 */
function bbj_v2_season_profile_articles(int $post_id, int $limit = 4): array
{
    global $wpdb;

    $season = bbj_v2_season_profile_data($post_id);
    $abbr = strtolower($season['abbr'] ?? '');
    if ($abbr === '') return [];

    $term = get_term_by('slug', $abbr, 'category');
    if (!$term) return [];

    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'category'       => $term->term_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $out = [];
    foreach ($posts as $p) {
        $thumb_id = get_post_thumbnail_id($p->ID);
        $out[] = [
            'id'       => $p->ID,
            'title'    => get_the_title($p),
            'excerpt'  => get_the_excerpt($p),
            'url'      => get_permalink($p),
            'thumb'    => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '',
            'date'     => $p->post_date,
            'category' => 'BBJ Blog',
        ];
    }
    return $out;
}
```

- [ ] **Step 3: Add both sections to the template**

In the precomputed top block:

```php
$top_feed = bbj_v2_season_profile_top_feed_updates($post_id, 9);
$articles = bbj_v2_season_profile_articles($post_id, 4);
if (!empty($top_feed)) $sections[] = ['id' => 'memories', 'label' => 'Memorable Moments', 'count' => count($top_feed)];
if (!empty($articles)) $sections[] = ['id' => 'articles', 'label' => 'Articles',          'count' => count($articles)];
```

In the body, after the comp winners section:

```php
      <?php if (!empty($top_feed)) : ?>
      <section id="memories">
        <div class="sech">
          <h2>Memorable Moments</h2>
          <span class="sub">Top fan-rated feed updates</span>
        </div>
        <div class="memories">
          <?php foreach ($top_feed as $u) :
            $week = '';
            if (!empty($season['start_date'])) {
                try {
                    $d1 = new DateTime($season['start_date']);
                    $d2 = new DateTime($u['post_date']);
                    $diff_days = (int) $d1->diff($d2)->days;
                    $week = 'Week ' . max(1, (int) floor($diff_days / 7) + 1);
                } catch (Exception $e) {}
            }
            $quote = $u['post_excerpt'] ?: wp_trim_words(strip_tags($u['post_content']), 28);
          ?>
          <div class="mem">
            <div class="qt"><?php echo esc_html($quote); ?></div>
            <div class="att">
              <span><?php echo esc_html(date_i18n('M j', strtotime($u['post_date']))); ?></span>
              <b><?php echo esc_html($week); ?></b>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($articles)) : ?>
      <section id="articles">
        <div class="sech">
          <h2>Articles About <?php echo esc_html($season['abbr']); ?></h2>
          <span class="sub"><?php echo count($articles); ?> on the BBJ blog</span>
        </div>
        <div class="artrow">
          <?php foreach ($articles as $a) : ?>
          <a class="art" href="<?php echo esc_url($a['url']); ?>">
            <div class="thm" data-label="Article" <?php if ($a['thumb']) echo 'style="background-image:url(' . esc_url($a['thumb']) . ');background-size:cover;background-position:center"'; ?>></div>
            <div class="txt">
              <span class="k"><?php echo esc_html($a['category']); ?></span>
              <h3><?php echo esc_html($a['title']); ?></h3>
              <span class="m"><?php echo esc_html(human_time_diff(strtotime($a['date']), current_time('timestamp')) . ' ago'); ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
```

- [ ] **Step 4: Verify articles category convention**

Before running smoke test, confirm one BB-related article on staging IS tagged with a "bb22"/"bb27"/etc lowercase category slug. Run:

```bash
ssh bbj-staging "cd ~/applications/ftgtnduhbt/public_html && wp db query \"SELECT t.slug, COUNT(tr.object_id) AS post_count FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id JOIN wp_term_relationships tr ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tt.taxonomy='category' AND t.slug LIKE 'bb%' GROUP BY t.slug ORDER BY t.slug;\""
```

If output shows slugs like `bb22`, `bb27` with post counts, the helper works as-is. If slugs differ, update the `$abbr = strtolower(...)` derivation in the helper.

- [ ] **Step 5: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-22/?nocache=$(date +%s)" 2>&1 | grep -oE '<section id="memories">|<div class="memories">|<section id="articles">|<a class="art"' | head -5
```

Expected: at least the memories section + memories div on a season with feed updates. Articles section may be empty on local.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): top feed updates + articles sections"
```

---

### Task 8: Sidebar (TOC, Quick Facts, More Seasons, Ad)

**Files:**
- Modify: `single-bigbrother-seasons.php`

- [ ] **Step 1: Replace the empty `<aside>` with the full sidebar**

Find the `<aside><div class="stick"></div></aside>` placeholder and replace with:

```php
    <!-- SIDEBAR -->
    <aside>
      <div class="stick">

        <?php if (!empty($sections)) : ?>
        <div class="card toc">
          <h4>On This Page</h4>
          <ul>
            <?php foreach ($sections as $sec) : ?>
              <li>
                <a href="#<?php echo esc_attr($sec['id']); ?>">
                  <span><?php echo esc_html($sec['label']); ?></span>
                  <?php if ($sec['count'] !== null) : ?>
                    <span><?php echo esc_html((string) $sec['count']); ?></span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($facts)) : ?>
        <div class="card facts-card">
          <h4>Quick Facts</h4>
          <dl>
            <?php foreach ($facts as $f) : ?>
              <dt><?php echo esc_html($f[0]); ?></dt>
              <dd><?php echo esc_html($f[1]); ?></dd>
            <?php endforeach; ?>
          </dl>
        </div>
        <?php endif; ?>

        <?php
        // More Seasons — reuse $neighbors but exclude current and limit to 4
        $more = array_values(array_filter($neighbors, function ($n) { return !$n['is_current']; }));
        $more = array_slice($more, 0, 4);
        ?>
        <?php if (!empty($more)) : ?>
        <div class="card">
          <h4>More Seasons</h4>
          <div class="seas-mini">
            <?php foreach ($more as $n) : ?>
              <a href="<?php echo esc_url($n['url']); ?>">
                <span class="num"><?php echo (int) $n['season_number']; ?></span>
                <span class="win">Season<b><?php echo esc_html($n['abbreviation']); ?></b></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="ad"><div class="mock">300 × 600</div></div>

      </div>
    </aside>
```

- [ ] **Step 2: Lint + smoke test**

```bash
php -l wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-13/?nocache=$(date +%s)" 2>&1 | grep -oE '<aside>|<div class="stick">|<h4>On This Page</h4>|<h4>Quick Facts</h4>|<h4>More Seasons</h4>|class="ad"' | head -10
```

Expected: aside, stick, all 3 widget headers, ad slot. (Quick Facts and More Seasons render conditionally — both should be present for BB13.)

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php
git commit -m "feat(season-profile): sidebar — TOC, Quick Facts, More Seasons, ad"
```

---

### Task 9: Caching layer + cache busters

Add `wp_cache_*` wrappers to the heaviest helpers and bust on relevant `save_post_*` events.

**Files:**
- Modify: `inc/season-profile-data.php`

- [ ] **Step 1: Wrap the 4 expensive helpers with cache get/set**

These do multiple SQL queries each; cache for 300s in the existing `bbj_v2` group. The lighter helpers (`_data`, `_facts`, `_ordinal`, `_neighbors`) stay uncached — they're cheap.

For each of `_cast`, `_evictions`, `_comps`, `_top_feed_updates`, `_articles`: add a cache-key shim at the top, return early if hit, set after compute. Example for `_cast`:

```php
function bbj_v2_season_profile_cast(int $post_id): array
{
    $cache_key = 'season_profile_cast_' . $post_id;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    // ... existing query body ...

    wp_cache_set($cache_key, $rows, 'bbj_v2', 300);
    return $rows;
}
```

Apply the same pattern to the other 4 helpers, with cache keys:
- `season_profile_cast_{id}`
- `season_profile_evictions_{id}`
- `season_profile_comps_{id}`
- `season_profile_top_feed_{id}`
- `season_profile_articles_{id}`

- [ ] **Step 2: Add cache buster hooks at the bottom of the file**

```php
/**
 * Bust the season profile object caches when relevant content changes.
 * Coarse strategy — bust everything for a season on any of its related saves.
 */
add_action('save_post_bigbrother-seasons',  'bbj_v2_season_profile_bust_cache_for_post');
add_action('save_post_bigbrother-players',  'bbj_v2_season_profile_bust_all_caches');
add_action('save_post_live-feed-updates',   'bbj_v2_season_profile_bust_current_feed_cache');
add_action('save_post_post',                'bbj_v2_season_profile_bust_all_articles_caches');

function bbj_v2_season_profile_bust_cache_for_post(int $post_id): void
{
    foreach (['cast', 'evictions', 'comps', 'top_feed', 'articles'] as $bucket) {
        wp_cache_delete('season_profile_' . $bucket . '_' . $post_id, 'bbj_v2');
    }
}

function bbj_v2_season_profile_bust_all_caches(): void
{
    // Player saves can affect any season — coarse bust by walking known seasons.
    $season_ids = get_posts([
        'post_type' => 'bigbrother-seasons', 'post_status' => 'publish',
        'numberposts' => -1, 'fields' => 'ids',
    ]);
    foreach ($season_ids as $sid) {
        bbj_v2_season_profile_bust_cache_for_post((int) $sid);
    }
}

function bbj_v2_season_profile_bust_current_feed_cache(): void
{
    $current = (int) get_option('bbj_v2_current_season');
    if ($current > 0) {
        wp_cache_delete('season_profile_top_feed_' . $current, 'bbj_v2');
    }
}

function bbj_v2_season_profile_bust_all_articles_caches(): void
{
    // Article changes — bust all season article caches (low frequency, coarse OK)
    $season_ids = get_posts([
        'post_type' => 'bigbrother-seasons', 'post_status' => 'publish',
        'numberposts' => -1, 'fields' => 'ids',
    ]);
    foreach ($season_ids as $sid) {
        wp_cache_delete('season_profile_articles_' . (int) $sid, 'bbj_v2');
    }
}
```

- [ ] **Step 3: Lint + smoke test (verify caching doesn't break rendering)**

```bash
php -l wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
# Hit the page twice — second should be cache-warm
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-13/?nocache=$(date +%s)" -o /tmp/p1.html -w "%{time_total}\n"
curl -s "http://bbj.localhost/bigbrother-seasons/big-brother-13/?nocache=$(date +%s)" -o /tmp/p2.html -w "%{time_total}\n"
diff /tmp/p1.html /tmp/p2.html | head -20
```

Expected: pages identical (no diff), second response noticeably faster (or at least not slower).

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
git commit -m "feat(season-profile): object caching + cache busters"
```

---

### Task 10: Deploy + staging verification + final polish

**Files:**
- (none modified — verification only)

- [ ] **Step 1: Push + deploy to staging**

```bash
git push origin staging
bash .claude/scripts/deploy-theme.sh --staging
```

- [ ] **Step 2: Smoke test 3 representative seasons on staging**

Each command should produce non-empty output for the listed sections.

```bash
# BB27 — current season, modern data, should have everything
echo "=== BB27 (modern) ==="
curl -s "https://stg-wp.bigbrotherjunkies.com/bigbrother-seasons/big-brother-27/?nocache=$(date +%s)" 2>&1 \
  | grep -oE '<h1>[^<]+|<section id="[a-z]+">|<h2>[^<]+|class="pill winner"|<div class="ad"' | head -25

# BB13 — Rachel won, legacy import, no wp_bbj_weeks data
echo "=== BB13 (legacy) ==="
curl -s "https://stg-wp.bigbrotherjunkies.com/bigbrother-seasons/big-brother-13/?nocache=$(date +%s)" 2>&1 \
  | grep -oE '<h1>[^<]+|<section id="[a-z]+">|<h2>[^<]+' | head -20

# BB7 — old, possibly minimal data
echo "=== BB7 (very old) ==="
curl -s "https://stg-wp.bigbrotherjunkies.com/bigbrother-seasons/big-brother-7/?nocache=$(date +%s)" 2>&1 \
  | grep -oE '<h1>[^<]+|<section id="[a-z]+">|<h2>[^<]+' | head -15
```

Expected:
- **BB27:** hero, switcher, sectionnav, all sections that have data (no winners section since current season has no finish_place=1 yet, but cast + memorable moments + maybe articles should appear).
- **BB13:** hero, switcher, sectionnav, overview, winners (Rachel), cast, articles. No evictions/comps if BB13 lacks `wp_bbj_weeks` data — that's expected graceful degradation.
- **BB7:** hero, switcher, overview, winners (if `finish_place=1` exists for someone), cast. Other sections may be absent.

- [ ] **Step 3: Visual eyeball check in browser**

Open each of the 3 URLs in a real browser. Verify:
- Tab nav sticks to top on scroll
- Clicking a tab smooth-scrolls to that section
- The active tab highlights as you scroll past sections
- Sidebar `.stick` stays in view on scroll
- Cast grid photos load (where `wp_bbj_players.profile_picture` is set)
- No initials bleed through where photos exist (same fix we did for player profile)
- Mobile breakpoint (< 1000px) collapses cleanly per the design's `@media` rules

If any visual issue, fix in `css/single-bigbrother-seasons.css` and commit a follow-up.

- [ ] **Step 4: Final commit if any visual fixups needed**

```bash
git add wp-content/themes/bbj-v2-theme/css/single-bigbrother-seasons.css
git commit -m "fix(season-profile): visual polish from staging review"
git push origin staging
bash .claude/scripts/deploy-theme.sh --staging
```

- [ ] **Step 5: Update memory note**

Append to `C:\Users\sbeli\.claude\projects\C--xampp-htdocs-bbj\memory\project_player_profile_state.md` (or create a new memory file if appropriate) noting that the season profile shipped with the same drift conventions, what works for which eras (BB22+ vs legacy), and what's still deferred (twists, ratings, polls, records, action buttons).

---

## Self-Review

Spec coverage:
- All 9 in-scope sections from the spec → mapped to Tasks 2-7. ✅
- Sidebar (TOC, Quick Facts, More Seasons, Ad) → Task 8. ✅
- Caching strategy with the keys named in the spec → Task 9. ✅
- File structure (template, helper, css, JS) → Task 1. ✅
- Conditional CSS + fonts enqueue → Task 1, Step 4. ✅
- Edge cases (LEFT JOIN, id-OR-post_id, finish_place) → embedded throughout. ✅
- Open issues from spec (articles convention, prize source, hero buttons, cast tags) → addressed in Task 7 Step 4 (articles), Task 2 Step 1 (prize era fallback), Task 2 Step 2 (single button only), Task 5 Step 3 (cast tags from finish_place). ✅
- Verification plan with 3 test seasons → Task 10 Step 2. ✅

Placeholder scan: no "TBD" / "implement later" / "similar to" used. The placeholder block in `season-profile.js` (Step 3 of Task 1) is replaced in Task 4 Step 3 with full code. ✅

Type consistency: all helpers return `array`, the keys used in the template (`$season['name']`, `$cm['profile_picture']`, etc.) match what the helpers return. ✅
