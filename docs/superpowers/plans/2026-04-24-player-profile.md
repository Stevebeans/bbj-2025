# Player Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite `single-bigbrother-players.php` to match the Claude Design mockup (`.claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html`), wired to real data from the player + season + junction tables. Layout, typography, and color all port from the mockup. Deferred sections (game timeline, fan voting, week-by-week, compare, articles) are handled per the spec.

**Architecture:** Data aggregation lives in a new theme include (`inc/player-profile-data.php`) with pure functions that return well-shaped arrays. The template file consumes those arrays and renders markup. Styles live in a new scoped stylesheet (`css/single-bigbrother-players.css`) enqueued only on this template. Google Fonts for the new editorial palette (Oswald + Source Serif 4 + Inter Tight + IBM Plex Mono) load conditionally on this template only — other pages unaffected.

**Tech Stack:** PHP (WordPress standards), custom MySQL tables (wp_bbj_players, wp_bbj_v2_player_season, wp_bbj_seasons, wp_bbj_geo), vanilla CSS with `:root` custom properties, no JS on this template.

**Design reference:** `.claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html`
**Spec:** `docs/superpowers/specs/2026-04-24-player-profile-design.md`

**Testing approach:** This theme has no PHPUnit suite. Verification is browser-based — load the profile URL and inspect rendered output + server logs. At the end of each task, the acceptance check is explicit (what to load, what to see, what to confirm absent from error logs). Final task has a multi-scenario smoke matrix the user runs before `/push-staging`.

**Branch:** User works directly on `staging` — no worktrees (see `feedback_no_worktrees` memory). Do NOT create a branch or worktree.

---

## Task 1: Scaffold files and asset plumbing

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css` (empty placeholder)
- Create: `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php` (empty placeholder)
- Modify: `wp-content/themes/bbj-v2-theme/functions.php` (add require)
- Modify: `wp-content/themes/bbj-v2-theme/inc/enqueue.php` (add conditional CSS + font preload)

- [ ] **Step 1.1: Create the `css/` directory and an empty stylesheet**

```bash
mkdir -p wp-content/themes/bbj-v2-theme/css
```

Then `Write` the stylesheet with a placeholder comment so the file is valid CSS:

```css
/* Single Player Profile — editorial redesign.
   Scoped to is_singular('bigbrother-players'). Design reference:
   .claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html
   Ported in Task 5. */
```

- [ ] **Step 1.2: Create the data helper stub**

Write `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php`:

```php
<?php
/**
 * Player profile data helpers.
 *
 * Pure functions that query wp_bbj_players, wp_bbj_geo, wp_bbj_v2_player_season,
 * and wp_bbj_seasons, returning normalized arrays for the
 * single-bigbrother-players.php template.
 *
 * All functions are prefixed bbj_v2_player_profile_* and live in the global
 * namespace (following the rest of this theme's convention).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Implementations land in Tasks 2–4.
```

- [ ] **Step 1.3: Wire the data helper into `functions.php`**

In `wp-content/themes/bbj-v2-theme/functions.php`, find the block of `require_once BBJ_V2_THEME_PATH . '/inc/...'` lines (around line 19–25) and add this line after the `homepage-data.php` require:

```php
require_once BBJ_V2_THEME_PATH . '/inc/player-profile-data.php';
```

- [ ] **Step 1.4: Enqueue the scoped stylesheet conditionally**

In `wp-content/themes/bbj-v2-theme/inc/enqueue.php`, inside the `bbj_v2_enqueue_assets()` function, add this block just **before** the `wp_dequeue_style('wp-block-library')` cleanup (so it sits with the other conditional enqueues like the admin-feed-updates one):

```php
    // Single player profile — scoped stylesheet + editorial fonts.
    if (is_singular('bigbrother-players')) {
        wp_enqueue_style(
            'bbj-v2-single-player',
            BBJ_V2_THEME_URL . '/css/single-bigbrother-players.css',
            [],
            bbj_v2_asset_ver('/css/single-bigbrother-players.css')
        );
    }
```

- [ ] **Step 1.5: Conditional Google Fonts in `wp_head`**

In `wp-content/themes/bbj-v2-theme/inc/enqueue.php`, modify `bbj_v2_preload_fonts()` so the editorial fonts load only on the player profile. Replace the function body with:

```php
function bbj_v2_preload_fonts(): void
{
    $base_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Oswald:wght@400;500;600;700&family=Yanone+Kaffeesatz:wght@400;500;600;700&family=Caveat:wght@400;500;600;700';

    // Editorial fonts for the player profile (retiring Yanone on that page).
    if (is_singular('bigbrother-players')) {
        $base_url .= '&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=Inter+Tight:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500';
    }

    $fonts_url = $base_url . '&display=swap';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="<?php echo esc_url($fonts_url); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($fonts_url); ?>" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="<?php echo esc_url($fonts_url); ?>">
    </noscript>
    <?php
}
```

- [ ] **Step 1.6: Verify the scaffold loads without error**

Load `http://bbj.localhost/bigbrother-players/<any-player-slug>/` in a browser.

Expected:
- Page renders (still the old stub template — we haven't touched `single-bigbrother-players.php` yet)
- View source, confirm:
  - `<link rel="stylesheet" ... /css/single-bigbrother-players.css?ver=...>` is present
  - The Google Fonts URL contains `Source+Serif+4`, `Inter+Tight`, and `IBM+Plex+Mono`
- Check browser devtools Network tab — `single-bigbrother-players.css` returns 200 (even though empty)
- Load any non-player page (e.g. homepage). View source — confirm the Google Fonts URL does NOT contain `Source+Serif+4` (conditional guard working)

- [ ] **Step 1.7: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css wp-content/themes/bbj-v2-theme/inc/player-profile-data.php wp-content/themes/bbj-v2-theme/functions.php wp-content/themes/bbj-v2-theme/inc/enqueue.php
git commit -m "feat(player-profile): scaffold css + data helper + conditional enqueues"
```

---

## Task 2: Data helper — player + hometown

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php`

- [ ] **Step 2.1: Implement `bbj_v2_player_profile_player_data()`**

Append to `inc/player-profile-data.php` (after the closing comment):

```php
/**
 * Fetch the core player record + geo data for a player post_id.
 *
 * Returns a normalized array or null if the player doesn't exist.
 */
