# Spoiler Bar Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the stubbed `#spoiler` tab on the season edit page with a card-per-player editor that matches the Next.js reference, plus the schema and sort changes needed to support it.

**Architecture:** Template partials in the theme render the form; the existing `bbj_v2_update_season()` handler in the plugin saves it (minor extension for a new `bbj_finish_place` column). One new handler for "Purge Cache." Tab default flips to `#spoiler`. The public `[bbj_spoiler_bar]` shortcode grows a `$skip_cache` param so the preview strip inside the editor shows just-saved state immediately.

**Tech Stack:** WordPress 6.x, PHP 8, Tailwind CSS 3.4 (with `has-[:checked]:*` variant for pure-CSS toggle chips), vanilla JS (existing hashchange handler only).

**Testing:** Manual smoke-testing on `http://bbj.localhost/`. No automated tests (matches repo convention).

---

## Spec reference

`docs/superpowers/specs/2026-04-22-spoiler-bar-editor-design.md`

---

## File structure

**Create (theme):**
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-spoiler.php` — tab body (preview + groups + forms)
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/spoiler-preview-strip.php` — uncached spoiler-bar render
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/spoiler-player-card.php` — one player card (all 19 existing fields + finish_place)

**Create (plugin):**
- `wp-content/plugins/bbj-v2/includes/Actions/form-submits/purge-season-cache.php` — purge handler

**Modify (plugin):**
- `wp-content/plugins/bbj-v2/includes/Helpers/create-db-tables.php` — add `bbj_finish_place` column
- `wp-content/plugins/bbj-v2/includes/Public/shortcodes/spoiler-bar.php` — add `$skip_cache` param + finish_place tiebreaker
- `wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php` — read + write `bbj_finish_place`
- `wp-content/plugins/bbj-v2/includes/Actions/action-list.php` — register new purge action

**Modify (theme):**
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php` — flip `DEFAULT_TAB` to `'spoiler'` + swap stub for real partial

---

### Task 1: Schema migration for `bbj_finish_place`

**Files:**
- Modify: `wp-content/plugins/bbj-v2/includes/Helpers/create-db-tables.php`

**Context:** `wp_bbj_v2_player_season` needs a nullable finish-place column so double-eviction days sort correctly. `dbDelta()` runs on plugin activation and is idempotent — adding a new nullable column is safe; existing rows get NULL.

- [ ] **Step 1: Add the new column to the `CREATE TABLE` SQL**

In `wp-content/plugins/bbj-v2/includes/Helpers/create-db-tables.php`, find the `bbj_create_player_season_table()` function's `$sql` block (line 15). The existing block is:

```php
    $sql = "CREATE TABLE {$table_name} (
        id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id                 BIGINT(20) UNSIGNED NOT NULL,
        bbj_season              BIGINT(20) UNSIGNED NOT NULL,
        bbj_player              BIGINT(20) UNSIGNED NOT NULL,
        bbj_evicted_date        DATE             DEFAULT NULL,
        bbj_total_hoh           INT(11)          NOT NULL DEFAULT 0,
```

Insert ONE new column line immediately after `bbj_evicted_date` so the SQL reads:

```php
    $sql = "CREATE TABLE {$table_name} (
        id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id                 BIGINT(20) UNSIGNED NOT NULL,
        bbj_season              BIGINT(20) UNSIGNED NOT NULL,
        bbj_player              BIGINT(20) UNSIGNED NOT NULL,
        bbj_evicted_date        DATE             DEFAULT NULL,
        bbj_finish_place        TINYINT(3) UNSIGNED DEFAULT NULL,
        bbj_total_hoh           INT(11)          NOT NULL DEFAULT 0,
```

Use Edit with exact string match. Do NOT rewrite the whole file.

- [ ] **Step 2: Trigger the migration**

Deactivate and reactivate the bbj-v2 plugin so `dbDelta()` runs with the updated SQL. Two options:

A. In `/wp-admin/plugins.php`, deactivate "BBJ v2," then reactivate.

B. Via wp-cli (if available): `wp plugin deactivate bbj-v2 && wp plugin activate bbj-v2`

- [ ] **Step 3: Verify the column exists**

