# Homepage Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the `bbj-v2-theme` homepage as a 3-column editorial layout with a status strip, rich feed-update cards, a new House Pulse chart, a 3×3 BB stories grid, and a repopulated sidebar. Add two new taxonomies (`update_type`, `update_location`) to the `live-feed-updates` CPT.

**Architecture:** Theme-side templates call a single data layer (`inc/homepage-data.php`) whose helpers each cache in the `bbj_v2` object-cache group with `save_post_*` + option + term hooks busting. Plugin registers two new non-hierarchical taxonomies and seeds starter terms on activation. No new JS on the critical path — House Pulse is server-rendered CSS bars; sticky ad uses pure `position: sticky`.

**Tech Stack:** WordPress, PHP 8.x, Tailwind CSS 3.4 (via theme's `npm run build`), WP object cache (`bbj_v2` group), vanilla JS only where already present.

**Spec:** `docs/superpowers/specs/2026-04-18-homepage-redesign-design.md`

**Working branch:** `feature/bbj-v2-theme`

---

## File Structure

**Plugin — new files (`wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/`):**
- `UpdateTypeTaxonomy.php` — registers `update_type`, seeds terms on activation
- `UpdateLocationTaxonomy.php` — registers `update_location`, seeds terms on activation

**Plugin — modified files:**
- `index.php` (or main bootstrap) — instantiate both taxonomy classes on `init`
- Existing season CPT Meta Box registration — add `bbj_next_show_override` field

**Theme — new files (`wp-content/themes/bbj-v2-theme/`):**
- `inc/homepage-data.php` — all homepage data + cache helpers
- `template-parts/home/status-strip.php`
- `template-parts/home/more-bb-spoilers.php`
- `template-parts/home/house-pulse.php`
- `template-parts/home/latest-feeds.php`
- `template-parts/home/more-bb-stories.php`
- `template-parts/content/feed-update-card.php`
- `template-parts/sidebar/season-stats.php`
- `template-parts/sidebar/recent-comments.php`
- `template-parts/sidebar/sticky-ad.php`
- `template-parts/sidebar/paramount-plus.php`
- `template-parts/sidebar/socials.php`

**Theme — modified files:**
- `front-page.php` — full rewrite, orchestrates the 3-col grid
- `functions.php` — `require_once` the new `inc/homepage-data.php`
- `template-parts/home/hero-post.php` — simplified markup, fits left column, 4:3 image
- `template-parts/home/houseboard.php` — reworked for sidebar card width
- `sidebar.php` — new widget list + order
- `src/css/style.css` — new component classes

**Theme — deleted files:**
- `template-parts/home/feed-updates.php` — replaced by `latest-feeds.php`
- `template-parts/home/recent-posts.php` — replaced by `more-bb-stories.php`

---

## Gotchas you'll hit (read once before starting)

- **REST `register_rest_route` without `permission_callback` leaks notices inline** — if you register any route in the plugin changes, always include `permission_callback`. See `memory/feedback_rest_permission_callback_notices.md`.
- **Caching group is `bbj_v2`** — all helpers use this group so the existing Breeze/Varnish purge hooks catch them.
- **Current season data**: the option `bbj_v2_current_season` holds the season CPT's post ID (not the season number). To get the number, read it from `wp_bbj_seasons.season_number` via the helper `bbj_v2_get_season_by_id($id)` (already exists in the plugin).
- **`data-nosnippet` on relative times** — wrap "18 min ago" style strings to avoid stale Google snippets. See `memory/feedback_seo_time_handling.md`.
- **Asset versions use `bbj_v2_asset_ver()`** — filemtime-based, already in `inc/enqueue.php`. Don't use the static `BBJ_V2_THEME_VERSION` constant.
- **Mobile ad placeholders** take `mobile_size` arg for breakpoint-swapped boxes. Pattern established in header leaderboard.
- **Don't run `git add .`** — the repo has uncommitted legacy files. Add specific paths only.

---

## Task 1: Plugin — register `update_type` taxonomy

**Files:**
- Create: `wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateTypeTaxonomy.php`
- Modify: the plugin's main bootstrap file (search `wp-content/plugins/bigbrotherjunkies-data/` for the class that `new`s up other features, and add an instantiation call on `init`).

- [ ] **Step 1: Create the taxonomy class**

Create `wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateTypeTaxonomy.php`:

```php
<?php

namespace BigBrotherJunkies\Data\Taxonomies;

class UpdateTypeTaxonomy
{
    private const TAXONOMY = 'update_type';
    private const POST_TYPE = 'live-feed-updates';

    private const STARTER_TERMS = [
        'Drama', 'Ceremony', 'Strategy', 'Competition',
        'Alliance', 'Eviction', 'Punishment', 'Reward', 'Showmance',
    ];

    public function init(): void
    {
        add_action('init', [$this, 'register'], 11);
        register_activation_hook(BBJD_PLUGIN_FILE, [$this, 'seedTerms']);
    }

    public function register(): void
    {
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => 'Update Types',
                'singular_name' => 'Update Type',
                'menu_name'     => 'Update Types',
                'add_new_item'  => 'Add New Update Type',
                'search_items'  => 'Search Update Types',
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'show_in_graphql'   => true,
            'graphql_single_name' => 'updateType',
            'graphql_plural_name' => 'updateTypes',
            'rewrite'           => ['slug' => 'update-type'],
        ]);
    }

    public function seedTerms(): void
    {
        // Must run post-init so taxonomy is registered.
        foreach (self::STARTER_TERMS as $name) {
            if (!term_exists($name, self::TAXONOMY)) {
                wp_insert_term($name, self::TAXONOMY);
            }
        }
    }
}
```

- [ ] **Step 2: Verify `BBJD_PLUGIN_FILE` constant exists**

Run: `grep -n "BBJD_PLUGIN_FILE\|define.*PLUGIN_FILE" wp-content/plugins/bigbrotherjunkies-data/bigbrotherjunkies-data.php`

Expected: one `define('BBJD_PLUGIN_FILE', __FILE__);` line near the top. If it doesn't exist, add it to the main plugin file right after the plugin header comment:

```php
define('BBJD_PLUGIN_FILE', __FILE__);
```

- [ ] **Step 3: Wire the class into the plugin's bootstrap**

Find the plugin's bootstrap class (usually in the main file or `src/Plugin.php`). Look for where other features (like `SpoilerBarRoutes`) get instantiated and called. Add:

```php
use BigBrotherJunkies\Data\Taxonomies\UpdateTypeTaxonomy;

// In the bootstrap's init() or equivalent:
(new UpdateTypeTaxonomy())->init();
```

- [ ] **Step 4: Activate the terms**

Run WP-CLI (or deactivate + reactivate the plugin in admin):

```bash
wp plugin deactivate bigbrotherjunkies-data && wp plugin activate bigbrotherjunkies-data
```

If WP-CLI isn't installed, toggle the plugin off/on in wp-admin Plugins page.

- [ ] **Step 5: Verify the taxonomy + terms exist**

```bash
wp term list update_type --fields=name,slug
```

Expected: 9 rows — Drama / drama, Ceremony / ceremony, … Showmance / showmance.

Or, verify via SQL:

```bash
mysql -u root bbj_db -e "SELECT t.name, t.slug FROM wp_terms t JOIN wp_term_taxonomy tt ON t.term_id=tt.term_id WHERE tt.taxonomy='update_type';"
```

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateTypeTaxonomy.php wp-content/plugins/bigbrotherjunkies-data/bigbrotherjunkies-data.php
git commit -m "feat(plugin): add update_type taxonomy on live-feed-updates + seed terms"
```

---

## Task 2: Plugin — register `update_location` taxonomy

**Files:**
- Create: `wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateLocationTaxonomy.php`
- Modify: plugin bootstrap (add instantiation)

- [ ] **Step 1: Create the class**

Create `wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateLocationTaxonomy.php`:

```php
<?php

namespace BigBrotherJunkies\Data\Taxonomies;

class UpdateLocationTaxonomy
{
    private const TAXONOMY = 'update_location';
    private const POST_TYPE = 'live-feed-updates';

    private const STARTER_TERMS = [
        'HoH Bathroom', 'HoH Room', 'Backyard', 'Hammock',
        'Kitchen', 'Living Room', 'Have-Not Room', 'Storage',
        'Pergola', 'Bathroom', 'Diary Room',
    ];

    public function init(): void
    {
        add_action('init', [$this, 'register'], 11);
        register_activation_hook(BBJD_PLUGIN_FILE, [$this, 'seedTerms']);
    }

    public function register(): void
    {
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'hierarchical'      => false,
            'labels'            => [
                'name'          => 'Update Locations',
                'singular_name' => 'Update Location',
                'menu_name'     => 'Update Locations',
                'add_new_item'  => 'Add New Update Location',
                'search_items'  => 'Search Update Locations',
            ],
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'show_in_graphql'   => true,
            'graphql_single_name' => 'updateLocation',
            'graphql_plural_name' => 'updateLocations',
            'rewrite'           => ['slug' => 'update-location'],
        ]);
    }

    public function seedTerms(): void
    {
        foreach (self::STARTER_TERMS as $name) {
            if (!term_exists($name, self::TAXONOMY)) {
                wp_insert_term($name, self::TAXONOMY);
            }
        }
    }
}
```

- [ ] **Step 2: Wire into bootstrap**

Same bootstrap file as Task 1; add:

```php
use BigBrotherJunkies\Data\Taxonomies\UpdateLocationTaxonomy;