function bbj_v2_player_profile_player_data(int $post_id): ?array
{
    global $wpdb;

    $player = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bbj_players WHERE post_id = %d LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    if (!$player) {
        return null;
    }

    // Geo lives in a side table keyed by post_id (may be missing for older players).
    $geo = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT locality, administrative_area_level_1 FROM {$wpdb->prefix}bbj_geo WHERE post_id = %d LIMIT 1",
            $post_id
        ),
        ARRAY_A
    );

    $socials = [];
    foreach (['facebook', 'instagram', 'twitter', 'tiktok'] as $platform) {
        if (!empty($player[$platform])) {
            $socials[$platform] = $player[$platform];
        }
    }

    $hometown_parts = array_filter([
        $geo['locality'] ?? '',
        $geo['administrative_area_level_1'] ?? '',
    ]);
    $hometown = $hometown_parts ? implode(', ', $hometown_parts) : '';

    return [
        'post_id'          => $post_id,
        'first_name'       => $player['first_name'] ?? '',
        'last_name'        => $player['last_name'] ?? '',
        'full_name'        => trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')),
        'nickname'         => $player['official_nickname'] ?? '',
        'gender'           => $player['player_gender'] ?? '',
        'profile_picture'  => (int) ($player['profile_picture'] ?? 0),
        'player_banner'    => (int) ($player['player_banner'] ?? 0),
        'date_of_birth'    => $player['date_of_birth'] ?? null,
        'occupation'       => $player['occupation'] ?? '',
        'hometown'         => $hometown,
        'city'             => $geo['locality'] ?? '',
        'state'            => $geo['administrative_area_level_1'] ?? '',
        'socials'          => $socials,
    ];
}
```

- [ ] **Step 2.2: Smoke-test the helper**

Create a temporary dump at the very top of `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`:

```php
<?php
if (current_user_can('manage_options') && isset($_GET['debug_player'])) {
    echo '<pre>';
    var_dump(bbj_v2_player_profile_player_data(get_the_ID()));
    echo '</pre>';
    exit;
}
```

Load `http://bbj.localhost/bigbrother-players/<any-player-slug>/?debug_player=1` while logged in as admin.

Expected: a var_dump of the normalized array with keys `full_name`, `hometown`, `socials`, etc. Empty strings are OK for missing geo; null for missing DOB is OK.

Then **remove the debug block** before moving on.

- [ ] **Step 2.3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/player-profile-data.php
git commit -m "feat(player-profile): add bbj_v2_player_profile_player_data helper"
```

---

## Task 3: Data helper — seasons, career totals, castmates

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php`

- [ ] **Step 3.1: Implement `bbj_v2_player_profile_seasons()`**

Append to `inc/player-profile-data.php`:

```php
/**
 * Fetch all junction rows for a player, joined with season metadata.
 * Ordered by season start_date DESC (most recent first).
 */
function bbj_v2_player_profile_seasons(int $post_id): array
{
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_season,
                j.bbj_evicted_date,
                j.bbj_finish_place,
                j.bbj_total_hoh,
                j.bbj_total_pov,
                j.bbj_total_nom,
                j.bbj_total_havenot,
                j.bbj_total_saved,
                j.bbj_votes_received,
                j.bbj_veto_played,
                j.current_evicted,
                j.current_jury,
                s.full_name       AS season_name,
                s.abbreviation    AS season_abbr,
                s.start_date      AS season_start,
                s.end_date        AS season_end,
                s.season_winner,
                s.runner_up,
                s.afp,
                p.post_name       AS season_slug
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = j.bbj_season
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_season
             WHERE j.bbj_player = %d
             ORDER BY s.start_date DESC",
            $post_id
        ),
        ARRAY_A
    );

    return $rows ?: [];
}
```

- [ ] **Step 3.2: Implement `bbj_v2_player_profile_career_totals()`**

Append:

```php
/**
 * Aggregate career totals across all seasons.
 *
 * $seasons is the array returned by bbj_v2_player_profile_seasons()
 * (pass it in rather than re-querying).
 */
function bbj_v2_player_profile_career_totals(array $seasons): array
{
    $totals = [
        'season_count' => count($seasons),
        'hoh'          => 0,
        'pov'          => 0,
        'nom'          => 0,
        'votes'        => 0,
        'days'         => 0,
        'veto_played'  => 0,
        'havenot'      => 0,
        'saved'        => 0,
    ];

    foreach ($seasons as $row) {
        $totals['hoh']         += (int) ($row['bbj_total_hoh']      ?? 0);
        $totals['pov']         += (int) ($row['bbj_total_pov']      ?? 0);
        $totals['nom']         += (int) ($row['bbj_total_nom']      ?? 0);
        $totals['votes']       += (int) ($row['bbj_votes_received'] ?? 0);
        $totals['veto_played'] += (int) ($row['bbj_veto_played']    ?? 0);
        $totals['havenot']     += (int) ($row['bbj_total_havenot']  ?? 0);
        $totals['saved']       += (int) ($row['bbj_total_saved']    ?? 0);

        // Days = evicted_date (or season end if made finale) - season start.
        $start   = $row['season_start']     ?? null;
        $evicted = $row['bbj_evicted_date'] ?? null;
        $end     = $row['season_end']       ?? null;

        if ($start) {
            $end_for_days = $evicted ?: ($end ?: null);
            if ($end_for_days) {
                $d1 = new DateTime($start);
                $d2 = new DateTime($end_for_days);
                $totals['days'] += max(0, (int) $d1->diff($d2)->days);
            }
        }
    }

    return $totals;
}
```