Run this in phpMyAdmin (or via wp-cli's `wp db query`):

```sql
SHOW COLUMNS FROM wp_bbj_v2_player_season WHERE Field = 'bbj_finish_place';
```

Expected: one row back with `Type` = `tinyint(3) unsigned`, `Null` = `YES`, `Default` = `NULL`.

Also verify existing rows have NULL:

```sql
SELECT COUNT(*) FROM wp_bbj_v2_player_season WHERE bbj_finish_place IS NOT NULL;
```

Expected: `0`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Helpers/create-db-tables.php
git commit -m "feat(plugin): add bbj_finish_place column to wp_bbj_v2_player_season"
```

---

### Task 2: Public spoiler-bar — add `$skip_cache` param + finish_place sort

**Files:**
- Modify: `wp-content/plugins/bbj-v2/includes/Public/shortcodes/spoiler-bar.php`

**Context:** The public `bbj_render_spoiler_bar()` shortcode renders the front-site spoiler bar. It caches HTML for 300s. We need two changes:
1. An optional `$skip_cache` param so the admin preview strip can request a fresh render
2. Inside the Jury/Evicted sort bucket, use `bbj_finish_place` as the primary tiebreaker before falling back to `bbj_evicted_date`

These are surgical changes — do NOT rewrite the function.

- [ ] **Step 1: Change the function signature + skip-cache branch**

In `spoiler-bar.php`, find the current function opener at line 7:

```php
function bbj_render_spoiler_bar() {
    global $bbj_is_admin;
    // get current season from options 
    $current_season_id = get_option( 'bbj_v2_current_season', '' );
    $current_season = bbj_v2_get_season_by_id( $current_season_id );

    // Caching 
    $cache_key = bbj_spoiler_bar_cache_key( (int)$current_season_id, (bool)$bbj_is_admin );
    if ( false !== ($cached = wp_cache_get($cache_key, BBJ_CACHE_GROUP)) ) {
        return $cached;
    }
```

Replace with:

```php
function bbj_render_spoiler_bar( $override_season_id = null, $skip_cache = false ) {
    global $bbj_is_admin;
    // get current season from options (unless caller passes a specific season)
    $current_season_id = ( $override_season_id !== null && (int) $override_season_id > 0 )
        ? (int) $override_season_id
        : get_option( 'bbj_v2_current_season', '' );
    $current_season = bbj_v2_get_season_by_id( $current_season_id );

    // Caching — caller may request a fresh render (e.g. admin preview after save)
    $cache_key = bbj_spoiler_bar_cache_key( (int)$current_season_id, (bool)$bbj_is_admin );
    if ( ! $skip_cache && false !== ($cached = wp_cache_get($cache_key, BBJ_CACHE_GROUP)) ) {
        return $cached;
    }
```

Why two params instead of one: the preview strip inside the admin editor needs to render a season OTHER than `bbj_v2_current_season` (an admin could be editing BB25 while BB27 is current). The shortcode call from the public homepage passes no args, so existing behavior is preserved by defaulting both params to sensible values.

- [ ] **Step 2: Do not cache the result when `$skip_cache` is true**

Find the cache write at the end of the function (line 155):

```php
    $html = ob_get_clean();
    wp_cache_set($cache_key, $html, BBJ_CACHE_GROUP, BBJ_CACHE_TTL);
    return $html;
}
```

Replace with:

```php
    $html = ob_get_clean();
    if ( ! $skip_cache ) {
        wp_cache_set($cache_key, $html, BBJ_CACHE_GROUP, BBJ_CACHE_TTL);
    }
    return $html;
}
```

- [ ] **Step 3: Add `bbj_finish_place` as the Jury/Evicted tiebreaker**

Find the sort block for Jury/Evicted at lines 41-58:

```php
        // Same bucket: Jury (5) or Evicted (6) → sort by bbj_evicted_date
        if ($wa === 5 || $wa === 6) {
            $da = bbj_eviction_ts($a['bbj_evicted_date'] ?? null);
            $db = bbj_eviction_ts($b['bbj_evicted_date'] ?? null);

            // nulls (no real date) go last
            if ($da === $db) {
                // tie-break by name/id to avoid unstable shuffles
                $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                $bn = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
                $cmp = strcasecmp($an, $bn);
                return $cmp !== 0 ? $cmp : ((int)($a['bbj_player'] ?? 0) <=> (int)($b['bbj_player'] ?? 0));
            }
            if ($da === null) return 1;
            if ($db === null) return -1;

            // NEWEST → OLDEST (flip to $da <=> $db for oldest → newest)
            return $db <=> $da;
        }
```

Replace with:

```php
        // Same bucket: Jury (5) or Evicted (6) → sort by finish_place (authoritative)
        // then bbj_evicted_date (fallback for historical seasons with no finish_place)
        if ($wa === 5 || $wa === 6) {
            // Primary: finish_place ASC — explicit values win, NULLs fall through to date sort
            $fa = isset($a['bbj_finish_place']) && $a['bbj_finish_place'] !== null && $a['bbj_finish_place'] !== ''
                ? (int) $a['bbj_finish_place'] : null;
            $fb = isset($b['bbj_finish_place']) && $b['bbj_finish_place'] !== null && $b['bbj_finish_place'] !== ''
                ? (int) $b['bbj_finish_place'] : null;
            if ($fa !== null && $fb !== null && $fa !== $fb) {
                return $fa <=> $fb; // 1st place before 2nd, etc.
            }
            if ($fa !== null && $fb === null) return -1;
            if ($fa === null && $fb !== null) return 1;

            // Secondary: evicted_date DESC (newest first) — preserves prior behavior
            $da = bbj_eviction_ts($a['bbj_evicted_date'] ?? null);
            $db = bbj_eviction_ts($b['bbj_evicted_date'] ?? null);

            if ($da === $db) {
                // tie-break by name/id to avoid unstable shuffles
                $an = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                $bn = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''));
                $cmp = strcasecmp($an, $bn);
                return $cmp !== 0 ? $cmp : ((int)($a['bbj_player'] ?? 0) <=> (int)($b['bbj_player'] ?? 0));
            }
            if ($da === null) return 1;
            if ($db === null) return -1;

            return $db <=> $da;
        }