(new UpdateLocationTaxonomy())->init();
```

- [ ] **Step 3: Reactivate plugin to seed terms**

```bash
wp plugin deactivate bigbrotherjunkies-data && wp plugin activate bigbrotherjunkies-data
```

- [ ] **Step 4: Verify**

```bash
wp term list update_location --fields=name,slug
```

Expected: 11 rows (HoH Bathroom, HoH Room, Backyard, Hammock, Kitchen, Living Room, Have-Not Room, Storage, Pergola, Bathroom, Diary Room).

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateLocationTaxonomy.php wp-content/plugins/bigbrotherjunkies-data/bigbrotherjunkies-data.php
git commit -m "feat(plugin): add update_location taxonomy on live-feed-updates + seed terms"
```

---

## Task 3: Theme — season helpers in new `inc/homepage-data.php`

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/inc/homepage-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/functions.php`

- [ ] **Step 1: Create the helpers file with season helpers**

Create `wp-content/themes/bbj-v2-theme/inc/homepage-data.php`:

```php
<?php
/**
 * Homepage data layer — cached queries shared across the home template parts.
 * All helpers cache in the `bbj_v2` object-cache group and are busted via
 * save_post_* / option / term hooks registered at the bottom of this file.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Numeric season number (e.g. 26) of the currently configured season.
 * Returns 0 if no current season is set or the CPT lookup fails.
 */
function bbj_v2_current_season_number(): int
{
    $cache_key = 'homepage_current_season_number';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if ($cached !== false) {
        return (int) $cached;
    }

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    if ($season_id <= 0) {
        wp_cache_set($cache_key, 0, 'bbj_v2', 300);
        return 0;
    }

    $num = 0;
    if (function_exists('bbj_v2_get_season_by_id')) {
        $season = bbj_v2_get_season_by_id($season_id);
        $num = (int) ($season['season_number'] ?? 0);
    }

    wp_cache_set($cache_key, $num, 'bbj_v2', 300);
    return $num;
}

/**
 * Category slug for the current season's posts (e.g. "big-brother-26").
 * Returns '' if no current season.
 */
function bbj_v2_current_season_slug(): string
{
    $cache_key = 'homepage_current_season_slug';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if ($cached !== false) {
        return (string) $cached;
    }

    $num = bbj_v2_current_season_number();
    $slug = $num > 0 ? "big-brother-{$num}" : '';

    wp_cache_set($cache_key, $slug, 'bbj_v2', 300);
    return $slug;
}

/**
 * Whether the current season is still active (finale not yet reached).
 * Decision order:
 *   1. Manual override via option `bbj_v2_season_active` (0 | 1). Respected if set.
 *   2. Otherwise: current season's `end_date` in the future → active.
 *   3. Fallback: true (fail open).
 */
function bbj_v2_is_active_season(): bool
{
    $cache_key = 'homepage_active_season';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if ($cached !== false) {
        return (bool) $cached;
    }

    $override = get_option('bbj_v2_season_active', null);
    if ($override !== null && $override !== '') {
        $active = (bool) (int) $override;
        wp_cache_set($cache_key, $active ? 1 : 0, 'bbj_v2', 300);
        return $active;
    }

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    $active = true;
    if ($season_id > 0 && function_exists('bbj_v2_get_season_by_id')) {
        $season = bbj_v2_get_season_by_id($season_id);
        $end    = $season['end_date'] ?? '';
        if ($end !== '') {
            $end_ts = strtotime($end);
            if ($end_ts !== false && $end_ts < time()) {
                $active = false;
            }
        }
    }

    wp_cache_set($cache_key, $active ? 1 : 0, 'bbj_v2', 300);
    return $active;
}
```

- [ ] **Step 2: Require the file from `functions.php`**

Edit `wp-content/themes/bbj-v2-theme/functions.php`, add after the existing `require_once` block:

```php
require_once BBJ_V2_THEME_PATH . '/inc/homepage-data.php';
```

- [ ] **Step 3: Smoke-test in a browser**

Load the WP admin → Tools → Site Health (or any admin page). If there are new PHP errors, they'll show in the error bar or in `wp-content/debug.log`.

Verify the helpers return real data by dropping this into a scratch template (or running via WP-CLI eval):

```bash
wp eval 'echo bbj_v2_current_season_number();'  # expect 26 (or whatever your current season is)
wp eval 'echo bbj_v2_current_season_slug();'    # expect "big-brother-26"
wp eval 'var_dump(bbj_v2_is_active_season());'  # expect bool(true) (or false if past end_date)
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/homepage-data.php wp-content/themes/bbj-v2-theme/functions.php
git commit -m "feat(theme): add homepage data layer with season helpers"
```

---

## Task 4: Theme — content-query helpers in `homepage-data.php`

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/homepage-data.php` (append)

- [ ] **Step 1: Append query helpers**

Append to `inc/homepage-data.php` (above the closing PHP):

```php
/**
 * Return the hero post for the homepage.
 * Priority: most recent post with `_is_hero_post` meta = "1", fallback to
 * most recent post in the current-season category, final fallback to most
 * recent post of any category.
 *
 * Not cached — this is one WP_Query and exclusion IDs must match exactly.
 *
 * @return WP_Post|null
 */
function bbj_v2_homepage_hero_post(): ?WP_Post
{
    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_is_hero_post',
        'meta_value'     => '1',
        'no_found_rows'  => true,
    ]);
    if ($q->have_posts()) {
        return $q->posts[0];
    }

    $slug = bbj_v2_current_season_slug();
    if ($slug !== '') {
        $q = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'category_name'  => $slug,
            'no_found_rows'  => true,
        ]);
        if ($q->have_posts()) {
            return $q->posts[0];
        }
    }

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);
    return $q->have_posts() ? $q->posts[0] : null;
}

/**
 * 3 most recent posts in (current season) AND `spoilers` categories,
 * excluding a supplied list of post IDs.
 *
 * @param int[] $exclude_ids
 * @return WP_Post[]
 */
function bbj_v2_homepage_more_spoilers(array $exclude_ids = []): array
{
    $cache_key = 'homepage_more_spoilers_' . md5(serialize($exclude_ids));
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $season_term = get_term_by('slug', bbj_v2_current_season_slug(), 'category');
    $spoilers    = get_term_by('slug', 'spoilers', 'category');
    if (!$season_term || !$spoilers) {
        wp_cache_set($cache_key, [], 'bbj_v2', 300);
        return [];
    }

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 3,
        'category__and'  => [(int) $season_term->term_id, (int) $spoilers->term_id],
        'post__not_in'   => array_map('intval', $exclude_ids),
        'no_found_rows'  => true,
    ]);

    wp_cache_set($cache_key, $q->posts, 'bbj_v2', 300);
    return $q->posts;
}

/**
 * 9 most recent posts in the current-season category, excluding given IDs.
 *
 * @param int[] $exclude_ids
 * @return WP_Post[]
 */
function bbj_v2_homepage_bb_stories(array $exclude_ids = []): array
{
    $cache_key = 'homepage_bb_stories_' . md5(serialize($exclude_ids));
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $slug = bbj_v2_current_season_slug();
    if ($slug === '') {
        wp_cache_set($cache_key, [], 'bbj_v2', 300);
        return [];
    }

    $q = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 9,
        'category_name'  => $slug,
        'post__not_in'   => array_map('intval', $exclude_ids),
        'no_found_rows'  => true,
    ]);

    wp_cache_set($cache_key, $q->posts, 'bbj_v2', 300);
    return $q->posts;
}

/**
 * 15 most recent live-feed-updates, each decorated with its
 * update_type + update_location term names/slugs/colors.
 *
 * @return array<int, array{post: WP_Post, type: ?array, location: ?array}>
 */
function bbj_v2_homepage_latest_feeds(int $limit = 15): array
{
    $cache_key = 'homepage_feeds_' . $limit;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $q = new WP_Query([
        'post_type'      => 'live-feed-updates',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'no_found_rows'  => true,
    ]);

    $out = [];
    foreach ($q->posts as $post) {
        $type_terms = get_the_terms($post->ID, 'update_type');
        $loc_terms  = get_the_terms($post->ID, 'update_location');
        $type = (is_array($type_terms) && !empty($type_terms)) ? [
            'name' => $type_terms[0]->name,
            'slug' => $type_terms[0]->slug,
        ] : null;
        $loc = (is_array($loc_terms) && !empty($loc_terms)) ? [
            'name' => $loc_terms[0]->name,
            'slug' => $loc_terms[0]->slug,
        ] : null;
        $out[] = ['post' => $post, 'type' => $type, 'location' => $loc];
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 60);
    return $out;
}
```