- [ ] **Step 3.3: Implement `bbj_v2_player_profile_castmates()`**

Append:

```php
/**
 * Fetch other players who appeared in the same season, with placement data
 * so the template can render status tags (Winner / Runner-up / AFP / Jury / Out).
 *
 * Excludes the given $player_post_id from the result.
 */
function bbj_v2_player_profile_castmates(int $player_post_id, int $season_post_id): array
{
    global $wpdb;

    if ($season_post_id <= 0) {
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT
                j.bbj_player        AS player_post_id,
                j.bbj_finish_place,
                j.bbj_evicted_date,
                j.current_jury,
                j.current_evicted,
                bp.first_name,
                bp.last_name,
                bp.official_nickname,
                bp.profile_picture,
                p.post_name         AS player_slug,
                s.season_winner,
                s.runner_up,
                s.afp
             FROM {$wpdb->prefix}bbj_v2_player_season j
             INNER JOIN {$wpdb->prefix}bbj_players bp ON bp.post_id = j.bbj_player
             INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
             INNER JOIN {$wpdb->prefix}bbj_seasons s ON s.post_id = j.bbj_season
             WHERE j.bbj_season = %d
               AND j.bbj_player != %d
             ORDER BY (j.bbj_finish_place IS NULL), j.bbj_finish_place ASC",
            $season_post_id,
            $player_post_id
        ),
        ARRAY_A
    );

    return $rows ?: [];
}
```

- [ ] **Step 3.4: Smoke-test all three**

Temporarily re-add the debug block at the top of `single-bigbrother-players.php`:

```php
<?php
if (current_user_can('manage_options') && isset($_GET['debug_player'])) {
    $seasons   = bbj_v2_player_profile_seasons(get_the_ID());
    $totals    = bbj_v2_player_profile_career_totals($seasons);
    $castmates = $seasons ? bbj_v2_player_profile_castmates(get_the_ID(), (int) $seasons[0]['bbj_season']) : [];

    echo '<pre>SEASONS:' . PHP_EOL; var_dump($seasons);
    echo '---TOTALS---' . PHP_EOL; var_dump($totals);
    echo '---CASTMATES---' . PHP_EOL; var_dump($castmates);
    echo '</pre>';
    exit;
}
```

Load `?debug_player=1` on a player that has season data. Confirm arrays look right. **Remove the debug block before committing.**

- [ ] **Step 3.5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/player-profile-data.php
git commit -m "feat(player-profile): seasons + career totals + castmates helpers"
```

---

## Task 4: Data helper — derived values

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php`

- [ ] **Step 4.1: Implement `bbj_v2_player_profile_derive()`**

Append to `inc/player-profile-data.php`:

```php
/**
 * Compute derived values for display: age now, age in house, days in house,
 * placement label, eviction day + week, status kicker, tag chips.
 *
 * $player   — return value from bbj_v2_player_profile_player_data()
 * $seasons  — return value from bbj_v2_player_profile_seasons() (may be empty)
 */
function bbj_v2_player_profile_derive(array $player, array $seasons): array
{
    $latest = $seasons[0] ?? null;

    // Age now (from DOB).
    $age_now = null;
    if (!empty($player['date_of_birth'])) {
        try {
            $dob = new DateTime($player['date_of_birth']);
            $age_now = $dob->diff(new DateTime('now'))->y;
        } catch (Exception $e) {
            // Malformed date — leave null.
        }
    }

    // Age in house (at evicted_date or season start if no evict).
    $age_in_house = null;
    if ($latest && !empty($player['date_of_birth'])) {
        $ref = $latest['bbj_evicted_date'] ?: $latest['season_start'];
        if ($ref) {
            try {
                $dob = new DateTime($player['date_of_birth']);
                $age_in_house = $dob->diff(new DateTime($ref))->y;
            } catch (Exception $e) {}
        }
    }

    // Days in house (latest season).
    $days_in_house = null;
    $eviction_week = null;
    $eviction_day  = null;
    if ($latest && !empty($latest['season_start'])) {
        $end = $latest['bbj_evicted_date'] ?: ($latest['season_end'] ?: date('Y-m-d'));
        try {
            $start = new DateTime($latest['season_start']);
            $evict = new DateTime($end);
            $diff  = $start->diff($evict);
            $days_in_house = max(0, (int) $diff->days);
            if (!empty($latest['bbj_evicted_date'])) {
                $eviction_day  = $days_in_house;
                $eviction_week = (int) floor($days_in_house / 7) + 1;
            }
        } catch (Exception $e) {}
    }

    // Status kicker: Winner / Runner-up / AFP / Jury / Pre-jury / Active.
    $status_kicker = 'Houseguest';
    if ($latest) {
        $abbr = $latest['season_abbr'] ?: 'BB';
        $status_kicker = 'Houseguest · ' . $abbr;
        if ((int) $latest['season_winner'] === (int) $player['post_id']) {
            $status_kicker .= ' · Winner';
        } elseif ((int) $latest['runner_up'] === (int) $player['post_id']) {
            $status_kicker .= ' · Runner-up';
        } elseif ((int) $latest['afp'] === (int) $player['post_id']) {
            $status_kicker .= " · America's Favorite";
        } elseif (!empty($latest['current_jury'])) {
            $status_kicker .= ' · Jury';
        } elseif (!empty($latest['bbj_evicted_date'])) {
            $status_kicker .= ' · Pre-jury';
        } else {
            $status_kicker .= ' · Active';
        }
    }

    // Placement label for bio strip (e.g. "5th · AFP winner" or "Currently playing").
    $placement_label = '';
    if ($latest) {
        $place = (int) ($latest['bbj_finish_place'] ?? 0);
        if ($place > 0) {
            $placement_label = bbj_v2_player_profile_ordinal($place);
            if ((int) $latest['afp'] === (int) $player['post_id']) {
                $placement_label .= ' · AFP winner';
            } elseif ($place === 1) {
                $placement_label .= ' · Winner';
            } elseif ($place === 2) {
                $placement_label .= ' · Runner-up';
            }
        } else {
            $placement_label = 'Currently playing';
        }
    }

    // Tag chips: only where count > 0.
    $totals = bbj_v2_player_profile_career_totals($seasons);
    $chips = [];
    if ($latest && (int) $latest['afp'] === (int) $player['post_id']) {
        $chips[] = ['text' => "♥ America's Favorite", 'class' => 'afp'];
    }
    if ($latest && !empty($latest['current_jury'])) {
        $chips[] = ['text' => 'Jury member', 'class' => ''];
    }
    if ($totals['hoh'] > 0)   { $chips[] = ['text' => "{$totals['hoh']}× HoH", 'class' => '']; }
    if ($totals['pov'] > 0)   { $chips[] = ['text' => "{$totals['pov']}× PoV", 'class' => '']; }
    if ($totals['nom'] > 0)   { $chips[] = ['text' => "{$totals['nom']}× Nominated", 'class' => '']; }

    // Has-AFP-ever: used for the hero portrait badge.
    $is_afp_anywhere = false;
    foreach ($seasons as $s) {
        if ((int) $s['afp'] === (int) $player['post_id']) {
            $is_afp_anywhere = true;
            break;
        }
    }

    return [
        'age_now'          => $age_now,
        'age_in_house'     => $age_in_house,
        'days_in_house'    => $days_in_house,
        'eviction_day'     => $eviction_day,
        'eviction_week'    => $eviction_week,
        'status_kicker'    => $status_kicker,
        'placement_label'  => $placement_label,
        'chips'            => $chips,
        'is_afp_anywhere'  => $is_afp_anywhere,
        'latest_season'    => $latest,
    ];
}

/**
 * Ordinal formatter: 1 → "1st", 2 → "2nd", 3 → "3rd", etc.
 */
function bbj_v2_player_profile_ordinal(int $n): string
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

- [ ] **Step 4.2: Smoke-test derive()**

Reinstate the debug block at top of `single-bigbrother-players.php`:

```php
<?php
if (current_user_can('manage_options') && isset($_GET['debug_player'])) {
    $player   = bbj_v2_player_profile_player_data(get_the_ID());
    $seasons  = bbj_v2_player_profile_seasons(get_the_ID());
    $derived  = bbj_v2_player_profile_derive($player, $seasons);

    echo '<pre>DERIVED:' . PHP_EOL; var_dump($derived); echo '</pre>';
    exit;
}
```

Load `?debug_player=1`. Confirm `age_now`, `days_in_house`, `status_kicker`, `placement_label`, `chips` all look sensible for a real player. **Remove debug block.**

- [ ] **Step 4.3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/player-profile-data.php
git commit -m "feat(player-profile): derived values (age, days, placement, chips)"
```