```

- [ ] **Step 4: Smoke test — public render unchanged for current season**

Visit `http://bbj.localhost/` (homepage). The spoiler bar should render identically to before (current season BB26, no `bbj_finish_place` values set yet, so the evicted_date sort path still runs).

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Public/shortcodes/spoiler-bar.php
git commit -m "feat(plugin): bbj_render_spoiler_bar accepts season override + skip_cache + finish_place sort"
```

---

### Task 3: Extend `bbj_v2_update_season()` to read + write `bbj_finish_place`

**Files:**
- Modify: `wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php`

**Context:** The existing handler saves 19 per-player fields. Add one more: `finish_place[$player_id]`. When blank or `0`, store NULL (so the sort treats "unset" as truly unset rather than 1st place).

- [ ] **Step 1: Read the new POST field**

In `bbj_v2_update_season()`, find the input-collection block at lines 129-149 (the `// pull in input data` comment). The final line there reads:

```php
    $misc_notes        = $_POST['misc_notes']        ?? [];
```

Insert a new line immediately after it:

```php
    $finish_place      = $_POST['finish_place']      ?? [];
```

- [ ] **Step 2: Add the field to the per-player `$data` array**

Find the `$data` array inside the foreach loop at lines 156-176. The current `$data` array ends with:

```php
            'current_safe'        => isset( $current_safe[ $player_id ] )    ? 1 : 0,
            'misc_notes'          => sanitize_text_field( $misc_notes[ $player_id ] ?? '' ),
        ];
```

Replace that closing block with:

```php
            'current_safe'        => isset( $current_safe[ $player_id ] )    ? 1 : 0,
            'misc_notes'          => sanitize_text_field( $misc_notes[ $player_id ] ?? '' ),
            // NULL when blank or 0 so the sort doesn't treat "unset" as 1st place
            'bbj_finish_place'    => ( isset( $finish_place[ $player_id ] ) && (int) $finish_place[ $player_id ] > 0 )
                                       ? (int) $finish_place[ $player_id ]
                                       : null,
        ];
```

- [ ] **Step 3: Update the `$wpdb->update()` format array**

Find the format array at lines 188-193:

```php
        $updated = $wpdb->update(
            $link_table,
            $data,
            $where,
            [
                '%s', // bbj_evicted_date
                '%d','%d','%d','%d','%d','%d','%d','%d', // original counts
                '%d','%d','%d','%d','%d','%d','%d','%d', // checkbox flags
                '%s'  // misc_notes
            ],
            [ '%d', '%d' ]
        );
```

Replace with:

```php
        $updated = $wpdb->update(
            $link_table,
            $data,
            $where,
            [
                '%s', // bbj_evicted_date
                '%d','%d','%d','%d','%d','%d','%d','%d', // original counts
                '%d','%d','%d','%d','%d','%d','%d','%d', // checkbox flags
                '%s', // misc_notes
                '%d'  // bbj_finish_place (NULL passes through as NULL via $wpdb)
            ],
            [ '%d', '%d' ]
        );
```

Note: when `$data['bbj_finish_place']` is PHP `null`, `$wpdb->update()` writes `NULL` to the database regardless of the `%d` format. This is WP-native behavior.

- [ ] **Step 4: Smoke test**

Since the form UI doesn't exist yet, temporarily test by manually POSTing via curl or by running this SQL after a manual save:

```sql
SELECT bbj_player, bbj_finish_place FROM wp_bbj_v2_player_season WHERE bbj_season = <current_bb_season_id> LIMIT 5;
```

Expected: all NULL (since no form is sending the field yet). Confirms no regression.