- [ ] **Step 2: Smoke via WP-CLI**

```bash
wp eval 'var_dump(count(bbj_v2_homepage_more_spoilers()));'   # expect <= 3
wp eval 'var_dump(count(bbj_v2_homepage_bb_stories()));'      # expect <= 9
wp eval 'var_dump(count(bbj_v2_homepage_latest_feeds()));'    # expect <= 15
wp eval 'var_dump(bbj_v2_homepage_hero_post()?->post_title);' # expect a post title
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/homepage-data.php
git commit -m "feat(theme): add homepage content-query helpers (spoilers, stories, feeds)"
```

---

## Task 5: Theme — House Pulse + Season Stats + Status + Recent Comments helpers

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/homepage-data.php` (append)

- [ ] **Step 1: Append the House Pulse helper**

```php
/**
 * Hourly feed-update counts for the last `$hours` hours.
 * Returns 8 entries (oldest first) — missing hours filled with 0.
 *
 * @return array<int, array{hour: int, label: string, count: int}>
 */
function bbj_v2_homepage_house_pulse(int $hours = 8): array
{
    $cache_key = 'homepage_pulse_' . $hours;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT HOUR(post_date) AS h, COUNT(*) AS c
         FROM {$wpdb->posts}
         WHERE post_type = 'live-feed-updates'
           AND post_status = 'publish'
           AND post_date >= DATE_SUB(NOW(), INTERVAL %d HOUR)
         GROUP BY HOUR(post_date)",
        $hours
    ), ARRAY_A);

    $counts = [];
    foreach ($rows as $r) {
        $counts[(int) $r['h']] = (int) $r['c'];
    }

    $out = [];
    for ($i = $hours - 1; $i >= 0; $i--) {
        $ts   = strtotime("-{$i} hour");
        $hour = (int) date('H', $ts);
        $label = date('ga', $ts); // "3pm"
        $out[] = [
            'hour'  => $hour,
            'label' => $label,
            'count' => $counts[$hour] ?? 0,
        ];
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 300);
    return $out;
}

/**
 * Season stats: top 3 HoH, PoV, Nominee counts for the current season.
 *
 * @return array{hoh: array, pov: array, noms: array, total_weeks: int}
 */
function bbj_v2_homepage_season_stats(): array
{
    $cache_key = 'homepage_season_stats';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    $empty = ['hoh' => [], 'pov' => [], 'noms' => [], 'total_weeks' => 0];
    if ($season_id <= 0) {
        wp_cache_set($cache_key, $empty, 'bbj_v2', 300);
        return $empty;
    }

    global $wpdb;
    $link_table   = defined('BBJ_V2_TABLE_LINKS')   ? BBJ_V2_TABLE_LINKS   : ($wpdb->prefix . 'bbj_v2_player_season');
    $player_table = defined('BBJ_V2_TABLE_PLAYERS') ? BBJ_V2_TABLE_PLAYERS : ($wpdb->prefix . 'bbj_players');

    // Columns confirmed via DESCRIBE:
    //   link table: bbj_player, bbj_season, bbj_total_hoh, bbj_total_pov, bbj_total_nom
    //   players:    id (== post_id), first_name, last_name, official_nickname
    $fetch = function (string $col) use ($wpdb, $link_table, $player_table, $season_id): array {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.first_name, p.last_name, p.official_nickname, l.{$col} AS count
             FROM {$link_table} l
             INNER JOIN {$player_table} p ON p.id = l.bbj_player
             WHERE l.bbj_season = %d AND l.{$col} > 0
             ORDER BY l.{$col} DESC, p.first_name ASC
             LIMIT 3",
            $season_id
        ), ARRAY_A) ?: [];
    };

    $out = [
        'hoh'          => $fetch('bbj_total_hoh'),
        'pov'          => $fetch('bbj_total_pov'),
        'noms'         => $fetch('bbj_total_nom'),
        'total_weeks'  => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(bbj_total_hoh + bbj_total_pov), 0)
             FROM {$link_table} WHERE bbj_season = %d",
            $season_id
        )),
    ];

    wp_cache_set($cache_key, $out, 'bbj_v2', 300);
    return $out;
}

/**
 * Last N approved comments, site-wide.
 *
 * @return WP_Comment[]
 */
function bbj_v2_homepage_recent_comments(int $limit = 5): array
{
    $cache_key = 'homepage_recent_comments_' . $limit;
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $comments = get_comments([
        'number'  => $limit,
        'status'  => 'approve',
        'type'    => 'comment',
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
    ]);

    wp_cache_set($cache_key, $comments, 'bbj_v2', 60);
    return $comments;
}

/**
 * Status strip payload: day number, percent elapsed, next CBS show string.
 *
 * @return array{
 *   active: bool,
 *   season_number: int,
 *   day_number: ?int,
 *   percent_elapsed: ?int,
 *   next_show: ?string,
 *   premiere_label: ?string,
 * }
 */
function bbj_v2_homepage_status(): array
{
    $cache_key = 'homepage_status';
    $cached    = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    $out = [
        'active'          => bbj_v2_is_active_season(),
        'season_number'   => bbj_v2_current_season_number(),
        'day_number'      => null,
        'percent_elapsed' => null,
        'next_show'       => null,
        'premiere_label'  => null,
    ];

    $season_id = (int) get_option('bbj_v2_current_season', 0);
    if ($season_id > 0 && function_exists('bbj_v2_get_season_by_id')) {
        $season = bbj_v2_get_season_by_id($season_id);
        $start  = !empty($season['start_date']) ? strtotime((string) $season['start_date']) : false;
        $end    = !empty($season['end_date'])   ? strtotime((string) $season['end_date'])   : false;

        if ($start !== false) {
            $now = time();
            $out['day_number'] = max(1, (int) floor(($now - $start) / DAY_IN_SECONDS) + 1);
            if ($end !== false && $end > $start) {
                $pct = (int) round((($now - $start) / ($end - $start)) * 100);
                $out['percent_elapsed'] = max(0, min(100, $pct));
            }
        }
    }

    if ($out['active']) {
        $override = $season_id > 0
            ? get_post_meta($season_id, 'bbj_next_show_override', true)
            : '';
        $out['next_show'] = bbj_v2_format_next_cbs_show((string) $override);
    } else {
        $premiere = get_option('bbj_next_season_premiere', '');
        $out['premiere_label'] = $premiere ? date('M j', (int) strtotime((string) $premiere)) : null;
    }

    wp_cache_set($cache_key, $out, 'bbj_v2', 60);
    return $out;
}

/**
 * Format the next CBS airing label ("Tonight at 8pm ET / 5pm PT" etc.).
 * If $override (a date string) is a future datetime, it wins.
 */
function bbj_v2_format_next_cbs_show(string $override = ''): string
{
    $et = new DateTimeZone('America/New_York');
    $pt = new DateTimeZone('America/Los_Angeles');
    $now_et = new DateTimeImmutable('now', $et);

    $target_et = null;
    if ($override !== '') {
        try {
            $candidate = new DateTimeImmutable($override, $et);
            if ($candidate > $now_et) {
                $target_et = $candidate;
            }
        } catch (Exception $_) {}
    }

    if ($target_et === null) {
        // Weekly rule: next Sun / Wed / Thu at 20:00 ET.
        $days = [0 /* Sun */, 3 /* Wed */, 4 /* Thu */];
        $best = null;
        foreach ($days as $d) {
            $candidate = $now_et->setTime(20, 0);
            while ((int) $candidate->format('w') !== $d || $candidate <= $now_et) {
                $candidate = $candidate->modify('+1 day')->setTime(20, 0);
            }
            if ($best === null || $candidate < $best) {
                $best = $candidate;
            }
        }
        $target_et = $best;
    }

    if ($target_et === null) return '';
    $target_pt = $target_et->setTimezone($pt);

    $diff_days = (int) $now_et->diff($target_et)->format('%a');
    $time_part = $target_et->format('ga') . ' ET / ' . $target_pt->format('ga') . ' PT';

    if ($target_et->format('Y-m-d') === $now_et->format('Y-m-d')) {
        return 'Tonight at ' . $time_part;
    }
    if ($target_et->format('Y-m-d') === $now_et->modify('+1 day')->format('Y-m-d')) {
        return 'Tomorrow at ' . $time_part;
    }
    if ($diff_days <= 6) {
        return $target_et->format('D') . ' at ' . $time_part;
    }
    return $target_et->format('M j') . ' at ' . $time_part;
}
```

- [ ] **Step 2: Smoke via WP-CLI**

```bash
wp eval 'print_r(bbj_v2_homepage_house_pulse());'    # expect 8 rows with hour/label/count
wp eval 'print_r(bbj_v2_homepage_season_stats());'   # expect hoh/pov/noms arrays + total_weeks
wp eval 'print_r(bbj_v2_homepage_status());'          # expect day_number + percent_elapsed + next_show
wp eval 'echo bbj_v2_format_next_cbs_show();'         # expect a formatted string
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/homepage-data.php
git commit -m "feat(theme): add house pulse, season stats, status, and recent comments helpers"
```

---

## Task 6: Theme — cache-bust hooks in `homepage-data.php`

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/homepage-data.php` (append)