---

## Task 5: Port the design's CSS into the scoped stylesheet

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css`

- [ ] **Step 5.1: Read the design's `<style>` block**

Open `.claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html` lines 10–305. This is the source of truth for the stylesheet.

- [ ] **Step 5.2: Port everything inside a scoping wrapper**

The design's `<style>` block contains:
- `:root` variables (keep as-is, they cascade globally from this page's stylesheet — harmless since the file only loads on this template)
- Global resets on `*`, `html`, `body` — **dangerous if they cascade from an outside stylesheet**. Since this CSS only loads on this template, we can port them as-is, but add a safety: nest everything except `:root` inside `body.single-bigbrother-players` so the styles never leak if the file is mistakenly loaded elsewhere.

Rather than hand-nesting, use a **single top-level scope** by relying on WordPress's body class. WordPress automatically adds the class `single-bigbrother-players` to `<body>` on this template (via `body_class()`). So:

1. Keep the `:root` block at the top (global cascades only matter for variables)
2. Convert the `html, body { background: var(--paper); ... }` rule into `body.single-bigbrother-players { background: var(--paper); ... }`
3. Leave the `* { box-sizing: border-box; ... }` rule as-is — it's already on the same cascade as the theme's global reset

Write the stylesheet as follows (copy the full design `<style>` block verbatim, with the single tweak to the `html, body` rule noted above). Key edits:

**Original (design file, line 17–20):**
```css
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--paper);color:var(--ink);font-family:var(--sans);font-size:15px;line-height:1.5;-webkit-font-smoothing:antialiased}
```

**Ported (keep * as-is — the theme already normalizes margin/padding globally; scope the body rule):**
```css
/* the theme already resets box-sizing and margin/padding; we skip the `*` rule.
   Body-level background + default typography only applies on this template. */
body.single-bigbrother-players{background:var(--paper);color:var(--ink);font-family:var(--sans);font-size:15px;line-height:1.5;-webkit-font-smoothing:antialiased}
```

**Everything else** (`.wrap`, `.topbar`, `header.site`, `nav.primary`, `.crumb`, `.hero`, `.biostrip`, `.grid`, `.biocard`, `.statgrid`, `.timeline`, `.seasons`, `.weeks`, `.cast-grid`, `.compare`, `.artrow`, `aside`, `.card`, `.odds-card`, `.fan`, `.ranks`, `.social`, `.nl`, `.ad`, `footer`, `@media (max-width:1000px)` block) ports **verbatim**.

**Skip these selectors entirely** (they target the design's prototype chrome which we're not porting):
- `.topbar`, `.topbar .wrap`, `.topbar .live`, `.topbar .live::before`, `.topbar .sep`, `.topbar .right`
- `header.site`, `header.site .wrap`, `.logo`, `.logo .mark`, `.logo .mark b`, `.logo .tag`, `.searchbar`, `.searchbar input`, `.searchbar input:focus`, `.searchbar svg`, `.user-actions .btn-login`, `.user-actions .btn-login:hover`
- `nav.primary`, `nav.primary .wrap`, `nav.primary a`, `nav.primary a:hover`, `nav.primary a.active`, `nav.primary a.active::after`, `nav.primary .watch`, `nav.primary .watch::before`
- `@keyframes blink` (used only by the chrome)
- `footer`, `footer .cols`, `footer .brand`, `footer .brand small`, `footer h5`, `footer ul`, `footer a:hover`, `footer .bot`

We're using the theme's own header/footer.

Also **skip** the `.timeline`, `.tl-ruler`, `.tl-row`, `.tl-track`, `.tl-legend`, `.weeks`, `.wk`, `.compare`, `.vs`, `.artrow`, `.art` selectors — those sections are deferred per the spec. Leaving them out now keeps the CSS file smaller; they'll get added back when the relevant sections ship.

- [ ] **Step 5.3: Verify the file loads and is valid CSS**

Load `http://bbj.localhost/bigbrother-players/<slug>/` in a browser. The OLD template is still rendering, but:
- Open devtools → Network → confirm `single-bigbrother-players.css` loads 200 with content
- Open devtools → Console → confirm no CSS parse errors
- Run `getComputedStyle(document.body).backgroundColor` in console — expected `rgb(251, 250, 246)` (our eggshell `#FBFAF6`) — the old template will look weird because it's still using its old rounded-card design over eggshell, but that's fine, next task fixes it