Also hit the existing old wp-admin editor at `/wp-admin/admin.php?page=bbj-v2-edit-season` and save a random edit. Confirm it doesn't 500 (the old editor never sends `finish_place[]`, which means the `$finish_place` array is empty, so every row's `bbj_finish_place` gets set to NULL — which is harmless since they're all already NULL).

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php
git commit -m "feat(plugin): bbj_v2_update_season reads + writes bbj_finish_place (NULL when blank/0)"
```

---

### Task 4: Purge-Cache handler + registration

**Files:**
- Create: `wp-content/plugins/bbj-v2/includes/Actions/form-submits/purge-season-cache.php`
- Modify: `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`

**Context:** Tiny handler that just calls `bbj_spoiler_bar_bust_cache($season_id)`. Lets the admin wipe the spoiler-bar cache without changing data.

- [ ] **Step 1: Write the handler**

Create `wp-content/plugins/bbj-v2/includes/Actions/form-submits/purge-season-cache.php` with this exact content:

```php
<?php
/**
 * Purges the spoiler-bar cache for a single season.
 * No DB writes — pure cache wipe.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_purge_season_cache() {
    // 1. Security + capability
    if (
        empty($_POST['bbj_v2_purge_season_cache_nonce']) ||
        ! wp_verify_nonce($_POST['bbj_v2_purge_season_cache_nonce'], 'bbj_v2_purge_season_cache_action') ||
        ! current_user_can('manage_options')
    ) {
        wp_die('Permission check failed');
    }

    $season_id = isset($_POST['season_id']) ? absint($_POST['season_id']) : 0;
    if ($season_id <= 0) {
        wp_die('Invalid season id');
    }

    bbj_spoiler_bar_bust_cache($season_id);

    // 2. Redirect back with purged=1
    $redirect = wp_get_referer() ?: add_query_arg(
        ['tab' => 'seasons', 'edit' => $season_id],
        home_url('/admin/')
    );
    wp_safe_redirect(add_query_arg('purged', '1', $redirect));
    exit;
}
```

- [ ] **Step 2: Register the action**

In `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`, find the `bbj_v2_create_season` registration block (recently added, around line 44):

```php
// Create a new season (draft)
add_action('admin_post_bbj_v2_create_season', 'BBJ_load_create_season_handler');

function BBJ_load_create_season_handler() {
    require_once BBJ_FORM_SUBMITS . 'create-season.php';
    bbj_v2_create_season();
}
```

Insert this block immediately after the closing `}` of `BBJ_load_create_season_handler`:

```php
// Purge spoiler-bar cache for a single season (no DB writes)
add_action('admin_post_bbj_v2_purge_season_cache', 'BBJ_load_purge_season_cache_handler');

function BBJ_load_purge_season_cache_handler() {
    require_once BBJ_FORM_SUBMITS . 'purge-season-cache.php';
    bbj_v2_purge_season_cache();
}
```

- [ ] **Step 3: Smoke test deferred**

The button doesn't exist yet (it goes in Task 7). No smoke test at this step — just confirm no PHP fatal on load. Hit any front-end page to force PHP to parse the updated action-list.php; if the site loads without a blank white page, the registration is syntactically valid.

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Actions/form-submits/purge-season-cache.php wp-content/plugins/bbj-v2/includes/Actions/action-list.php
git commit -m "feat(plugin): add bbj_v2_purge_season_cache handler"
```

---

### Task 5: Player card partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/spoiler-player-card.php`

**Context:** Dense card per player. Renders all 19 existing fields plus `finish_place`. All inputs live inside the outer `<form>` (rendered by Task 7), so this partial outputs only the card contents — no form tags, no nonce, no submit button.

Field names MUST match what `bbj_v2_update_season()` reads (confirmed in Task 3):
- Numeric: `finish_place[]`, `hoh_count[]`, `veto_count[]`, `nom_count[]`, `veto_played[]`, `misc_count[]`, `saved_count[]`, `havenot_count[]`, `votes_received[]`
- Date: `evicted_date[]`
- Text: `misc_notes[]`
- Checkboxes: `current_hoh[]`, `current_pov[]`, `current_nom[]`, `current_havenot[]`, `current_evicted[]`, `current_misc[]`, `current_jury[]`, `current_safe[]`

The `current_evicted` is rendered as a radio group (N/Y) for ergonomics, but posts the same field name.

- [ ] **Step 1: Create the file with this exact content**

```php
<?php
/**
 * Admin shell — single player card inside the Spoiler Bar tab.
 *
 * Receives $args['player'] — a row from bbj_v2_get_season_players() (ARRAY_A).
 * Receives $args['season'] — the wp_bbj_seasons row object (for winner/runner-up/AFP comparisons).
 */

if (!defined('ABSPATH')) {
    exit;
}

$player = $args['player'];
$season = $args['season'];

$player_id   = (int) ($player['bbj_player'] ?? 0);
$first_name  = (string) ($player['first_name'] ?? '');
$last_name   = (string) ($player['last_name'] ?? '');
$nickname    = (string) ($player['official_nickname'] ?? '');
$avatar_url  = (string) ($player['profile_picture_url'] ?? '');
$display_full = trim($first_name . ' ' . $last_name);
$display_card = $nickname !== '' ? '"' . $nickname . '"' : $first_name;

// Current-state reads from the row (INTs from DB, treat as bool)
$current_hoh     = !empty($player['current_hoh']);
$current_pov     = !empty($player['current_pov']);
$current_nom     = !empty($player['current_nom']);
$current_havenot = !empty($player['current_havenot']);
$current_evicted = !empty($player['current_evicted']);
$current_misc    = !empty($player['current_misc']);
$current_jury    = !empty($player['current_jury']);
$current_safe    = !empty($player['current_safe']);

$evicted_date   = (string) ($player['bbj_evicted_date'] ?? '');
$misc_notes     = (string) ($player['misc_notes'] ?? '');
$finish_place   = $player['bbj_finish_place'] ?? '';

// Stat counts
$hoh_count      = (int) ($player['bbj_total_hoh'] ?? 0);
$veto_count     = (int) ($player['bbj_total_pov'] ?? 0);
$nom_count      = (int) ($player['bbj_total_nom'] ?? 0);
$veto_played    = (int) ($player['bbj_veto_played'] ?? 0);
$misc_count     = (int) ($player['bbj_total_misc'] ?? 0);
$saved_count    = (int) ($player['bbj_total_saved'] ?? 0);
$havenot_count  = (int) ($player['bbj_total_havenot'] ?? 0);
$votes_received = (int) ($player['bbj_votes_received'] ?? 0);

// Determine primary status for the left-border accent.
// Winner / runner-up / AFP live on the seasons row; the rest come from the player's flags.
$winner_id    = (int) ($season->season_winner ?? 0);
$runner_up_id = (int) ($season->runner_up ?? 0);
$afp_id       = (int) ($season->afp ?? 0);

$primary = 'stone';
if     ($player_id === $winner_id && $winner_id > 0)       $primary = 'yellow';
elseif ($player_id === $runner_up_id && $runner_up_id > 0) $primary = 'sky';
elseif ($player_id === $afp_id && $afp_id > 0)             $primary = 'pink';
elseif ($current_hoh)                                      $primary = 'emerald';
elseif ($current_pov)                                      $primary = 'purple';
elseif ($current_nom)                                      $primary = 'red';
elseif ($current_safe)                                     $primary = 'green';
elseif ($current_havenot)                                  $primary = 'slate';
elseif ($current_jury)                                     $primary = 'indigo';
elseif ($current_evicted)                                  $primary = 'gray';