- [ ] **Step 1: Append invalidation hooks**

```php
/**
 * Cache invalidation — bust homepage keys when the underlying data moves.
 */
add_action('save_post_post',                 'bbj_v2_homepage_bust_posts');
add_action('save_post_live-feed-updates',    'bbj_v2_homepage_bust_feeds');
add_action('save_post_bigbrother-seasons',   'bbj_v2_homepage_bust_seasons');
add_action('update_option_bbj_v2_current_season', 'bbj_v2_homepage_bust_all');
add_action('update_option_bbj_v2_season_active',  'bbj_v2_homepage_bust_all');
add_action('comment_post',                   'bbj_v2_homepage_bust_comments', 10, 3);
add_action('wp_set_comment_status',          'bbj_v2_homepage_bust_comments');
add_action('created_update_type',            'bbj_v2_homepage_bust_feeds');
add_action('edited_update_type',             'bbj_v2_homepage_bust_feeds');
add_action('created_update_location',        'bbj_v2_homepage_bust_feeds');
add_action('edited_update_location',         'bbj_v2_homepage_bust_feeds');

function bbj_v2_homepage_bust_posts(): void
{
    wp_cache_delete('homepage_more_spoilers_' . md5(serialize([])), 'bbj_v2');
    wp_cache_delete('homepage_bb_stories_'    . md5(serialize([])), 'bbj_v2');
    // Any key built with different exclude lists will eventually expire on its own (300s).
}

function bbj_v2_homepage_bust_feeds(): void
{
    wp_cache_delete('homepage_feeds_15', 'bbj_v2');
    wp_cache_delete('homepage_pulse_8',  'bbj_v2');
}

function bbj_v2_homepage_bust_seasons(): void
{
    wp_cache_delete('homepage_status',                 'bbj_v2');
    wp_cache_delete('homepage_season_stats',           'bbj_v2');
    wp_cache_delete('homepage_active_season',          'bbj_v2');
    wp_cache_delete('homepage_current_season_number',  'bbj_v2');
    wp_cache_delete('homepage_current_season_slug',    'bbj_v2');
}

function bbj_v2_homepage_bust_all(): void
{
    bbj_v2_homepage_bust_posts();
    bbj_v2_homepage_bust_feeds();
    bbj_v2_homepage_bust_seasons();
}

function bbj_v2_homepage_bust_comments(): void
{
    wp_cache_delete('homepage_recent_comments_5', 'bbj_v2');
}
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/homepage-data.php
git commit -m "feat(theme): add cache-bust hooks for homepage helpers"
```

---

## Task 7: Theme — Status strip template

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/home/status-strip.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * Homepage status strip — BB<N> · Day X · % elapsed · Next CBS show · BB Time.
 * Off-season variant: OFF-SEASON · LAST SEASON: BB<N> · BB<N+1> PREMIERES <date>.
 */

if (!defined('ABSPATH')) {
    exit;
}

$status = bbj_v2_homepage_status();
$sep    = '<span class="px-2 text-gray-500" aria-hidden="true">·</span>';
?>
<section class="bbj-status-strip" aria-label="<?php esc_attr_e('Current season status', 'bbj-v2-theme'); ?>">
    <div class="mx-auto max-w-screen-xl px-4 py-2 flex items-center flex-wrap gap-y-1 font-osw uppercase tracking-wider text-xs sm:text-sm text-white">

        <?php if ($status['active'] && $status['season_number'] > 0) : ?>
            <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-accent-red animate-pulse" aria-hidden="true"></span>
                <strong class="text-secondary-500">BB<?php echo (int) $status['season_number']; ?></strong>
                <?php if ($status['day_number'] !== null) : ?>
                    <span>· Day <?php echo (int) $status['day_number']; ?></span>
                <?php endif; ?>
            </span>

            <?php if ($status['percent_elapsed'] !== null) : ?>
                <?php echo $sep; ?>
                <span><?php echo (int) $status['percent_elapsed']; ?>% Elapsed</span>
            <?php endif; ?>

            <?php if (!empty($status['next_show'])) : ?>
                <?php echo $sep; ?>
                <span>Next CBS show: <?php echo esc_html($status['next_show']); ?></span>
            <?php endif; ?>

            <?php echo $sep; ?>
            <span data-nosnippet>BB Time <?php echo esc_html(bbj_v2_bb_time()); ?></span>

        <?php else : ?>
            <span class="text-secondary-500 font-bold">Off-season</span>

            <?php if ($status['season_number'] > 0) : ?>
                <?php echo $sep; ?>
                <span>Last season: BB<?php echo (int) $status['season_number']; ?></span>
            <?php endif; ?>

            <?php if (!empty($status['premiere_label'])) : ?>
                <?php echo $sep; ?>
                <span>BB<?php echo (int) $status['season_number'] + 1; ?> premieres <?php echo esc_html($status['premiere_label']); ?></span>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>
```

- [ ] **Step 2: Add CSS in `src/css/style.css`**

Inside the `@layer components` block, add:

```css
.bbj-status-strip {
    @apply bg-gray-900 dark:bg-black border-b border-gray-700 text-white;
}
```

- [ ] **Step 3: Rebuild Tailwind and smoke-test**

```bash
cd wp-content/themes/bbj-v2-theme && npm run build
```

Temporarily include the template from any page (or add a `?debug_strip=1` check in header for a one-off test). Verify:
- Active season → shows BB<N> · Day X · % · Next CBS · BB Time
- Off-season (toggle `bbj_v2_season_active=0` in wp_options) → shows off-season variant

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/status-strip.php wp-content/themes/bbj-v2-theme/src/css/style.css wp-content/themes/bbj-v2-theme/build/style.css
git commit -m "feat(theme): add homepage status strip template"
```

---

