# Admin Settings Pane Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the old `/wp-admin/admin.php?page=bbj-v2-settings` into the new admin shell at `/admin?tab=settings`, adding a read-only info card with a quick-jump link to the active season's spoiler bar.

**Architecture:** New pane template in the theme, new save handler in the plugin. Reuses existing helpers (`bbj_v2_get_seasons`, `bbj_v2_get_season_by_id`, `bbj_spoiler_bar_bust_cache`). Dispatcher in `page-admin.php` grows one `elseif` branch.

**Tech Stack:** WordPress 6.x, PHP 8, Tailwind CSS 3.4.

**Testing:** Manual smoke-testing on `http://bbj.localhost/`.

---

## Spec reference

`docs/superpowers/specs/2026-04-22-admin-settings-pane-design.md`

---

## File structure

**Create:**
- `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-settings.php` — the pane
- `wp-content/plugins/bbj-v2/includes/Actions/form-submits/set-current-season.php` — save handler

**Modify:**
- `wp-content/themes/bbj-v2-theme/page-admin.php` — dispatcher picks up `tab=settings`
- `wp-content/plugins/bbj-v2/includes/Actions/action-list.php` — register the admin-post action

---

### Task 1: Save handler + registration

**Files:**
- Create: `wp-content/plugins/bbj-v2/includes/Actions/form-submits/set-current-season.php`
- Modify: `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`

**Context:** Server side goes in first so the pane can POST to a working endpoint immediately.

- [ ] **Step 1: Write the handler**

Create `wp-content/plugins/bbj-v2/includes/Actions/form-submits/set-current-season.php` with this exact content:

```php
<?php
/**
 * Handles the "Set current season" form on /admin?tab=settings.
 * Writes wp_options['bbj_v2_current_season'] and busts spoiler-bar cache
 * for both the previously-active season and the newly-selected one.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_set_current_season() {
    // 1. Security + capability
    if (
        empty($_POST['bbj_v2_set_current_season_nonce']) ||
        ! wp_verify_nonce($_POST['bbj_v2_set_current_season_nonce'], 'bbj_v2_set_current_season_action') ||
        ! current_user_can('manage_options')
    ) {
        wp_die('Permission check failed');
    }

    $new_id = isset($_POST['bbj_v2_season']) ? absint($_POST['bbj_v2_season']) : 0;
    if ($new_id <= 0) {
        wp_die('Invalid season selection');
    }

    // Validate the id points at a real season row
    $season = bbj_v2_get_season_by_id($new_id);
    if (!$season) {
        wp_die('Season not found');
    }

    // Capture the old id before the switch so we can bust its cache too
    $old_id = (int) get_option('bbj_v2_current_season', 0);

    update_option('bbj_v2_current_season', $new_id);

    // Defensive cache bust: old and new season ids both get their spoiler-bar
    // cache keys wiped. The cache is season-keyed, so old cached HTML is
    // harmless once the option changes, but busting cleanly is cheap.
    if ($old_id > 0 && $old_id !== $new_id) {
        bbj_spoiler_bar_bust_cache($old_id);
    }
    bbj_spoiler_bar_bust_cache($new_id);

    // Redirect back to the settings pane with a success flag
    $redirect = add_query_arg(
        ['tab' => 'settings', 'updated' => 1],
        home_url('/admin/')
    );
    wp_safe_redirect($redirect);
    exit;
}
```

- [ ] **Step 2: Register the action**