$border_class = 'border-l-' . $primary . '-500';

// Toggle-chip helper classes
$chip_base = 'cursor-pointer select-none inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold border text-stone-500 border-stone-300 bg-white dark:bg-slate-900 dark:text-slate-400 dark:border-slate-700 transition-colors';
$chip_checked_on = ' has-[:checked]:bg-primary-500 has-[:checked]:text-white has-[:checked]:border-primary-500';
?>

<div class="border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 border-l-4 <?php echo esc_attr($border_class); ?> p-3 mb-2 text-sm">

    <!-- Row 1: avatar, name, finish -->
    <div class="flex items-center gap-3 mb-2">
        <?php if ($avatar_url): ?>
            <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($display_full); ?>"
                 class="w-10 h-10 object-cover rounded-full border border-stone-200 dark:border-slate-700">
        <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-stone-100 dark:bg-slate-800 flex items-center justify-center text-stone-500 dark:text-slate-400 font-bold">
                <?php echo esc_html(strtoupper(substr($first_name, 0, 1))); ?>
            </div>
        <?php endif; ?>

        <div class="flex-1">
            <div class="font-semibold text-stone-800 dark:text-slate-200">
                <?php echo esc_html($display_full !== '' ? $display_full : '(Unnamed player)'); ?>
            </div>
            <div class="text-xs text-stone-500 dark:text-slate-500">
                Card name: <?php echo esc_html($display_card); ?>
            </div>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <label class="text-stone-600 dark:text-slate-400" for="bbj-fin-<?php echo $player_id; ?>">Fin#</label>
            <input type="number" min="1" max="99" id="bbj-fin-<?php echo $player_id; ?>"
                   name="finish_place[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($finish_place !== null ? $finish_place : ''); ?>"
                   class="w-14 px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </div>
    </div>

    <!-- Row 2: 8 stat counts -->
    <div class="grid grid-cols-8 gap-2 mb-2 text-xs">
        <?php
        $stats = [
            ['label' => 'HoH',   'name' => "hoh_count[{$player_id}]",      'value' => $hoh_count],
            ['label' => 'PoV',   'name' => "veto_count[{$player_id}]",     'value' => $veto_count],
            ['label' => 'Nom',   'name' => "nom_count[{$player_id}]",      'value' => $nom_count],
            ['label' => 'Veto',  'name' => "veto_played[{$player_id}]",    'value' => $veto_played],
            ['label' => 'Misc',  'name' => "misc_count[{$player_id}]",     'value' => $misc_count],
            ['label' => 'Saved', 'name' => "saved_count[{$player_id}]",    'value' => $saved_count],
            ['label' => 'H/N',   'name' => "havenot_count[{$player_id}]",  'value' => $havenot_count],
            ['label' => 'Votes', 'name' => "votes_received[{$player_id}]", 'value' => $votes_received],
        ];
        foreach ($stats as $s):
        ?>
            <label class="flex flex-col items-center">
                <span class="text-stone-500 dark:text-slate-500"><?php echo esc_html($s['label']); ?></span>
                <input type="number" min="0" name="<?php echo esc_attr($s['name']); ?>"
                       value="<?php echo esc_attr((string) $s['value']); ?>"
                       class="w-full px-2 py-0.5 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-center">
            </label>
        <?php endforeach; ?>
    </div>

    <!-- Row 3: elimination controls + misc notes -->
    <div class="flex flex-wrap items-center gap-3 mb-2 text-xs">
        <span class="text-stone-500 dark:text-slate-500">Elim?</span>
        <label class="inline-flex items-center gap-1">
            <input type="radio" name="current_evicted[<?php echo $player_id; ?>]" value="0"
                   <?php checked(!$current_evicted); ?>>
            <span>N</span>
        </label>
        <label class="inline-flex items-center gap-1">
            <input type="radio" name="current_evicted[<?php echo $player_id; ?>]" value="1"
                   <?php checked($current_evicted); ?>>
            <span>Y</span>
        </label>

        <label class="inline-flex items-center gap-1">
            <input type="checkbox" name="current_jury[<?php echo $player_id; ?>]" value="1"
                   <?php checked($current_jury); ?>>
            <span>Jury</span>
        </label>

        <label class="inline-flex items-center gap-1">
            <span class="text-stone-500 dark:text-slate-500">Evicted:</span>
            <input type="date" name="evicted_date[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($evicted_date); ?>"
                   class="px-2 py-0.5 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </label>

        <label class="inline-flex items-center gap-1 flex-1 min-w-[140px]">
            <span class="text-stone-500 dark:text-slate-500">Misc:</span>
            <input type="text" name="misc_notes[<?php echo $player_id; ?>]"
                   value="<?php echo esc_attr($misc_notes); ?>"
                   placeholder="Custom status label"
                   class="w-full px-2 py-0.5 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
        </label>
    </div>

    <!-- Row 4: status toggle chips -->
    <div class="flex flex-wrap gap-1 text-xs">
        <span class="text-stone-500 dark:text-slate-500 mr-1">Status:</span>
        <?php
        $toggles = [
            ['label' => 'HoH',  'name' => "current_hoh[{$player_id}]",     'checked' => $current_hoh],
            ['label' => 'PoV',  'name' => "current_pov[{$player_id}]",     'checked' => $current_pov],
            ['label' => 'Nom',  'name' => "current_nom[{$player_id}]",     'checked' => $current_nom],
            ['label' => 'Safe', 'name' => "current_safe[{$player_id}]",    'checked' => $current_safe],
            ['label' => 'HN',   'name' => "current_havenot[{$player_id}]", 'checked' => $current_havenot],
            ['label' => 'Misc', 'name' => "current_misc[{$player_id}]",    'checked' => $current_misc],
        ];
        foreach ($toggles as $t):
        ?>
            <label class="<?php echo esc_attr($chip_base . $chip_checked_on); ?>">
                <input type="checkbox" name="<?php echo esc_attr($t['name']); ?>" value="1"
                       class="sr-only"
                       <?php checked($t['checked']); ?>>
                <span><?php echo esc_html($t['label']); ?></span>
            </label>
        <?php endforeach; ?>
    </div>