## Task 8: Theme — rewrite hero-post for left column

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/home/hero-post.php`

- [ ] **Step 1: Replace the file contents**

```php
<?php
/**
 * Homepage hero post — lives in the left column of the 3-col grid.
 * Renders the post selected by bbj_v2_homepage_hero_post() with an <h1>,
 * 4:3 image with fetchpriority=high, excerpt, and byline.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero = bbj_v2_homepage_hero_post();
if (!$hero) {
    return;
}

$thumb_id = (int) get_post_thumbnail_id($hero->ID);
$alt      = $thumb_id ? (get_post_meta($thumb_id, '_wp_attachment_image_alt', true) ?: $hero->post_title) : $hero->post_title;
?>
<article class="bbj-hero-post">
    <a href="<?php echo esc_url(get_permalink($hero->ID)); ?>" class="block group">
        <?php if ($thumb_id) : ?>
            <div class="aspect-[4/3] overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                <?php echo wp_get_attachment_image(
                    $thumb_id,
                    'bbj_v2_index_hero',
                    false,
                    [
                        'alt'           => esc_attr($alt),
                        'class'         => 'w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300',
                        'fetchpriority' => 'high',
                        'loading'       => 'eager',
                        'decoding'      => 'async',
                    ]
                ); ?>
            </div>
        <?php endif; ?>
        <h1 class="mt-4 font-display text-3xl md:text-4xl lg:text-5xl leading-tight text-primary-500 dark:text-secondary-500 group-hover:text-primary-600 dark:group-hover:text-secondary-400 transition-colors">
            <?php echo esc_html($hero->post_title); ?>
        </h1>
    </a>

    <?php
    $excerpt = $hero->post_excerpt !== ''
        ? $hero->post_excerpt
        : wp_trim_words(strip_shortcodes($hero->post_content), 40, '…');
    ?>
    <?php if ($excerpt !== '') : ?>
        <p class="mt-3 text-gray-700 dark:text-gray-300 leading-relaxed"><?php echo esc_html($excerpt); ?></p>
    <?php endif; ?>

    <div class="mt-3 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-osw" data-nosnippet>
        <time datetime="<?php echo esc_attr(get_the_date('c', $hero)); ?>"><?php echo esc_html(get_the_date('F j, Y', $hero)); ?></time>
        <span class="mx-1">·</span>
        <?php echo esc_html(get_the_author_meta('display_name', $hero->post_author)); ?>
    </div>
</article>
```

- [ ] **Step 2: Add CSS helper**

In `src/css/style.css` (inside `@layer components`):

```css
.bbj-hero-post { @apply block; }
```

- [ ] **Step 3: Rebuild**

```bash
cd wp-content/themes/bbj-v2-theme && npm run build
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/hero-post.php wp-content/themes/bbj-v2-theme/src/css/style.css wp-content/themes/bbj-v2-theme/build/style.css
git commit -m "refactor(theme): rebuild hero-post for 4:3 left-column layout with H1"
```

---

## Task 9: Theme — More BB<N> Spoilers center-column template

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/home/more-bb-spoilers.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * Center-column block: "More BB<N> Spoilers" — 3 latest posts in
 * (current-season-slug AND spoilers) categories, with a square ad below.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero        = bbj_v2_homepage_hero_post();
$exclude     = $hero ? [$hero->ID] : [];
$posts       = bbj_v2_homepage_more_spoilers($exclude);
$season_num  = bbj_v2_current_season_number();
$active      = bbj_v2_is_active_season();
$heading     = $active
    ? sprintf(__('More BB%d Spoilers', 'bbj-v2-theme'), $season_num)
    : sprintf(__('BB%d Recap', 'bbj-v2-theme'), $season_num);
?>
<section id="more-bb-spoilers" class="bbj-more-spoilers">
    <h2 class="section-header mb-4">
        <a href="<?php echo esc_url(home_url('/category/spoilers/')); ?>" class="no-underline hover:text-secondary-500">
            <?php echo esc_html($heading); ?>
        </a>
    </h2>

    <?php if (empty($posts)) : ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <?php esc_html_e('No recent spoilers yet. Check back soon.', 'bbj-v2-theme'); ?>
        </p>
    <?php else : ?>
        <ol class="space-y-3">
            <?php foreach ($posts as $i => $p) : ?>
                <li class="flex gap-3 items-start border-b border-gray-200 dark:border-gray-700 pb-3 last:border-b-0">
                    <span class="font-osw text-primary-500 dark:text-secondary-500 text-lg w-7 shrink-0" aria-hidden="true">
                        <?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?>
                    </span>
                    <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="flex gap-3 group">
                        <?php if (has_post_thumbnail($p->ID)) : ?>
                            <div class="w-16 h-16 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                <?php echo get_the_post_thumbnail($p->ID, 'featured-thumbnail', [
                                    'class'    => 'w-full h-full object-cover',
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <h3 class="text-sm font-osw uppercase tracking-wide text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors leading-snug">
                                <?php echo esc_html($p->post_title); ?>
                            </h3>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400" data-nosnippet>
                                <time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>"><?php echo esc_html(get_the_date('M j', $p)); ?></time>
                            </div>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <div class="mt-6">
        <?php get_template_part('template-parts/components/ad-placeholder', null, [
            'slot'        => 'homepage_right_mpu',
            'size'        => '300x250',
            'mobile_size' => '300x250',
            'note'        => __('Homepage · Square / MPU', 'bbj-v2-theme'),
        ]); ?>
    </div>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/more-bb-spoilers.php
git commit -m "feat(theme): add More BB-N Spoilers center-column template"
```

---

## Task 10: Theme — House Pulse template

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/home/house-pulse.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * House Pulse — server-rendered 8-hour bar chart of live-feed-updates/hour.
 * Hidden off-season.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!bbj_v2_is_active_season()) {
    return;
}

$buckets = bbj_v2_homepage_house_pulse(8);
$max     = max(array_map(static fn($b) => (int) $b['count'], $buckets) ?: [0]);
$total   = array_sum(array_map(static fn($b) => (int) $b['count'], $buckets));
?>
<section id="house-pulse" class="bbj-house-pulse" aria-label="<?php esc_attr_e('Feed activity last 8 hours', 'bbj-v2-theme'); ?>">
    <div class="flex items-baseline justify-between mb-2">
        <h2 class="section-header"><?php esc_html_e('House Pulse', 'bbj-v2-theme'); ?></h2>
        <span class="text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider">
            <?php esc_html_e('Updates/hr · last 8 hours', 'bbj-v2-theme'); ?>
        </span>
    </div>

    <?php if ($total === 0) : ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <?php esc_html_e('Quiet house · no updates in the last 8 hours.', 'bbj-v2-theme'); ?>
        </p>
    <?php else : ?>
        <div class="flex gap-1 items-end h-12" role="img" aria-label="<?php esc_attr_e('Updates per hour, last 8 hours', 'bbj-v2-theme'); ?>">
            <?php foreach ($buckets as $b) :
                $count = (int) $b['count'];
                $ratio = $max > 0 ? $count / $max : 0;
                if ($ratio === 0)        { $color = 'bg-gray-200 dark:bg-gray-700'; }
                elseif ($ratio <= 0.2)   { $color = 'bg-amber-200'; }
                elseif ($ratio <= 0.5)   { $color = 'bg-amber-400'; }
                elseif ($ratio <= 0.8)   { $color = 'bg-red-400'; }
                else                      { $color = 'bg-red-600'; }
            ?>
                <div class="flex-1 <?php echo esc_attr($color); ?> rounded-sm" style="height: <?php echo max(10, (int) round($ratio * 100)); ?>%;">
                    <span class="sr-only"><?php echo (int) $count; ?> updates at <?php echo esc_html($b['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex gap-1 mt-1 text-[10px] text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider">
            <?php foreach ($buckets as $i => $b) : ?>
                <div class="flex-1 text-center"><?php echo ($i % 2 === 0) ? esc_html($b['label']) : '&nbsp;'; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/house-pulse.php
git commit -m "feat(theme): add House Pulse server-rendered bar chart"
```

---

## Task 11: Theme — Feed update card component

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/content/feed-update-card.php`

- [ ] **Step 1: Create the shared card**

```php
<?php
/**
 * Feed update card — the rich live-blog-style row used by Latest Feeds.
 *
 * Expected args (via get_template_part):
 *   - post     (WP_Post)
 *   - type     (?array{name:string, slug:string})
 *   - location (?array{name:string, slug:string})
 *   - variant  ('rich'|'compact')  default 'rich'
 */

if (!defined('ABSPATH')) {
    exit;
}

$args      = $args ?? [];
$post      = $args['post'] ?? null;
$type      = $args['type'] ?? null;
$location  = $args['location'] ?? null;
$variant   = $args['variant'] ?? 'rich';
if (!$post instanceof WP_Post) {
    return;
}

$permalink = get_permalink($post->ID);
$time_12h  = get_the_date('g:i A', $post);
$relative  = human_time_diff(get_post_time('U', true, $post), current_time('timestamp', true)) . ' ago';

$type_slug = $type['slug'] ?? '';
$type_class_map = [
    'drama'        => 'bg-red-100 text-red-900',
    'ceremony'     => 'bg-green-100 text-green-900',
    'strategy'     => 'bg-slate-100 text-slate-800',
    'competition'  => 'bg-amber-100 text-amber-900',
    'alliance'     => 'bg-indigo-100 text-indigo-900',
    'eviction'     => 'bg-gray-700 text-white',
    'punishment'   => 'bg-purple-100 text-purple-900',
    'reward'       => 'bg-emerald-100 text-emerald-900',
    'showmance'    => 'bg-pink-100 text-pink-900',
];
$type_class = $type_class_map[$type_slug] ?? 'bg-gray-100 text-gray-900';

$dot_color_map = [
    'drama'        => 'bg-red-500',
    'ceremony'     => 'bg-green-500',
    'strategy'     => 'bg-slate-500',
    'competition'  => 'bg-amber-500',
    'alliance'     => 'bg-indigo-500',
    'eviction'     => 'bg-gray-700',
    'punishment'   => 'bg-purple-500',
    'reward'       => 'bg-emerald-500',
    'showmance'    => 'bg-pink-500',
];
$dot_class = $dot_color_map[$type_slug] ?? 'bg-gray-400';

$author  = get_the_author_meta('display_name', $post->post_author);
$replies = (int) get_comments_number($post->ID);
$comments_open = comments_open($post->ID);

if ($variant === 'compact') :
?>
<li class="flex gap-3 items-center py-2 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
    <span class="text-xs font-osw text-gray-500 w-16 shrink-0" data-nosnippet><?php echo esc_html($time_12h); ?></span>
    <?php if ($type) : ?>
        <span class="hidden sm:inline-block text-[10px] font-osw uppercase tracking-wider px-1.5 py-0.5 rounded <?php echo esc_attr($type_class); ?>">
            <?php echo esc_html($type['name']); ?>
        </span>
    <?php endif; ?>
    <a href="<?php echo esc_url($permalink); ?>" class="text-sm font-semibold text-gray-900 dark:text-gray-100 hover:text-primary-500 dark:hover:text-secondary-500 truncate">
        <?php echo esc_html($post->post_title); ?>
    </a>