- [ ] **Step 5.4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css
git commit -m "feat(player-profile): port design CSS to scoped stylesheet"
```

---

## Task 6: Template — chrome, breadcrumb, hero, bio strip

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`

- [ ] **Step 6.1: Replace the template with the skeleton + above-the-fold markup**

Overwrite `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php` with:

```php
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
      <!-- Sections land in Tasks 7–9 -->
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
```

- [ ] **Step 6.2: Visual verify**

Load `http://bbj.localhost/bigbrother-players/<slug>/`.

Expected:
- Site header renders (from `get_header()`) on top of eggshell body background
- Breadcrumb row shows correctly
- Hero: dark gradient card, portrait on left (real photo if one is set), name + meta + chips in middle, two action buttons on right
- Bio strip: horizontal 5 (or fewer) cells — no empty cells
- Blank whitespace below (main + sidebar not yet populated — expected)
- Footer renders

Check PHP error log (`C:\xampp\php\logs\php_error_log` or whatever the local error log is) — confirm no notices, warnings, or fatals.

- [ ] **Step 6.3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-players.php
git commit -m "feat(player-profile): template — chrome, breadcrumb, hero, bio strip"
```

---

## Task 7: Template — bio & background section (floated at-a-glance)

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`
- Modify: `wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css`

- [ ] **Step 7.1: Add the bio section to the template**

In `single-bigbrother-players.php`, replace the `<!-- Sections land in Tasks 7–9 -->` placeholder with:

```php
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
```

- [ ] **Step 7.2: Update the `.biocard` CSS to use a float layout instead of a 2-col grid**

In `css/single-bigbrother-players.css`, find the `.biocard` block that was ported from the design (grid-template-columns:1.5fr 1fr) and replace it with this editorial-float version:

```css
/* ==== BIO CARD ==== */
.biocard{background:#fff;border:1px solid var(--line);border-radius:6px;padding:24px 28px;overflow:hidden}
.biocard .at-a-glance{float:right;width:240px;margin:0 0 16px 24px;padding:18px;background:var(--paper-2);border-radius:4px}
.biocard .at-a-glance h4{font-family:var(--display);font-size:12px;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px;color:var(--bb-blue)}
.biocard .at-a-glance dl{display:grid;grid-template-columns:auto 1fr;gap:7px 14px;font-size:13px}
.biocard .at-a-glance dt{font-family:var(--mono);font-size:10px;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);font-weight:600;align-self:center}
.biocard .at-a-glance dd{font-family:var(--sans);color:var(--ink);font-weight:500}
.biocard .copy p{font-family:var(--serif);font-size:16px;line-height:1.6;color:var(--ink-2);text-wrap:pretty}
.biocard .copy p + p{margin-top:10px}
.biocard .copy > p:first-child{font-size:17px;color:var(--ink)}
.biocard .copy > p:first-child::first-letter{font-family:var(--display);font-size:44px;float:left;line-height:.85;margin:4px 8px 0 0;color:var(--bb-blue);font-weight:600}
.biocard .copy blockquote{margin:14px 0 0;padding:14px 18px;background:var(--paper-2);border-left:3px solid var(--bb-yellow);font-family:var(--serif);font-style:italic;font-size:15px;color:var(--ink);border-radius:0 3px 3px 0}
.biocard .copy blockquote cite{font-style:normal;display:block;font-family:var(--mono);font-size:10px;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;margin-top:6px;font-weight:600}

@media (max-width:1000px){
  .biocard .at-a-glance{float:none;width:auto;margin:0 0 16px 0}
}
```

This replaces the design's grid layout so the WP content wraps around the at-a-glance panel.

- [ ] **Step 7.3: Visual verify**

Load the player profile. Expected:
- Bio section appears below the bio strip
- The "At a glance" panel floats to the top-right corner of the card
- The `the_content()` output wraps around it on the left
- First paragraph has a large blue drop-cap initial
- Mobile viewport (< 1000px): at-a-glance stacks above the copy, no float

- [ ] **Step 7.4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-players.php wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css
git commit -m "feat(player-profile): bio & background section with floated at-a-glance"
```

---

## Task 8: Template — career statistics + season history

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`

- [ ] **Step 8.1: Add career stats grid**

In `single-bigbrother-players.php`, **after** the bio section (which closes with `</section>`) and still **inside** the main column `<div>`, append:

```php
      <!-- CAREER STATS -->
      <section>
        <div class="sech">
          <h2>Career Statistics</h2>
          <span class="sub">Across <?php echo (int) $totals['season_count']; ?> season<?php echo $totals['season_count'] === 1 ? '' : 's'; ?></span>
        </div>
        <div class="statgrid">
          <div class="stat"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['season_count']; ?></div><div class="k">Seasons</div></div>
          <div class="stat hoh"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['hoh']; ?></div><div class="k">HoH wins</div></div>
          <div class="stat pov"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['pov']; ?></div><div class="k">PoV wins</div></div>
          <div class="stat nom"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['nom']; ?></div><div class="k">Nominated</div></div>
          <div class="stat"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['votes']; ?></div><div class="k">Jury votes</div></div>
          <div class="stat afp"><span class="delta na">—</span><div class="n"><?php echo (int) $totals['days']; ?></div><div class="k">Days</div></div>
        </div>
      </section>
```

(The `delta na` "—" placeholders are per the spec — we don't have cross-player averages yet.)

- [ ] **Step 8.2: Add season history table**

Append (still inside the main column `<div>`):

```php
      <!-- SEASON HISTORY -->
      <?php if (!empty($seasons)) : ?>
      <section>
        <div class="sech">
          <h2>Season History</h2>
          <span class="sub">Finale placements</span>
        </div>
        <div class="seasons">
          <table>
            <thead>
              <tr><th>Season</th><th>Age</th><th>HoH</th><th>PoV</th><th>Nom</th><th>Votes</th><th>Days</th><th>Progress</th><th>Result</th></tr>
            </thead>
            <tbody>
              <?php foreach ($seasons as $row) :
                $season_url = !empty($row['season_slug']) ? home_url('/bigbrother-seasons/' . $row['season_slug'] . '/') : '#';
                $age_at_season = null;
                if (!empty($player['date_of_birth']) && !empty($row['season_start'])) {
                    try {
                        $age_at_season = (new DateTime($player['date_of_birth']))->diff(new DateTime($row['season_start']))->y;
                    } catch (Exception $e) {}
                }
                $days_this_season = 0;
                if (!empty($row['season_start'])) {
                    $end = $row['bbj_evicted_date'] ?: ($row['season_end'] ?: date('Y-m-d'));
                    try {
                        $days_this_season = max(0, (new DateTime($row['season_start']))->diff(new DateTime($end))->days);
                    } catch (Exception $e) {}
                }
                // Progress bar: better placement = fuller bar. Compute against season contestant count.
                global $wpdb;
                $season_size = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}bbj_v2_player_season WHERE bbj_season = %d",
                    (int) $row['bbj_season']
                ));
                $finish = (int) ($row['bbj_finish_place'] ?? 0);
                $progress = ($season_size > 0 && $finish > 0) ? round((($season_size - $finish + 1) / $season_size) * 100) : 0;

                // Result pill label.
                $result_label = '';
                $result_class = '';
                if ((int) $row['season_winner'] === (int) $post_id) {
                    $result_label = 'Winner'; $result_class = 'winner';
                } elseif ((int) $row['runner_up'] === (int) $post_id) {
                    $result_label = 'Runner-up · 2nd'; $result_class = 'runnerup';
                } elseif ((int) $row['afp'] === (int) $post_id) {
                    $result_label = 'AFP' . ($finish ? ' · ' . bbj_v2_player_profile_ordinal($finish) : '');
                    $result_class = 'afp';
                } elseif (!empty($row['current_jury'])) {
                    $result_label = 'Jury' . ($finish ? ' · ' . bbj_v2_player_profile_ordinal($finish) : '');
                    $result_class = 'jury';
                } elseif ($finish > 0) {
                    $result_label = 'Evicted · ' . bbj_v2_player_profile_ordinal($finish);
                } else {
                    $result_label = 'Active';
                }
              ?>
                <tr>
                  <td><a class="season" href="<?php echo esc_url($season_url); ?>"><?php echo esc_html($row['season_name']); ?></a></td>
                  <td class="stat-n"><?php echo $age_at_season !== null ? (int) $age_at_season : '—'; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_total_hoh']; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_total_pov']; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_total_nom']; ?></td>
                  <td class="stat-n"><?php echo (int) $row['bbj_votes_received']; ?></td>
                  <td class="stat-n"><?php echo (int) $days_this_season; ?></td>
                  <td><div class="progbar"><div class="bar"><b style="width:<?php echo (int) $progress; ?>%"></b></div><span class="p"><?php echo (int) $progress; ?>%</span></div></td>
                  <td class="result"><span class="pill <?php echo esc_attr($result_class); ?>"><?php echo esc_html($result_label); ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
```

- [ ] **Step 8.3: Visual verify**

Load the player profile.

Expected:
- Career stats: 6-up grid with real numbers + dash-placeholders for deltas
- Season history table renders for players with junction data; is absent entirely for players with none (no empty shell)
- Result pill colors follow the design palette (Winner = purple per design's existing `.seasons .result .pill` rule)
- Mobile: stat grid collapses to 3 columns, table stays scrollable

- [ ] **Step 8.4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-players.php
git commit -m "feat(player-profile): career stats + season history table"
```

---

## Task 9: Template — castmates grid

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`

- [ ] **Step 9.1: Fetch castmates and render the grid**

Append inside the main column `<div>`, after the season history section:

```php
      <!-- CASTMATES -->
      <?php
      $castmates = ($latest && !empty($latest['bbj_season']))
          ? bbj_v2_player_profile_castmates($post_id, (int) $latest['bbj_season'])
          : [];
      if (!empty($castmates)) :
        $season_abbr = $latest['season_abbr'] ?: 'Big Brother';
      ?>
      <section>
        <div class="sech">
          <h2>Castmates · <?php echo esc_html($season_abbr); ?></h2>
          <span class="sub">Who they played with</span>
          <?php if (!empty($latest['season_slug'])) : ?>
            <span class="spacer"></span>
            <a class="link" href="<?php echo esc_url(home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/')); ?>">Full cast →</a>
          <?php endif; ?>
        </div>
        <div class="cast-grid">
          <?php foreach ($castmates as $cm) :
            $cm_full = trim(($cm['first_name'] ?? '') . ' ' . ($cm['last_name'] ?? ''));
            $cm_display = $cm['official_nickname'] ?: ($cm['first_name'] ?: $cm_full);
            $cm_url = !empty($cm['player_slug']) ? home_url('/bigbrother-players/' . $cm['player_slug'] . '/') : '#';
            $cm_finish = (int) ($cm['bbj_finish_place'] ?? 0);

            $tag_class = 'pre';
            $tag_text = 'Out';
            if ((int) $cm['season_winner'] === (int) $cm['player_post_id']) {
                $tag_class = 'win'; $tag_text = 'Winner';
            } elseif ((int) $cm['runner_up'] === (int) $cm['player_post_id']) {
                $tag_class = 'win'; $tag_text = '2nd';
            } elseif ((int) $cm['afp'] === (int) $cm['player_post_id']) {
                $tag_class = 'jury'; $tag_text = 'AFP';
            } elseif (!empty($cm['current_jury'])) {
                $tag_class = 'jury'; $tag_text = 'Jury';
            }
          ?>
            <a class="cm" href="<?php echo esc_url($cm_url); ?>" title="<?php echo esc_attr($cm_full); ?>">
              <div class="face" data-i="<?php echo esc_attr(strtoupper(substr($cm_display, 0, 2))); ?>">
                <?php if (!empty($cm['profile_picture'])) : ?>
                  <?php echo wp_get_attachment_image((int) $cm['profile_picture'], 'thumbnail', false, [
                      'alt'   => sprintf('%s, %s', $cm_full, $season_abbr),
                      'style' => 'width:100%;height:100%;object-fit:cover;position:absolute;inset:0;',
                  ]); ?>
                <?php endif; ?>
                <span class="tag <?php echo esc_attr($tag_class); ?>"><?php echo esc_html($tag_text); ?></span>
              </div>
              <div class="n"><?php echo esc_html($cm_display); ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
```

(Note: per the spec §9 open question, we don't inject the current player into the grid — we're on their page, showing them is redundant. The design's "You" badge is skipped. If the user later wants self-inclusion, it's a one-line addition.)

- [ ] **Step 9.2: Visual verify**

Load the player profile.

Expected:
- Castmates section appears below the season history
- 8-column grid on desktop, 4-column on mobile
- Each tile shows the castmate's photo (or gradient + initials fallback) with a status tag
- Clicking a tile goes to that castmate's profile

Verify with a player whose season has real junction data. If the current player has no season data, the castmates section should be absent.

- [ ] **Step 9.3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-players.php
git commit -m "feat(player-profile): castmates grid"
```

---

## Task 10: Template — sidebar (placeholders + Follow + ad)

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`

- [ ] **Step 10.1: Render sidebar cards**

In `single-bigbrother-players.php`, find the `<!-- Sidebar cards land in Task 10 -->` placeholder and replace it with:

```php
        <!-- AFP Odds placeholder -->
        <div class="card odds-card">
          <h4>AFP Odds <small>Coming soon</small></h4>
          <div class="odds-big">
            <div class="k">Voting system</div>
            <div class="n" style="font-size:28px;-webkit-text-stroke:0;text-shadow:none;">—</div>
            <div class="d">AFP voting runs all season. Custom polling system in the works — not Jokers'.</div>
          </div>
        </div>

        <!-- Fan Affinity placeholder -->
        <div class="card fan">
          <h4>Fan Affinity <small>Awaiting votes</small></h4>
          <p style="font-family:var(--serif);font-size:13px;color:var(--ink-2);line-height:1.45;">Needs 10+ fan ratings to display. Ratings open once the voting system ships.</p>
        </div>

        <!-- Fan Ranking placeholder -->
        <div class="card ranks">
          <h4><?php echo esc_html($latest['season_abbr'] ?? 'Season'); ?> Fan Ranking</h4>
          <p style="font-family:var(--serif);font-size:13px;color:var(--ink-2);line-height:1.45;">Season ranking opens once enough affinity scores accumulate.</p>
        </div>

        <!-- Follow card -->
        <?php if (!empty($player['socials'])) :
          $social_labels = [
            'twitter'   => ['ic' => '𝕏',   'label' => 'X / Twitter'],
            'instagram' => ['ic' => '📷', 'label' => 'Instagram'],
            'facebook'  => ['ic' => 'f',   'label' => 'Facebook'],
            'tiktok'    => ['ic' => '♪',   'label' => 'TikTok'],
          ];
        ?>
        <div class="card social">
          <h4>Follow <?php echo esc_html($player['first_name'] ?: $player['full_name']); ?></h4>
          <div class="socials">
            <?php foreach ($player['socials'] as $platform => $url) :
              $meta = $social_labels[$platform] ?? ['ic' => '↗', 'label' => ucfirst($platform)];
            ?>
              <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                <span class="ic"><?php echo esc_html($meta['ic']); ?></span>
                <span><?php echo esc_html($meta['label']); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Sticky ad rail -->
        <?php get_template_part('template-parts/components/ad-placeholder', null, [
          'slot'        => 'player_profile_sidebar',
          'size'        => '300x600',
          'mobile_size' => '300x250',
          'note'        => __('Single player profile · right rail', 'bbj-v2-theme'),
        ]); ?>
```

- [ ] **Step 10.2: Visual verify**

Load the player profile.

Expected:
- Right rail sticks to viewport as you scroll (desktop)
- Three placeholder cards render with their "reserved spot" copy — they should match the design's card styling (paper bg, top border, bold h4)
- Follow card only appears for players with at least one social URL; hidden otherwise
- Ad placeholder renders at 300×600
- Mobile: sidebar stacks below main column, non-sticky

- [ ] **Step 10.3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-players.php
git commit -m "feat(player-profile): sidebar placeholders + Follow + ad slot"
```

---

## Task 11: SEO — breadcrumb schema + Person JSON-LD + final smoke matrix

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`

- [ ] **Step 11.1: Inject `Person` + `BreadcrumbList` JSON-LD**

In `single-bigbrother-players.php`, directly **before** the `get_header();` call (but **after** the data fetches), add:

```php
// --- JSON-LD: Person + BreadcrumbList ---
$person_schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $player['full_name'] ?: get_the_title(),
    'url'      => get_permalink($post_id),
];
if (!empty($player['date_of_birth']))          $person_schema['birthDate']   = $player['date_of_birth'];
if (!empty($player['occupation']))             $person_schema['jobTitle']    = $player['occupation'];
if (!empty($player['hometown']))               $person_schema['homeLocation'] = ['@type' => 'Place', 'name' => $player['hometown']];
if (!empty($player['profile_picture']))        $person_schema['image']       = wp_get_attachment_image_url($player['profile_picture'], 'full') ?: null;
if (!empty($player['socials']))                $person_schema['sameAs']      = array_values($player['socials']);

$breadcrumb_items = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',        'item' => home_url('/')],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Houseguests', 'item' => home_url('/houseguests/')],
];
if ($latest && !empty($latest['season_slug'])) {
    $breadcrumb_items[] = [
        '@type' => 'ListItem', 'position' => 3,
        'name'  => $latest['season_abbr'] ?: $latest['season_name'],
        'item'  => home_url('/bigbrother-seasons/' . $latest['season_slug'] . '/'),
    ];
    $breadcrumb_items[] = [
        '@type' => 'ListItem', 'position' => 4,
        'name'  => $player['full_name'] ?: get_the_title(),
        'item'  => get_permalink($post_id),
    ];
} else {
    $breadcrumb_items[] = [
        '@type' => 'ListItem', 'position' => 3,
        'name'  => $player['full_name'] ?: get_the_title(),
        'item'  => get_permalink($post_id),
    ];
}

$breadcrumb_schema = [
    '@context'         => 'https://schema.org',
    '@type'            => 'BreadcrumbList',
    'itemListElement'  => $breadcrumb_items,
];
```

Then, inside the `<main class="wrap">` section, right **before** the `<nav class="crumb">` opening tag, add:

```php
  <script type="application/ld+json"><?php echo wp_json_encode($person_schema, JSON_UNESCAPED_SLASHES); ?></script>
  <script type="application/ld+json"><?php echo wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_SLASHES); ?></script>
```

- [ ] **Step 11.2: Run the smoke matrix**

Load each scenario. For each, verify (a) page renders without PHP notices/errors and (b) the specified section behaves as noted.

| # | Scenario                                                       | Check                                                                                          |
|---|----------------------------------------------------------------|------------------------------------------------------------------------------------------------|
| 1 | A player with full data (real photo, geo, DOB, socials, stats) | All sections render. Hero photo visible. Bio wraps around at-a-glance. Castmates grid populated. Follow card shows all 4 socials. |
| 2 | An active (not yet evicted) player                             | Bio strip shows "Currently playing" placement. Days in house = today - season start. No eviction cell. |
| 3 | A player with NO season data (junction rows empty)             | Hero renders without AFP badge. Career stats show all zeros. Season history section absent. Castmates section absent. |
| 4 | A player with NO geo data (missing `wp_bbj_geo` row)           | Hometown cell in bio strip is omitted (4-col strip, not 5). "From" row in hero meta is omitted. No JSON-LD error. |
| 5 | A player with NO DOB                                           | Age cell in bio strip + hero omitted. Birthday row in at-a-glance omitted. JSON-LD has no `birthDate` key. |
| 6 | A player with NO socials                                       | Follow card is entirely absent (not an empty shell). `sameAs` is absent from JSON-LD. |
| 7 | A player who won AFP in any season                             | Hero portrait shows the yellow "AFP / Season XX" badge. Chips row starts with "♥ America's Favorite". |
| 8 | Mobile viewport (< 1000px)                                     | Hero collapses to single column. Stat grid 6-col → 3-col. Castmates 8-col → 4-col. Sidebar stacks below main. |
| 9 | Dark mode via `<html class="dark">` (theme toggle)             | Page still looks correct — OR — at minimum, text is readable. Full dark-mode parity is a stretch; log issues for a follow-up task if any. |

For any failure, fix before moving on. Dark-mode issues (#9) are acceptable to note and defer — log them as GitHub-style TODOs in the stylesheet if real.

Run the JSON-LD outputs through https://validator.schema.org (or the "Rich Results Test" at https://search.google.com/test/rich-results) and confirm no errors. If validation fails, fix the JSON-LD before committing.

- [ ] **Step 11.3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/single-bigbrother-players.php
git commit -m "feat(player-profile): Person + BreadcrumbList JSON-LD"
```

- [ ] **Step 11.4: (Optional) Dark-mode polish commit**

If #9 exposed real issues, add dark-mode overrides to `css/single-bigbrother-players.css` before the mobile `@media` block:

```css
@media (prefers-color-scheme: dark){ /* or target body.dark if theme uses a class */ }
```

Keep this targeted — don't rewrite the whole stylesheet. Commit separately:

```bash
git add wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css
git commit -m "fix(player-profile): dark-mode polish"
```

---

## Self-review notes (inline fixups)

After writing the plan above, a fresh read surfaced these items — all addressed inline:

1. **Spec coverage:** Every MVP section from spec §4 has a task. Reserved sidebar placeholders from spec §4.8 land in Task 10. SEO from spec §6 lands in Task 11. Deferred sections (Game Timeline, Week-by-week, Compare, Articles, Fan Voting, HG Alerts) intentionally have no tasks — they're future sprints.
2. **No placeholders:** No TBD/TODO/"implement later". Every code step has concrete code. The `delta na` "—" in Task 8 is a rendered placeholder (specified in the spec), not a plan placeholder.
3. **Type consistency:** `bbj_v2_player_profile_ordinal()` is defined in Task 4 and used in Tasks 4 and 8 — consistent signature. Array key names (`full_name`, `hometown`, `socials`, `latest_season`, `season_abbr`, `season_slug`, etc.) match across tasks.

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-24-player-profile.md`. Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