</div>
```

- [ ] **Step 2: Verify no syntax errors**

Load any admin page (e.g. `/admin/?tab=overview`). The page must still render. A syntax error in a partial won't trigger until the partial is loaded, but this at least verifies the plugin action-list is clean after Task 4.

Until Task 7 wires the partial into the tab body, the card itself isn't rendered anywhere.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/partials/spoiler-player-card.php
git commit -m "feat(admin): spoiler-bar player card partial with 20 editable fields"
```

---

### Task 6: Preview strip partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/spoiler-preview-strip.php`

**Context:** Thin wrapper around `bbj_render_spoiler_bar($season_id, true)` — the `true` flag bypasses the 300s cache. Shows admins exactly what the public will see once cache expires, reflecting the just-saved DB state.

- [ ] **Step 1: Create the file**

```php
<?php
/**
 * Admin shell — uncached spoiler-bar preview inside the Spoiler Bar edit tab.
 *
 * Receives $args['season_id'] — int, the season being edited.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season_id = isset($args['season_id']) ? (int) $args['season_id'] : 0;
if ($season_id <= 0) {
    return;
}
?>

<div class="mb-6">
    <div class="text-xs uppercase tracking-wide text-stone-500 dark:text-slate-500 mb-2 font-osw">
        Live preview (uncached)
    </div>
    <div class="p-3 border border-stone-200 dark:border-slate-700 bg-stone-50 dark:bg-slate-900/40 overflow-x-auto">
        <?php
        // Render the public spoiler bar for this specific season, bypassing the 300s cache.
        echo bbj_render_spoiler_bar($season_id, true);
        ?>
    </div>
    <p class="text-xs text-stone-500 dark:text-slate-500 mt-1">
        This preview rebuilds from the DB on every page load. Public visitors still see the cached version until the cache expires (or you click Purge Cache).
    </p>
</div>
```

- [ ] **Step 2: No smoke test at this step**

The partial isn't included anywhere yet (Task 7). Confirm the file parses by hitting any admin page.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/partials/spoiler-preview-strip.php
git commit -m "feat(admin): spoiler-bar uncached preview strip partial"
```

---

### Task 7: Spoiler tab body + wire it in + flip default tab

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-spoiler.php`
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php`

**Context:** The spoiler tab body renders the preview strip, then the two save / purge forms, then the player cards split into Active / Eliminated groups. Then we flip the tab nav: default tab goes from `'info'` to `'spoiler'`, and the `#spoiler` panel swaps its stub for the real partial.

- [ ] **Step 1: Create `seasons-edit-spoiler.php`**