</li>
<?php return;
endif;
?>
<article class="flex gap-4 py-4">
    <div class="hidden sm:block w-20 shrink-0 text-right">
        <div class="font-osw text-sm text-gray-900 dark:text-gray-200"><?php echo esc_html($time_12h); ?></div>
        <div class="text-[11px] text-gray-500 dark:text-gray-400" data-nosnippet><?php echo esc_html($relative); ?></div>
    </div>

    <div class="relative flex-shrink-0">
        <span class="block w-3 h-3 rounded-full mt-1.5 <?php echo esc_attr($dot_class); ?>" aria-hidden="true"></span>
    </div>

    <div class="flex-1 min-w-0 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
        <div class="flex flex-wrap gap-2 mb-2">
            <?php if ($type) : ?>
                <span class="text-[10px] font-osw uppercase tracking-wider px-2 py-0.5 rounded <?php echo esc_attr($type_class); ?>">
                    <?php echo esc_html($type['name']); ?>
                </span>
            <?php endif; ?>
            <?php if ($location) : ?>
                <span class="text-[10px] font-osw uppercase tracking-wider px-2 py-0.5 rounded border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300">
                    <?php echo esc_html($location['name']); ?>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="font-display text-lg md:text-xl leading-snug mb-2 text-gray-900 dark:text-gray-100">
            <a href="<?php echo esc_url($permalink); ?>" class="hover:text-primary-500 dark:hover:text-secondary-500">
                <?php echo esc_html($post->post_title); ?>
            </a>
        </h3>

        <?php
        $excerpt = $post->post_excerpt !== ''
            ? $post->post_excerpt
            : wp_trim_words(strip_shortcodes($post->post_content), 30, '…');
        ?>
        <?php if ($excerpt !== '') : ?>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3"><?php echo esc_html($excerpt); ?></p>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3 items-center text-xs text-gray-500 dark:text-gray-400">
            <?php if ($author !== '') : ?>
                <span class="font-osw">@<?php echo esc_html(sanitize_title($author)); ?></span>
            <?php endif; ?>
            <span>
                <?php if ($replies > 0) : ?>
                    <?php printf(esc_html(_n('%d reply', '%d replies', $replies, 'bbj-v2-theme')), $replies); ?>
                <?php else : ?>
                    <?php esc_html_e('No replies yet', 'bbj-v2-theme'); ?>
                <?php endif; ?>
            </span>
            <?php if ($comments_open) : ?>
                <a href="<?php echo esc_url($permalink . '#respond'); ?>" class="text-primary-500 dark:text-secondary-500 hover:underline">
                    <?php esc_html_e('Join the thread →', 'bbj-v2-theme'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</article>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/content/feed-update-card.php
git commit -m "feat(theme): add feed-update-card shared component (rich + compact variants)"
```

---

## Task 12: Theme — Latest Feeds template (hybrid 5 rich + 10 compact)

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/home/latest-feeds.php`
- Delete: `wp-content/themes/bbj-v2-theme/template-parts/home/feed-updates.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * Latest from the Feeds — 5 rich cards + 10 compact rows.
 */

if (!defined('ABSPATH')) {
    exit;
}

$items = bbj_v2_homepage_latest_feeds(15);
if (empty($items)) {
    return;
}
$rich_items    = array_slice($items, 0, 5);
$compact_items = array_slice($items, 5, 10);
?>
<section id="latest-feeds" class="bbj-latest-feeds">
    <div class="flex items-baseline justify-between mb-2">
        <h2 class="section-header">
            <a href="<?php echo esc_url(home_url('/feed-updates/')); ?>" class="no-underline hover:text-secondary-500">
                <?php esc_html_e('Latest from the Feeds', 'bbj-v2-theme'); ?>
            </a>
        </h2>
    </div>

    <div class="relative border-l-2 border-gray-200 dark:border-gray-700 pl-1 sm:pl-0">
        <?php foreach ($rich_items as $item) :
            get_template_part('template-parts/content/feed-update-card', null, [
                'post'     => $item['post'],
                'type'     => $item['type'],
                'location' => $item['location'],
                'variant'  => 'rich',
            ]);
        endforeach; ?>
    </div>

    <?php if (!empty($compact_items)) : ?>
        <div class="mt-6">
            <h3 class="font-osw uppercase tracking-wider text-sm text-gray-700 dark:text-gray-300 mb-2">
                <?php esc_html_e('Quick hits', 'bbj-v2-theme'); ?>
            </h3>
            <ul class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 px-4">
                <?php foreach ($compact_items as $item) :
                    get_template_part('template-parts/content/feed-update-card', null, [
                        'post'     => $item['post'],
                        'type'     => $item['type'],
                        'location' => $item['location'],
                        'variant'  => 'compact',
                    ]);
                endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p class="mt-4 text-right">
        <a href="<?php echo esc_url(home_url('/feed-updates/')); ?>" class="font-osw uppercase tracking-wider text-sm text-primary-500 dark:text-secondary-500 hover:underline">
            <?php esc_html_e('See all feed updates →', 'bbj-v2-theme'); ?>
        </a>
    </p>
</section>
```

- [ ] **Step 2: Delete the old file**

```bash
git rm wp-content/themes/bbj-v2-theme/template-parts/home/feed-updates.php
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/latest-feeds.php
git commit -m "feat(theme): add Latest Feeds hybrid template (5 rich + 10 compact)"
```

---

## Task 13: Theme — More BB<N> Stories 3×3 grid

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/home/more-bb-stories.php`
- Delete: `wp-content/themes/bbj-v2-theme/template-parts/home/recent-posts.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * More BB<N> Stories — 3×3 grid of current-season posts, excluding the hero
 * and any post already surfaced in the "More BB<N> Spoilers" list.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hero           = bbj_v2_homepage_hero_post();
$spoiler_posts  = bbj_v2_homepage_more_spoilers($hero ? [$hero->ID] : []);
$exclude        = array_merge(
    $hero ? [$hero->ID] : [],
    array_map(static fn($p) => (int) $p->ID, $spoiler_posts)
);
$stories        = bbj_v2_homepage_bb_stories($exclude);
$season_num     = bbj_v2_current_season_number();
if (empty($stories)) {
    return;
}
?>
<section id="more-bb-stories" class="bbj-more-bb-stories">
    <h2 class="section-header mb-4">
        <a href="<?php echo esc_url(home_url('/category/' . bbj_v2_current_season_slug() . '/')); ?>" class="no-underline hover:text-secondary-500">
            <?php printf(esc_html__('More BB%d Stories', 'bbj-v2-theme'), $season_num); ?>
        </a>
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($stories as $p) : ?>
            <article class="v2-primary-container-inner">
                <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="block group">
                    <?php if (has_post_thumbnail($p->ID)) : ?>
                        <div class="aspect-[4/3] overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <?php echo get_the_post_thumbnail($p->ID, 'featured-thumbnail', [
                                'class'    => 'w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300',
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                            ]); ?>
                        </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <h3 class="font-display text-lg leading-snug text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors">
                            <?php echo esc_html($p->post_title); ?>
                        </h3>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-osw uppercase tracking-wider" data-nosnippet>
                            <time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>"><?php echo esc_html(get_the_date('M j', $p)); ?></time>
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
```

- [ ] **Step 2: Delete the old file**

```bash
git rm wp-content/themes/bbj-v2-theme/template-parts/home/recent-posts.php
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/more-bb-stories.php
git commit -m "feat(theme): add More BB-N Stories 3x3 grid template"
```

---

## Task 14: Theme — rework houseboard for sidebar

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/home/houseboard.php`

- [ ] **Step 1: Replace with sidebar-friendly single-column layout**

```php
<?php
/**
 * Houseboard — HoH / PoV / Noms / Week #.
 * Sidebar variant: single column stack. Used in the right-hand column.
 */

if (!defined('ABSPATH')) {
    exit;
}

$houseboard = function_exists('bbj_get_houseboard') ? bbj_get_houseboard() : [];
$hoh_name   = $houseboard['hoh']['name']  ?? 'TBD';
$pov_name   = $houseboard['pov']['name']  ?? 'TBD';
$noms       = $houseboard['noms']['names'] ?? [];
$week       = isset($houseboard['week']) ? (int) $houseboard['week'] : 0;
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('The House', 'bbj-v2-theme'); ?></h2>

    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        <li class="py-2 flex items-center justify-between text-sm">
            <span class="font-osw uppercase tracking-wider text-emerald-600 dark:text-emerald-400">HoH</span>
            <span class="font-medium"><?php echo esc_html($hoh_name); ?></span>
        </li>
        <li class="py-2 flex items-center justify-between text-sm">
            <span class="font-osw uppercase tracking-wider text-yellow-600 dark:text-yellow-400">PoV</span>
            <span class="font-medium"><?php echo esc_html($pov_name); ?></span>
        </li>
        <li class="py-2 flex items-start justify-between text-sm gap-3">
            <span class="font-osw uppercase tracking-wider text-red-600 dark:text-red-400 shrink-0">Nominees</span>
            <span class="font-medium text-right">
                <?php if (!empty($noms)) : ?>
                    <?php echo esc_html(implode(', ', (array) $noms)); ?>
                <?php else : ?>
                    <span class="text-gray-400">TBD</span>
                <?php endif; ?>
            </span>
        </li>
        <?php if ($week > 0) : ?>
            <li class="py-2 flex items-center justify-between text-sm">
                <span class="font-osw uppercase tracking-wider text-gray-600 dark:text-gray-400">Week</span>
                <span class="font-medium"><?php echo (int) $week; ?></span>
            </li>
        <?php endif; ?>
    </ul>
</section>
```

- [ ] **Step 2: Add sidebar-card CSS**

In `src/css/style.css`, inside `@layer components`:

```css
.bbj-sidebar-card {
    @apply bg-white dark:bg-gray-800 rounded-lg shadow p-4;
}
```

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/home/houseboard.php wp-content/themes/bbj-v2-theme/src/css/style.css
git commit -m "refactor(theme): rework houseboard for sidebar card layout"
```

---

## Task 15: Theme — Season Stats sidebar widget

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/sidebar/season-stats.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * Season Stats sidebar card — top 3 HoH, PoV, and nomination counts.
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = bbj_v2_homepage_season_stats();
$has_any = !empty($stats['hoh']) || !empty($stats['pov']) || !empty($stats['noms']);
if (!$has_any) {
    return;
}

$render_row = static function (array $rows, string $label): void {
    if (empty($rows)) return;
    echo '<div class="mb-3 last:mb-0">';
    echo '<div class="font-osw uppercase tracking-wider text-xs text-gray-600 dark:text-gray-400 mb-2">' . esc_html($label) . '</div>';
    echo '<ul class="space-y-1">';
    foreach ($rows as $r) {
        $name  = $r['official_nickname'] ?: trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $count = (int) $r['count'];
        echo '<li class="flex items-center justify-between text-sm"><span>' . esc_html($name) . '</span><span class="font-bold text-primary-500 dark:text-secondary-500">' . $count . '</span></li>';
    }
    echo '</ul>';
    echo '</div>';
};
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('Season Stats', 'bbj-v2-theme'); ?></h2>
    <?php
    $render_row($stats['hoh'],  __('Top HoH', 'bbj-v2-theme'));
    $render_row($stats['pov'],  __('Top PoV', 'bbj-v2-theme'));
    $render_row($stats['noms'], __('Most Nominated', 'bbj-v2-theme'));
    ?>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/sidebar/season-stats.php
git commit -m "feat(theme): add Season Stats sidebar card"
```

---

## Task 16: Theme — Recent Comments sidebar widget

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/sidebar/recent-comments.php`

- [ ] **Step 1: Create the template**

```php
<?php
/**
 * Recent Comments sidebar card — last 5 approved comments site-wide.
 */

if (!defined('ABSPATH')) {
    exit;
}

$comments = bbj_v2_homepage_recent_comments(5);
if (empty($comments)) {
    return;
}
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('Recent Comments', 'bbj-v2-theme'); ?></h2>
    <ul class="space-y-3">
        <?php foreach ($comments as $c) :
            $content = wp_trim_words(wp_strip_all_tags($c->comment_content), 15, '…');
            $link    = get_comment_link($c);
            $author  = $c->comment_author ?: __('Anonymous', 'bbj-v2-theme');
        ?>
            <li class="text-sm">
                <a href="<?php echo esc_url($link); ?>" class="block group">
                    <div class="font-osw uppercase tracking-wide text-xs text-gray-600 dark:text-gray-400">
                        <?php echo esc_html($author); ?>
                        <span class="mx-1">·</span>
                        <span data-nosnippet><?php echo esc_html(human_time_diff(strtotime($c->comment_date_gmt), time())); ?> ago</span>
                    </div>
                    <div class="text-gray-900 dark:text-gray-100 group-hover:text-primary-500 dark:group-hover:text-secondary-500 transition-colors">
                        <?php echo esc_html($content); ?>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/sidebar/recent-comments.php
git commit -m "feat(theme): add Recent Comments sidebar card"
```

---

## Task 17: Theme — Sticky Ad + Paramount+ + Socials

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/sidebar/sticky-ad.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/sidebar/paramount-plus.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/sidebar/socials.php`

- [ ] **Step 1: Sticky ad template**

`template-parts/sidebar/sticky-ad.php`:

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="lg:sticky lg:top-24">
    <?php get_template_part('template-parts/components/ad-placeholder', null, [
        'slot'        => 'homepage_sidebar_sticky',
        'size'        => '300x600',
        'mobile_size' => '300x250',
        'note'        => __('Sticky · desktop half-page / mobile MPU', 'bbj-v2-theme'),
    ]); ?>
</div>
```

- [ ] **Step 2: Paramount+ static block**

`template-parts/sidebar/paramount-plus.php`:

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

$url = apply_filters('bbj_v2_paramount_plus_url', '#');
?>
<section class="bbj-sidebar-card bg-primary-500 text-white border-0 shadow">
    <h2 class="font-display text-2xl mb-2"><?php esc_html_e('Watch Live on Paramount+', 'bbj-v2-theme'); ?></h2>
    <p class="text-sm opacity-90 mb-3">
        <?php esc_html_e('Stream the full BB live feeds and CBS episodes. 7-day free trial.', 'bbj-v2-theme'); ?>
    </p>
    <a href="<?php echo esc_url($url); ?>"
       class="btn-secondary w-full text-center block"
       target="_blank" rel="noopener sponsored">
        <?php esc_html_e('Start free trial', 'bbj-v2-theme'); ?>
    </a>
</section>
```

- [ ] **Step 3: Socials static block**

`template-parts/sidebar/socials.php`:

```php
<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="bbj-sidebar-card">
    <h2 class="section-header mb-3"><?php esc_html_e('Follow BBJ', 'bbj-v2-theme'); ?></h2>
    <ul class="space-y-2">
        <li>
            <a href="https://facebook.com/bigbrotherjunkies" target="_blank" rel="noopener"
               class="flex items-center justify-between text-sm hover:text-primary-500">
                <span class="font-osw uppercase tracking-wider">Facebook</span>
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12.07C22 6.5 17.52 2 12 2S2 6.5 2 12.07C2 17.1 5.66 21.25 10.44 22V14.9H7.9v-2.83h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.78l-.44 2.83h-2.34V22C18.34 21.25 22 17.1 22 12.07z"/></svg>
            </a>
        </li>
        <li>
            <a href="https://instagram.com/bigbrotherjunkies" target="_blank" rel="noopener"
               class="flex items-center justify-between text-sm hover:text-primary-500">
                <span class="font-osw uppercase tracking-wider">Instagram</span>
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23a3.7 3.7 0 0 1-.9 1.38 3.7 3.7 0 0 1-1.38.9c-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zm0 5.84A4 4 0 1 0 12 16a4 4 0 0 0 0-8z"/></svg>
            </a>
        </li>
        <li>
            <a href="https://twitter.com/BigBrotherBBJ" target="_blank" rel="noopener"
               class="flex items-center justify-between text-sm hover:text-primary-500">
                <span class="font-osw uppercase tracking-wider">Twitter / X</span>
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.9 2H22l-7.5 8.6L23 22h-6.8l-5.3-6.9L4.7 22H1.6l8-9.2L1 2h6.9l4.8 6.3L18.9 2z"/></svg>
            </a>
        </li>
        <li>
            <a href="https://bsky.app/profile/realstevebeans.bsky.social" target="_blank" rel="noopener"
               class="flex items-center justify-between text-sm hover:text-primary-500">
                <span class="font-osw uppercase tracking-wider">Bluesky</span>
                <svg class="w-5 h-5" viewBox="0 0 568 501" fill="currentColor" aria-hidden="true"><path d="M123 34c65 49 136 148 161 201 26-53 97-152 162-201 47-35 123-62 123 24 0 17-10 146-16 166-20 72-94 91-160 80 115 20 144 84 81 149-120 123-172-31-186-70-2-7-3-11-3-8 0-3-1 1-4 8-13 39-66 193-185 70-63-65-34-129 81-149-66 11-140-7-160-80-6-21-16-149-16-166 0-86 76-59 122-24z"/></svg>
            </a>
        </li>
    </ul>
</section>
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/sidebar/sticky-ad.php wp-content/themes/bbj-v2-theme/template-parts/sidebar/paramount-plus.php wp-content/themes/bbj-v2-theme/template-parts/sidebar/socials.php
git commit -m "feat(theme): add sticky-ad, Paramount+, and socials sidebar blocks"
```

---

## Task 18: Theme — rewrite sidebar.php

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/sidebar.php`

- [ ] **Step 1: Replace with new widget list**

```php
<?php
/**
 * Homepage sidebar — used by front-page.php.
 * Widget order: Houseboard → Season Stats → Recent Comments → Sticky Ad → Paramount+ → Socials → Newsletter.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<aside class="space-y-6">
    <?php get_template_part('template-parts/home/houseboard'); ?>
    <?php get_template_part('template-parts/sidebar/season-stats'); ?>
    <?php get_template_part('template-parts/sidebar/recent-comments'); ?>
    <?php get_template_part('template-parts/sidebar/sticky-ad'); ?>
    <?php get_template_part('template-parts/sidebar/paramount-plus'); ?>
    <?php get_template_part('template-parts/sidebar/socials'); ?>
    <?php get_template_part('template-parts/sidebar/newsletter'); ?>
</aside>
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/sidebar.php
git commit -m "refactor(theme): rewrite sidebar.php with new widget order"
```

---

## Task 19: Theme — rewrite `front-page.php` orchestrator

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/front-page.php`

- [ ] **Step 1: Replace the file**

```php
<?php
/**
 * Homepage template — 3-column editorial grid.
 * Mobile: hero → houseboard (pulled up) → more spoilers → square ad → pulse
 *         → latest feeds → leaderboard ad → bb stories → rest of sidebar.
 */