In `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`, find the existing `BBJ_load_purge_season_cache_handler` block (added in the prior sprint — it's the most-recently-added handler near the bottom of the registrations):

```php
// Purge spoiler-bar cache for a single season (no DB writes)
add_action('admin_post_bbj_v2_purge_season_cache', 'BBJ_load_purge_season_cache_handler');

function BBJ_load_purge_season_cache_handler() {
    require_once BBJ_FORM_SUBMITS . 'purge-season-cache.php';
    bbj_v2_purge_season_cache();
}
```

Insert this block immediately after its closing `}`:

```php
// Set the current season (global option bbj_v2_current_season)
add_action('admin_post_bbj_v2_set_current_season', 'BBJ_load_set_current_season_handler');

function BBJ_load_set_current_season_handler() {
    require_once BBJ_FORM_SUBMITS . 'set-current-season.php';
    bbj_v2_set_current_season();
}
```

Use Edit with exact string match. Do NOT rewrite the whole file.

- [ ] **Step 3: Syntax + registration checks**

```bash
php -l wp-content/plugins/bbj-v2/includes/Actions/form-submits/set-current-season.php
php -l wp-content/plugins/bbj-v2/includes/Actions/action-list.php
```

Both must report `No syntax errors detected`.

Confirm the action is registered:

```bash
php -r "define('WP_USE_THEMES', false); require 'wp-load.php'; echo has_action('admin_post_bbj_v2_set_current_season') ? \"OK\n\" : \"MISSING\n\";" 2>&1 | tail -3
```

Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Actions/form-submits/set-current-season.php wp-content/plugins/bbj-v2/includes/Actions/action-list.php
git commit -m "feat(plugin): add bbj_v2_set_current_season handler"
```

Do NOT push.

---

### Task 2: Settings pane template + dispatcher wire-in

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-settings.php`
- Modify: `wp-content/themes/bbj-v2-theme/page-admin.php`

- [ ] **Step 1: Create the pane template**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-settings.php` with this exact content:

```php
<?php
/**
 * Admin shell — Settings pane.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_season_id = (int) get_option('bbj_v2_current_season', 0);
$seasons           = function_exists('bbj_v2_get_seasons')
    ? bbj_v2_get_seasons('start_date', 'DESC')
    : [];

$current_season = $current_season_id > 0 && function_exists('bbj_v2_get_season_by_id')
    ? bbj_v2_get_season_by_id($current_season_id)
    : null;

// Extract display values for the info card
$display_name  = '';
$display_abbr  = '';
$display_dates = '';
$edit_url      = '';

if (is_array($current_season)) {
    $display_name = (string) ($current_season['full_name'] ?? '');
    $display_abbr = (string) ($current_season['abbreviation'] ?? '');
    $start_ts     = !empty($current_season['start_date']) ? strtotime($current_season['start_date']) : 0;
    $end_ts       = !empty($current_season['end_date'])   ? strtotime($current_season['end_date'])   : 0;

    if ($start_ts && $end_ts) {
        $display_dates = date_i18n('M j', $start_ts) . ' – ' . date_i18n('M j, Y', $end_ts);
    } elseif ($start_ts) {
        $display_dates = date_i18n('M j, Y', $start_ts) . ' – TBD';
    }

    $edit_url = esc_url(
        add_query_arg(
            ['tab' => 'seasons', 'edit' => $current_season_id],
            home_url('/admin/')
        ) . '#spoiler'
    );
}

$saved = !empty($_GET['updated']);
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        Settings
    </h1>
    <p class="text-sm text-stone-600 dark:text-slate-400 mb-6">
        Site-wide configuration for Big Brother Junkies.
    </p>

    <?php if ($saved): ?>
        <div class="mb-4 p-3 bg-green-50 text-green-800 border border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-800"
             data-bbj-autodismiss="3000">
            Current season updated.
        </div>
    <?php endif; ?>

    <!-- Current season info card -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Current Season
    </h2>
    <div class="p-4 mb-6 bg-stone-50 dark:bg-slate-900/40 border border-stone-200 dark:border-slate-700">
        <?php if (is_array($current_season)): ?>
            <div class="flex items-center gap-2 mb-1">
                <span class="font-semibold text-stone-800 dark:text-slate-200">
                    <?php echo esc_html($display_name !== '' ? $display_name : '(Untitled season)'); ?>
                </span>
                <?php if ($display_abbr !== ''): ?>
                    <span class="px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400">
                        <?php echo esc_html($display_abbr); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($display_dates !== ''): ?>
                <div class="text-sm text-stone-600 dark:text-slate-400 mb-3">
                    <?php echo esc_html($display_dates); ?>
                </div>
            <?php endif; ?>
            <?php if ($edit_url !== ''): ?>
                <a href="<?php echo $edit_url; ?>"
                   class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-500 hover:bg-primary-600 text-white transition-colors">
                    Edit spoiler bar →
                </a>
            <?php endif; ?>
        <?php elseif ($current_season_id > 0): ?>
            <p class="text-stone-600 dark:text-slate-400">
                (Season id <?php echo (int) $current_season_id; ?> not found — pick a valid season below.)
            </p>
        <?php else: ?>
            <p class="text-stone-600 dark:text-slate-400">No current season selected yet.</p>
        <?php endif; ?>
    </div>

    <!-- Change current season form -->
    <h2 class="font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500 mb-2">
        Change Current Season
    </h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="flex items-center gap-3">
        <?php wp_nonce_field('bbj_v2_set_current_season_action', 'bbj_v2_set_current_season_nonce'); ?>
        <input type="hidden" name="action" value="bbj_v2_set_current_season">

        <select name="bbj_v2_season"
                class="px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm min-w-[220px]"
                <?php echo empty($seasons) ? 'disabled' : ''; ?>>
            <?php if (empty($seasons)): ?>
                <option value="">No seasons available</option>
            <?php else: ?>
                <?php foreach ($seasons as $s):
                    $row = (object) $s; // ARRAY_A → object for consistent access
                    $sid = (int) ($row->id ?? 0);
                    $nm  = (string) ($row->full_name ?? '');
                    if ($sid <= 0) continue;
                ?>
                    <option value="<?php echo esc_attr($sid); ?>"
                            <?php selected($sid === $current_season_id); ?>>
                        <?php echo esc_html($nm !== '' ? $nm : '(Untitled draft)'); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>

        <button type="submit"
                class="px-5 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold text-sm transition-colors"
                <?php echo empty($seasons) ? 'disabled' : ''; ?>>
            Save
        </button>
    </form>

    <!-- Tiny JS: auto-dismiss the success notice -->
    <script>
    (function () {
        document.querySelectorAll('[data-bbj-autodismiss]').forEach(function (el) {
            var delay = parseInt(el.getAttribute('data-bbj-autodismiss'), 10);
            if (!delay) return;
            setTimeout(function () {
                el.style.transition = 'opacity 0.3s';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 300);
            }, delay);
        });
    })();
    </script>

</section>
```

- [ ] **Step 2: Extend the dispatcher in `page-admin.php`**

In `wp-content/themes/bbj-v2-theme/page-admin.php`, find this existing block (lines 30-47):

```php
            <?php if ($active_tab === 'overview'): ?>
                <?php get_template_part('template-parts/admin/pane-overview'); ?>
            <?php elseif ($active_tab === 'seasons'): ?>
                <?php
                $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
                if ($edit_id > 0) {
                    get_template_part('template-parts/admin/pane-seasons-edit', null, [
                        'season_id' => $edit_id,
                    ]);
                } else {
                    get_template_part('template-parts/admin/pane-seasons');
                }
                ?>
            <?php else: ?>
                <?php get_template_part('template-parts/admin/pane-stub', null, [
                    'tab' => $active_tab,
                ]); ?>
            <?php endif; ?>
```

Replace with:

```php
            <?php if ($active_tab === 'overview'): ?>
                <?php get_template_part('template-parts/admin/pane-overview'); ?>
            <?php elseif ($active_tab === 'seasons'): ?>
                <?php
                $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
                if ($edit_id > 0) {
                    get_template_part('template-parts/admin/pane-seasons-edit', null, [
                        'season_id' => $edit_id,
                    ]);
                } else {
                    get_template_part('template-parts/admin/pane-seasons');
                }
                ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php get_template_part('template-parts/admin/pane-settings'); ?>
            <?php else: ?>
                <?php get_template_part('template-parts/admin/pane-stub', null, [
                    'tab' => $active_tab,
                ]); ?>
            <?php endif; ?>
```

Use Edit with exact string match.

- [ ] **Step 3: PHP syntax check**

```bash
php -l wp-content/themes/bbj-v2-theme/template-parts/admin/pane-settings.php
php -l wp-content/themes/bbj-v2-theme/page-admin.php
```

Both must report `No syntax errors detected`.

- [ ] **Step 4: Data smoke test via PHP CLI**

```bash
php -r "define('WP_USE_THEMES', false); require 'wp-load.php'; \$seasons = bbj_v2_get_seasons('start_date', 'DESC'); echo 'Seasons: ' . count(\$seasons) . \"\n\"; \$cid = (int) get_option('bbj_v2_current_season', 0); echo 'Current: ' . \$cid . \"\n\"; if (\$cid > 0) { \$s = bbj_v2_get_season_by_id(\$cid); echo 'Name: ' . (is_array(\$s) ? \$s['full_name'] : 'NULL') . \"\n\"; }" 2>&1 | tail -5
```

Expected: a positive seasons count, the current season id (likely 60026 for BB26), and its name.

- [ ] **Step 5: Rebuild Tailwind CSS**

This commit introduces a small number of new Tailwind class combos (the info card's bg-stone-50, the primary-button hover on the Edit-spoiler-bar link). Most are already in the compiled CSS from the prior sprint, but rebuild to be safe:

```bash
cd wp-content/themes/bbj-v2-theme && npm run build 2>&1 | tail -10
```

If npm output contains any error keyword (not just warning), stop and report BLOCKED.

If the compiled CSS changed, stage the build artifact:

```bash
git status --short wp-content/themes/bbj-v2-theme/
```

If any CSS output file is listed, include it in the commit at Step 6. If nothing changed, skip.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-settings.php wp-content/themes/bbj-v2-theme/page-admin.php
# Also stage the Tailwind rebuild output if it changed:
git add wp-content/themes/bbj-v2-theme/ 2>/dev/null
git commit -m "feat(admin): settings pane at /admin?tab=settings"
```

Do NOT push.

---

### Task 3: Roadmap update

**Files:**
- Modify: `.claude/project/roadmap.md`

- [ ] **Step 1: Bump the Last-updated line**

In `.claude/project/roadmap.md`, the date at the top probably already says `2026-04-22` from the prior sprint — if so, skip this step. Otherwise:

Find:

```
> Last updated: 2026-04-21
```

(or whatever the prior date is) and replace with:

```
> Last updated: 2026-04-22
```

- [ ] **Step 2: Add a "What's shipped" bullet**

Find the most-recently-added bullet at the bottom of the "What's shipped" list (likely the Spoiler Bar editor line from the prior sprint):

```markdown
- **Spoiler Bar editor** (`/admin?tab=seasons&edit=<id>#spoiler`) — card-per-player UI on the default edit tab; adds `bbj_finish_place` column for correct double-eviction sort; uncached preview strip; Purge Cache button; reuses existing `bbj_v2_update_season()` handler
```

Insert this new bullet directly below it:

```markdown
- **Settings pane** (`/admin?tab=settings`) — current-season dropdown + info card with quick-jump to the active season's spoiler bar; cache-bust on switch. Ports the old `/wp-admin/admin.php?page=bbj-v2-settings` page into the new admin shell.
```

- [ ] **Step 3: Flip Sprint A from 🟡 → ✅**

Find:

```markdown
### Sprint A — Site Settings + Spoiler Bar Manager 🟡
```

Replace with:

```markdown
### Sprint A — Site Settings + Spoiler Bar Manager ✅
```

Also update the Scope block directly below it. Find:

```markdown
**Scope:**
- ~~`/admin?tab=spoiler-bar` pane~~ — **shipped as the `#spoiler` tab on `/admin?tab=seasons&edit=<id>`** (card-per-player UI, bbj_finish_place sort, uncached preview, Purge Cache button). See `docs/superpowers/specs/2026-04-22-spoiler-bar-editor-design.md`.
- `/admin?tab=settings` pane ⬜ — `bbj_v2_current_season` dropdown, `bbj_v2_season_active` toggle, future knobs (still to ship)
- Wire `bbj_v2_get_spoiler_bar()` to return live data so `front-page.php` houseboard flips from placeholder to real (still to ship — depends on Settings pane being wired + editor being used for BB27)
```

Replace with:

```markdown
**Scope shipped:**
- ~~`/admin?tab=spoiler-bar` pane~~ — shipped as the `#spoiler` tab on `/admin?tab=seasons&edit=<id>` (card-per-player UI, `bbj_finish_place` sort, uncached preview, Purge Cache button). See `docs/superpowers/specs/2026-04-22-spoiler-bar-editor-design.md`.
- `/admin?tab=settings` pane ✅ — current-season dropdown, info card with quick-jump to spoiler bar. `bbj_v2_season_active` toggle dropped (no consumers). See `docs/superpowers/specs/2026-04-22-admin-settings-pane-design.md`.

**Follow-up work (not blocking Sprint A's done state):**
- Wire `bbj_v2_get_spoiler_bar()` to return live data so `front-page.php` houseboard flips from placeholder to real — depends on BB27 cast actually being entered into the Spoiler Bar editor (content work, not code).
```

- [ ] **Step 4: Commit**

```bash
git add .claude/project/roadmap.md
git commit -m "docs(roadmap): Settings pane shipped; Sprint A complete"
```

Do NOT push.

---

## Self-review notes

- **Spec coverage:** Every spec requirement has a task.
  - Handler (Task 1): nonce, capability, validation, update_option, cache-bust for old + new, redirect
  - Pane template (Task 2): info card, empty/missing-season states, dropdown populated from `bbj_v2_get_seasons('start_date', 'DESC')`, save form with correct action/nonce, `?updated=1` notice, `ABSPATH` guard, Oswald section headings, flat-editorial wrapper
  - Dispatcher (Task 2): new `elseif` for `tab=settings`
  - Roadmap (Task 3): Sprint A closed out
- **Type consistency:** `$current_season_id` is always int via `(int) get_option(...)`. `$current_season` is array-or-null (matches `bbj_v2_get_season_by_id()`'s ARRAY_A return). `$seasons` is ARRAY_A array (cast to object in the loop for consistent `->id` access, matching the seasons-edit pattern).
- **Field name:** `bbj_v2_season` matches the old wp-admin page's POST key — intentional, keeps terminology consistent.
- **Nonce naming:** new `bbj_v2_set_current_season_nonce` / `bbj_v2_set_current_season_action` — semantically clear (vs the pre-sprint's `add_player_*` oddity).
- **Out of scope (confirmed per spec):** `bbj_v2_season_active` toggle, future knobs, date-range validation, preserving the old wp-admin page (left live for rollback).