```php
<?php
/**
 * Admin shell — Spoiler Bar tab body.
 *
 * Receives $args['season'] — the wp_bbj_seasons row object.
 * Receives $args['season_id'] — int.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season    = $args['season'];
$season_id = (int) $args['season_id'];

// Pull roster
$players = function_exists('bbj_v2_get_season_players')
    ? bbj_v2_get_season_players($season_id, 'bbj_v2_profile_image')
    : [];

// Split into Active / Eliminated (pre-sort by name / finish_place + date respectively)
$active = [];
$eliminated = [];
foreach ($players as $p) {
    if (!empty($p['current_evicted'])) {
        $eliminated[] = $p;
    } else {
        $active[] = $p;
    }
}

// Active: sort by first_name
usort($active, function ($a, $b) {
    return strcasecmp(
        trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')),
        trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''))
    );
});

// Eliminated: finish_place ASC (NULL last), then evicted_date DESC
usort($eliminated, function ($a, $b) {
    $fa = (isset($a['bbj_finish_place']) && $a['bbj_finish_place'] !== null && $a['bbj_finish_place'] !== '')
        ? (int) $a['bbj_finish_place'] : null;
    $fb = (isset($b['bbj_finish_place']) && $b['bbj_finish_place'] !== null && $b['bbj_finish_place'] !== '')
        ? (int) $b['bbj_finish_place'] : null;
    if ($fa !== null && $fb !== null && $fa !== $fb) return $fa <=> $fb;
    if ($fa !== null && $fb === null) return -1;
    if ($fa === null && $fb !== null) return 1;

    $da = strtotime($a['bbj_evicted_date'] ?? '') ?: 0;
    $db = strtotime($b['bbj_evicted_date'] ?? '') ?: 0;
    return $db <=> $da;
});

$active_count = count($active);
$eliminated_count = count($eliminated);
$roster_is_empty = $active_count === 0 && $eliminated_count === 0;

// Notices
$saved   = !empty($_GET['updated']);
$purged  = !empty($_GET['purged']);
?>

<?php get_template_part('template-parts/admin/partials/spoiler-preview-strip', null, [
    'season_id' => $season_id,
]); ?>

<?php if ($saved): ?>
    <div class="mb-4 p-3 bg-green-50 text-green-800 border border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-800"
         data-bbj-autodismiss="3000">
        Spoiler bar saved.
    </div>
<?php endif; ?>
<?php if ($purged): ?>
    <div class="mb-4 p-3 bg-blue-50 text-blue-800 border border-blue-200 dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-800"
         data-bbj-autodismiss="3000">
        Cache purged.
    </div>
<?php endif; ?>

<!-- Purge Cache form (separate, tiny) -->
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
    <?php wp_nonce_field('bbj_v2_purge_season_cache_action', 'bbj_v2_purge_season_cache_nonce'); ?>
    <input type="hidden" name="action" value="bbj_v2_purge_season_cache">
    <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id); ?>">
    <button type="submit"
            class="float-right mb-2 px-3 py-1.5 text-xs font-semibold text-stone-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-stone-300 dark:border-slate-700 hover:bg-stone-50 dark:hover:bg-slate-700 transition-colors">
        Purge Cache
    </button>
</form>

<!-- Save form: wraps all player cards -->
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="clear-both">
    <?php wp_nonce_field('add_player_action', 'add_player_nonce'); ?>
    <input type="hidden" name="action" value="bbj_v2_update_season">
    <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id); ?>">

    <?php if ($roster_is_empty): ?>
        <div class="p-6 bg-stone-50 dark:bg-slate-900/40 border border-dashed border-stone-300 dark:border-slate-700 text-center">
            <p class="text-stone-600 dark:text-slate-400">No players yet.</p>
            <p class="text-sm text-stone-500 dark:text-slate-500 mt-1">Add players from the Season Info tab.</p>
        </div>
    <?php else: ?>

        <?php if ($active_count > 0): ?>
            <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mt-4 mb-2">
                Active (<?php echo $active_count; ?>)
            </h2>
            <?php foreach ($active as $player):
                get_template_part('template-parts/admin/partials/spoiler-player-card', null, [
                    'player' => $player,
                    'season' => $season,
                ]);
            endforeach; ?>
        <?php endif; ?>

        <?php if ($eliminated_count > 0): ?>
            <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mt-6 mb-2">
                Eliminated (<?php echo $eliminated_count; ?>)
            </h2>
            <?php foreach ($eliminated as $player):
                get_template_part('template-parts/admin/partials/spoiler-player-card', null, [
                    'player' => $player,
                    'season' => $season,
                ]);
            endforeach; ?>
        <?php endif; ?>

        <div class="mt-6 flex items-center justify-end gap-3">
            <button type="submit"
                    class="px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors">
                Save Spoiler Bar
            </button>
        </div>

    <?php endif; ?>
</form>
```

- [ ] **Step 2: Flip the default tab + swap the stub in `seasons-edit-tabs.php`**

In `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php`, find the `#spoiler` panel block at lines 39-43:

```php
<div data-bbj-tab-panel="spoiler" class="bbj-tab-panel">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Spoiler Bar',
    ]); ?>
</div>
```

Replace with:

```php
<div data-bbj-tab-panel="spoiler" class="bbj-tab-panel">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-spoiler', null, [
        'season'    => $season,
        'season_id' => $season_id,
    ]); ?>
</div>
```

Then find the `DEFAULT_TAB` JS constant at line 61:

```javascript
    var DEFAULT_TAB = 'info'; // Pre-sprint default — Spoiler Bar is stubbed.
```

Replace with:

```javascript
    var DEFAULT_TAB = 'spoiler'; // Spoiler Bar is the primary reason to open this page.
```

Also update the `#spoiler` panel's initial visibility: the tab panels list at lines 39-56 has `spoiler` WITHOUT `hidden` and `info` / `photos` WITH `hidden`. That's still correct because the JS re-renders on initial load and sets visibility based on the default tab. No change needed.

- [ ] **Step 3: Smoke test the end-to-end**

Visit `http://bbj.localhost/admin/?tab=seasons&edit=<current_bb_season_id>`.

Expected:
- Spoiler Bar tab is the ACTIVE tab (underline under it, panel visible).
- Hash in URL updates to `#spoiler` on first interaction (the IIFE sets tab on load).
- Live preview strip renders at the top showing the current spoiler bar.
- Below the preview: "Purge Cache" button (right-aligned).
- Below that: "Active (N)" heading and card stack, then "Eliminated (M)" heading and card stack (sorted correctly).
- Each card shows: avatar, full name, Fin# input, 8 stat count inputs, Elim radios, Jury checkbox, Evicted date, Misc text, 6 status toggle chips.
- Save button at bottom.

Interact:
- Change a player's HoH count, toggle their `current_hoh` chip ON. Click Save.
- URL becomes `?tab=seasons&edit=<id>&updated=1#spoiler`.
- Green "Spoiler bar saved" notice appears and auto-dismisses in ~3s.
- The preview strip at the top now reflects the change (because `skip_cache=true`).
- The homepage spoiler bar may still show stale data if visited within 300s of the previous cache write — that's expected, use Purge Cache to force it.