get_header();

$hero = bbj_v2_homepage_hero_post();
?>

<?php get_template_part('template-parts/home/status-strip'); ?>

<main id="site-content" class="mx-auto max-w-screen-xl px-4 py-6" role="main">

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <div class="lg:col-span-8 space-y-8">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <?php get_template_part('template-parts/home/hero-post'); ?>
                </div>
                <div>
                    <?php get_template_part('template-parts/home/more-bb-spoilers'); ?>
                </div>
            </div>

            <div class="lg:hidden">
                <?php get_template_part('template-parts/home/houseboard'); ?>
            </div>

            <?php get_template_part('template-parts/home/house-pulse'); ?>

            <?php get_template_part('template-parts/home/latest-feeds'); ?>

            <?php get_template_part('template-parts/components/ad-placeholder', null, [
                'slot'        => 'homepage_leaderboard',
                'size'        => '728x90',
                'mobile_size' => '320x50',
                'note'        => __('Homepage · between feeds and stories', 'bbj-v2-theme'),
            ]); ?>

            <?php get_template_part('template-parts/home/more-bb-stories'); ?>

        </div>

        <aside class="lg:col-span-4 space-y-6" aria-label="<?php esc_attr_e('Sidebar', 'bbj-v2-theme'); ?>">
            <div class="hidden lg:block">
                <?php get_template_part('template-parts/home/houseboard'); ?>
            </div>
            <?php get_template_part('template-parts/sidebar/season-stats'); ?>
            <?php get_template_part('template-parts/sidebar/recent-comments'); ?>
            <?php get_template_part('template-parts/sidebar/sticky-ad'); ?>
            <?php get_template_part('template-parts/sidebar/paramount-plus'); ?>
            <?php get_template_part('template-parts/sidebar/socials'); ?>
            <?php get_template_part('template-parts/sidebar/newsletter'); ?>
        </aside>

    </div>

</main>

<?php get_footer(); ?>
```

- [ ] **Step 2: Rebuild + smoke-test in browser**

```bash
cd wp-content/themes/bbj-v2-theme && npm run build
```

Load `http://bbj.localhost/` and verify:
- Status strip at top.
- Desktop: 3-col grid (hero on left, more-spoilers center, sidebar right).
- Below 3-col: pulse → feeds → leaderboard ad → stories.
- Mobile (< 768px): everything stacks; houseboard appears between hero and more-spoilers.
- Only ONE `<h1>` in view-source: `grep -c '<h1' /dev/stdin` after viewing source.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/front-page.php
git commit -m "refactor(theme): rewrite front-page.php as 3-col editorial grid"
```

---

## Task 20: Plugin — admin override field on season CPT

**Files:**
- Locate + modify the season CPT Meta Box registration in the plugin (search for the file that registers `bigbrother-seasons` fields).

- [ ] **Step 1: Find the season meta-box file**

```bash
grep -rln "bigbrother-seasons" wp-content/plugins/bigbrotherjunkies-data/src/ | head
```

Look for a MetaBox / Fields file for seasons.

- [ ] **Step 2: Add the override field**

Add to the existing season fields array:

```php
[
    'name' => 'Next CBS Show Override (datetime)',
    'id'   => 'bbj_next_show_override',
    'type' => 'datetime',
    'desc' => 'Optional — override the default Sun/Wed/Thu 8pm ET rule for this week (e.g. finale). Leave blank for default.',
    'js_options' => [
        'dateFormat' => 'yy-mm-dd',
        'showTimepicker' => true,
    ],
],
```

(If the plugin uses Meta Box library like `rwmb_meta`, `datetime` is the right type. If it uses raw `add_meta_box`, fall back to a plain text input with a datetime format note.)

- [ ] **Step 3: Test**

Load the season CPT edit page for the current season. Verify the new field appears, save a value, and verify it's returned by:

```bash
wp post meta get <season_id> bbj_next_show_override
```

- [ ] **Step 4: Commit**

```bash
git add <path to the modified file>
git commit -m "feat(plugin): add bbj_next_show_override datetime field on season CPT"
```

---

## Task 21: Theme — CSS tidy-up + final rebuild

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/src/css/style.css`

- [ ] **Step 1: Verify any lingering classes are in the compiled bundle**

```bash
cd wp-content/themes/bbj-v2-theme && npm run build
grep -c "bbj-status-strip\|bbj-sidebar-card\|bbj-house-pulse\|bbj-hero-post\|bbj-latest-feeds\|bbj-more-bb-stories\|bbj-more-spoilers" build/style.css
```

Expected: each class appears at least once in the minified bundle. If any show 0, the Tailwind content scan didn't pick up the class — check the class is referenced in a `.php` template that lives under `template-parts/**/*.php` or the theme root.

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/build/style.css wp-content/themes/bbj-v2-theme/src/css/style.css
git commit -m "chore(theme): rebuild Tailwind with homepage classes"
```

---

## Task 22: End-to-end smoke test + staging deploy

- [ ] **Step 1: Final local smoke**

Load `http://bbj.localhost/` on desktop AND mobile viewport (DevTools responsive) and verify each checkpoint below:

```
[ ] Status strip renders with active-season variant
[ ] Hero post: image loads with fetchpriority=high, H1 is the post title
[ ] Only one <h1> in the page (view-source → search '<h1')
[ ] "More BB26 Spoilers" shows up to 3 posts + 300x250 ad placeholder
[ ] Houseboard visible (sidebar on desktop, pulled-up section on mobile)
[ ] House Pulse shows 8 bars (or "Quiet house" if no recent updates)
[ ] Latest Feeds shows 5 rich cards + 10 compact rows + "See all feed updates →"
[ ] Leaderboard ad is 320x50 on mobile, 728x90 on desktop
[ ] More BB26 Stories 3x3 grid renders (9 cards)
[ ] Sidebar order: Houseboard → Season Stats → Recent Comments → Sticky Ad → Paramount+ → Socials → Newsletter
[ ] Sticky Ad stays pinned while scrolling on desktop; inline on mobile
[ ] No PHP errors in wp-content/debug.log
[ ] PageSpeed mobile still ≥ 80
```

- [ ] **Step 2: Manual toggle off-season + verify**

```bash
wp option update bbj_v2_season_active 0
```

Reload → confirm status strip switches to off-season variant, House Pulse hides, section headings change to "BB<N> Recap".

```bash
wp option delete bbj_v2_season_active
```

- [ ] **Step 3: Merge to staging + deploy**

```bash
git checkout staging
git pull --ff-only origin staging
git merge feature/bbj-v2-theme --no-ff -m "Merge feature/bbj-v2-theme: homepage redesign"
git push origin staging
bash .claude/scripts/deploy-plugin.sh --staging   # plugin taxonomies need to land first
bash .claude/scripts/deploy-theme.sh --staging
```

- [ ] **Step 4: Staging smoke**

Purge Breeze/Varnish on Cloudways, then load https://stg-wp.bigbrotherjunkies.com/ and re-run the smoke checklist from Step 1.

- [ ] **Step 5: Final cleanup**

If everything's green:

```bash
git checkout feature/bbj-v2-theme
# cherry-pick any staging-only commits back if needed
git push origin feature/bbj-v2-theme
```

Otherwise iterate, push, redeploy, re-smoke.

---

## Self-review — spec coverage

| Spec section | Covered by |
|---|---|
| 3-col desktop / 2-col tablet / 1-col mobile | Task 19 (front-page.php grid) |
| Status strip + off-season | Tasks 5, 7 |
| Hero post (4:3, H1, fetchpriority) | Task 8 |
| More BB<N> Spoilers + ad | Task 9 |
| House Pulse | Tasks 5, 10 |
| Latest Feeds hybrid | Tasks 11, 12 |
| More BB<N> Stories 3×3 | Task 13 |
| Houseboard (sidebar) | Task 14 |
| Season Stats sidebar | Tasks 5, 15 |
| Recent Comments sidebar | Tasks 5, 16 |
| Sticky Ad / Paramount+ / Socials | Task 17 |
| Newsletter (existing) | Task 18 references |
| `update_type` taxonomy | Task 1 |
| `update_location` taxonomy | Task 2 |
| Data layer + cache + busters | Tasks 3, 4, 5, 6 |
| Season slug helpers | Task 3 |
| Active-season helper | Task 3 |
| Next CBS show rule + override | Tasks 5, 20 |
| Mobile stacking with Houseboard pulled up | Task 19 |
| SEO / H1 enforcement | Task 8 (single H1), Task 22 smoke |
| Schema.org notes | Acknowledged in spec, existing schema untouched |
| PageSpeed / LCP / CLS | Tasks 8 (eager hero), 10 (CSS-only), 17 (300×600 / 300×250 reserved) |
| `data-nosnippet` on times | Tasks 7, 8, 9, 11, 13, 16 |
| Accessibility | Tasks 7 (aria-label), 10 (role="img"), 11 (article + sr-only) |
| Tests | Manual smoke in Task 22 (no PHPUnit in repo) |