Click Purge Cache:
- URL becomes `?tab=seasons&edit=<id>&purged=1#spoiler`.
- Blue "Cache purged" notice appears.
- Visit homepage, confirm the spoiler bar now reflects the latest state.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-spoiler.php wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php
git commit -m "feat(admin): Spoiler Bar tab body + flip default tab to #spoiler"
```

---

### Task 8: Final full-flow smoke test + roadmap update

**Files:** (verification only until Step 3)
- Modify: `.claude/project/roadmap.md`

- [ ] **Step 1: Run the full spec smoke-test checklist**

Work through each scenario from the spec's Testing plan section. Pick a test season (BB26 works; has roster). For each:

1. **Schema column present.** Run `SHOW COLUMNS FROM wp_bbj_v2_player_season WHERE Field = 'bbj_finish_place';` — confirms the TINYINT(3) UNSIGNED NULL column.
2. **Default tab flipped.** `/admin/?tab=seasons&edit=<BB26_id>` lands on `#spoiler`, preview renders, cards render.
3. **Single-field edit.** Change a player's `finish_place` from blank to `3`. Save. Notice appears, `?updated=1` in URL, field persists, DB confirms.
4. **Multi-field edit.** Toggle a status chip + bump a count. Save. Both persist.
5. **Eliminate a player.** Flip a player Elim=Y, set evicted_date, set finish_place=1, check Jury. Save. Card moves to Eliminated group and sorts first among eliminated.
6. **Finish-place sort.** Set two eliminated players to same evicted_date but finish_place 4 and 5. Homepage spoiler bar renders them in `4 → 5` order.
7. **Purge Cache button.** Click. Notice, no DB change, homepage reflects latest.
8. **Preview freshness.** Edit + save. Preview strip on tab updates immediately (bypass cache).
9. **Non-admin redirect.** Log out, hit the edit URL → redirects to wp-login.php.
10. **Public regression.** Homepage spoiler bar for BB26 (no finish_place values set) still sorts by evicted_date — unchanged behavior.

If any scenario fails, stop and investigate before proceeding.

- [ ] **Step 2: Visual QA — flat-editorial consistency**

Compare with the existing `/admin/?tab=overview` and `/admin/?tab=seasons` list:
- Same `bg-white` + `border-stone-200` treatment on outer containers
- Same Oswald section headings (Active/Eliminated)
- Same flat edges (no rounded corners on cards)
- Toggle chips should work without JS (click label, state toggles via `has-[:checked]:*`)

- [ ] **Step 3: Update the roadmap**

In `.claude/project/roadmap.md`:

(a) Bump the "Last updated" line:

Find:

```
> Last updated: 2026-04-21
```

Replace with:

```
> Last updated: 2026-04-22
```

(b) Add a bullet to the "What's shipped" section. Find the last bullet (the pre-sprint Seasons admin pane, added recently):

```markdown
- **Seasons admin pane** (`/admin?tab=seasons`) — flat list with status badges + current-season accent, Add Season draft flow, edit page shell with 3-tab layout (Spoiler Bar / Info / Photos); Season Info tab live for BasicInfo + Dates; Images / Winners / Roster stubbed for Sprint A 🟡
```

Insert this new bullet directly below it:

```markdown
- **Spoiler Bar editor** (`/admin?tab=seasons&edit=<id>#spoiler`) — card-per-player UI on the default edit tab; adds `bbj_finish_place` column for correct double-eviction sort; uncached preview strip; Purge Cache button; reuses existing `bbj_v2_update_season()` handler
```

(c) In the "Sprint roadmap" section, strike through the Spoiler Bar portion of Sprint A, since it's shipped now. Find the Sprint A block near the top of the roadmap. It currently reads as an open sprint. Update its header line from `### Sprint A — Site Settings + Spoiler Bar Manager ⬜` to reflect partial progress — change `⬜` to `🟡` and add a note in the scope section saying the Spoiler Bar tab is now shipped.

- [ ] **Step 4: Commit**

```bash
git add .claude/project/roadmap.md
git commit -m "docs(roadmap): mark Spoiler Bar editor shipped on staging; Sprint A partial"
```

---

## Self-review notes

- **Spec coverage:** every spec section has a task. Schema (Task 1), public shortcode changes (Task 2), save handler (Task 3), purge handler (Task 4), player card (Task 5), preview strip (Task 6), tab body + default-tab flip (Task 7), QA + roadmap (Task 8).
- **Type consistency:** `$finish_place` is always treated as int-or-null, never empty string. In the DB it's `TINYINT(3) UNSIGNED DEFAULT NULL`. The handler stores `(int) value` when `> 0` else PHP `null` (which becomes SQL NULL). The form input uses `min="1"`.
- **Field name consistency:** all 20 form field names in Task 5 match what Task 3's handler reads. Double-checked against the spec table.
- **`bbj_render_spoiler_bar()` signature change safety:** the new params both default to values that preserve existing behavior (null season_id → fall back to option lookup; skip_cache=false → current caching). The single existing call from the homepage (`[bbj_spoiler_bar]` shortcode) passes no args, so no regression.
- **Out of scope (confirmed, per spec):** roster add/remove, dirty tracking, client-side live preview, finish-place backfill, nonce rename, validation of finish_place uniqueness, mobile layout.
