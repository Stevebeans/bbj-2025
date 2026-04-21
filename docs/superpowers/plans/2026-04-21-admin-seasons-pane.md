# Admin Seasons Pane — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `/admin?tab=seasons` with a seasons list + an edit page skeleton (3-tab shell, Info tab live with BasicInfo + Dates; Spoiler Bar, Photos, and the rest of Info are in-tab stubs). Replaces the current "Coming soon" stub. Unblocks Sprint A.

**Architecture:** Templates in the theme, write-handlers in the bbj-v2 plugin. Pure PHP + tiny inline JS for tab hashchange. Form POST → `admin-post.php` with WP nonces. No REST, no React, no schema changes. Reuses existing `bbj_v2_edit_season_info()` handler (after fixing a pre-existing post-title-sync bug). Adds one new handler: `bbj_v2_create_season()`.

**Tech Stack:** WordPress 6.x, PHP 8, Tailwind CSS 3.4, Flowbite, vanilla JS.

**Testing:** No automated test harness in this repo. Verification is manual smoke-testing on the local staging site at `http://bbj.localhost/`. Each task ends with specific URLs to hit and expected behavior.

---

## Spec reference

`docs/superpowers/specs/2026-04-21-admin-seasons-pane-design.md`

---

## File structure

**Create (theme):**
- `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons.php` — list view
- `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php` — edit view
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-list-row.php` — one row in list
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php` — tab nav + hashchange JS
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-info.php` — Info tab body
- `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-stub.php` — reusable "Coming in Sprint A" in-tab stub

**Create (plugin):**
- `wp-content/plugins/bbj-v2/includes/Actions/form-submits/create-season.php` — create-season handler

**Modify:**
- `wp-content/themes/bbj-v2-theme/page-admin.php` — extend dispatcher (seasons list vs edit)
- `wp-content/plugins/bbj-v2/includes/Actions/action-list.php` — register `admin_post_bbj_v2_create_season`
- `wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php` — fix post-title-sync bug + publish draft on first save

---

### Task 1: Extend admin dispatcher for seasons tab

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/page-admin.php`

- [ ] **Step 1: Add a placeholder `pane-seasons.php` so the dispatcher has something to load**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons.php` with minimal content (fleshed out in Task 2):

```php
<?php
/**
 * Admin shell — Seasons pane (list view).
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        Seasons
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        Placeholder — list renders next task.
    </p>
</section>
```

- [ ] **Step 2: Extend the dispatcher in `page-admin.php`**

Current dispatcher (lines 30-36):

```php
<?php if ($active_tab === 'overview'): ?>
    <?php get_template_part('template-parts/admin/pane-overview'); ?>
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
<?php else: ?>
    <?php get_template_part('template-parts/admin/pane-stub', null, [
        'tab' => $active_tab,
    ]); ?>
<?php endif; ?>
```

- [ ] **Step 3: Smoke test — list pane loads, edit pane 404s gracefully**

- Visit `http://bbj.localhost/admin/?tab=seasons` as an admin user.
  - Expected: the placeholder "Seasons / Placeholder — list renders next task." card renders. Sidebar shows `Seasons` highlighted.
- Visit `http://bbj.localhost/admin/?tab=seasons&edit=999` (edit pane doesn't exist yet).
  - Expected: WordPress shows a PHP notice or blank slot where the partial would be — this is fine, we build `pane-seasons-edit.php` in Task 4.
- Visit `http://bbj.localhost/admin/?tab=overview` and `?tab=feed-updates`.
  - Expected: overview + other stubs still render normally (no regressions).

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/page-admin.php wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons.php
git commit -m "feat(admin): dispatch seasons tab to list / edit panes"
```

---

### Task 2: Seasons list page

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-list-row.php`

- [ ] **Step 1: Write the list-row partial**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-list-row.php`:

```php
<?php
/**
 * Admin shell — single season row in the seasons list.
 * Receives $args['season'] — a wp_bbj_seasons row (object).
 * Receives $args['current_season_id'] — int, the active season id.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season            = $args['season'];
$current_season_id = (int) ($args['current_season_id'] ?? 0);

$season_id   = (int) $season->id;
$is_current  = $season_id === $current_season_id;
$full_name   = (string) ($season->full_name ?? '');
$abbrev      = (string) ($season->abbreviation ?? '');
$start_date  = (string) ($season->start_date ?? '');
$end_date    = (string) ($season->end_date ?? '');
$season_num  = (string) ($season->season_number ?? '');
$winner_id   = (int) ($season->season_winner ?? 0);

// Compute status
$today_ts = strtotime(current_time('Y-m-d'));
$start_ts = $start_date ? strtotime($start_date) : 0;
$end_ts   = $end_date   ? strtotime($end_date)   : 0;

if ($full_name === '') {
    $status = ['label' => 'Draft',     'classes' => 'bg-red-100 text-red-700 border-red-200'];
} elseif ($is_current) {
    $status = ['label' => 'Current',   'classes' => 'bg-yellow-100 text-yellow-900 border-yellow-200'];
} elseif ($start_ts && $start_ts > $today_ts) {
    $status = ['label' => 'Upcoming',  'classes' => 'bg-blue-100 text-blue-700 border-blue-200'];
} elseif ($end_ts && $end_ts < $today_ts) {
    $status = ['label' => 'Completed', 'classes' => 'bg-stone-100 text-stone-600 border-stone-200'];
} else {
    $status = ['label' => 'Active',    'classes' => 'bg-green-100 text-green-700 border-green-200'];
}

// Winner name
$winner_name = $winner_id ? (get_the_title($winner_id) ?: '—') : '—';

// Dates — "Jul 17 – Oct 13, 2024" or "Jul 10 – TBD" or "—"
$date_display = '—';
if ($start_ts && $end_ts) {
    $date_display = date_i18n('M j', $start_ts) . ' – ' . date_i18n('M j, Y', $end_ts);
} elseif ($start_ts) {
    $date_display = date_i18n('M j, Y', $start_ts) . ' – TBD';
}

$edit_url = esc_url(add_query_arg(['tab' => 'seasons', 'edit' => $season_id], home_url('/admin/')));

// Current-season row gets a yellow left-border accent
$row_classes = 'group border-b border-stone-200 dark:border-slate-800 hover:bg-stone-50 dark:hover:bg-slate-800/40 transition-colors';
if ($is_current) {
    $row_classes .= ' border-l-4 border-l-secondary-500';
}
?>

<tr class="<?php echo esc_attr($row_classes); ?>">
    <td class="px-4 py-3">
        <a href="<?php echo $edit_url; ?>" class="font-semibold text-primary-500 hover:text-primary-hard dark:text-secondary-500">
            <?php echo esc_html($full_name !== '' ? $full_name : '(Untitled draft)'); ?>
        </a>
        <?php if ($abbrev !== ''): ?>
            <span class="ml-2 inline-block px-1.5 py-0.5 text-xs font-mono bg-stone-100 text-stone-600 border border-stone-200 dark:bg-slate-800 dark:text-slate-400">
                <?php echo esc_html($abbrev); ?>
            </span>
        <?php endif; ?>
    </td>
    <td class="px-4 py-3 text-stone-600 dark:text-slate-400">
        <?php echo esc_html($season_num); ?>
    </td>
    <td class="px-4 py-3 text-stone-600 dark:text-slate-400">
        <?php echo esc_html($date_display); ?>
    </td>
    <td class="px-4 py-3 text-stone-600 dark:text-slate-400">
        <?php echo esc_html($winner_name); ?>
    </td>
    <td class="px-4 py-3">
        <span class="inline-block px-2 py-0.5 text-xs font-semibold border <?php echo esc_attr($status['classes']); ?>">
            <?php echo esc_html($status['label']); ?>
        </span>
    </td>
</tr>
```

- [ ] **Step 2: Rewrite `pane-seasons.php` with the full list**

Replace the placeholder contents of `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons.php`:

```php
<?php
/**
 * Admin shell — Seasons pane (list view).
 */

if (!defined('ABSPATH')) {
    exit;
}

$seasons           = bbj_v2_get_seasons('season_number', 'DESC');
$current_season_id = (int) get_option('bbj_v2_current_season', 0);

// Notices from query args
$notice_html = '';
if (!empty($_GET['error']) && $_GET['error'] === 'not_found') {
    $notice_html = '<div class="mb-4 p-3 bg-red-50 text-red-800 border border-red-200 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800">That season doesn\'t exist.</div>';
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500">Seasons</h1>
            <p class="text-sm text-stone-600 dark:text-slate-400 mt-1">
                All Big Brother seasons tracked on the site.
            </p>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('bbj_v2_create_season_action', 'bbj_v2_create_season_nonce'); ?>
            <input type="hidden" name="action" value="bbj_v2_create_season">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-hard text-white font-semibold text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Season
            </button>
        </form>
    </div>

    <?php echo $notice_html; ?>

    <?php if (empty($seasons)): ?>
        <div class="p-10 text-center border border-dashed border-stone-300 dark:border-slate-700">
            <p class="text-stone-600 dark:text-slate-400 mb-4">No seasons yet — add your first one.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
                <?php wp_nonce_field('bbj_v2_create_season_action', 'bbj_v2_create_season_nonce'); ?>
                <input type="hidden" name="action" value="bbj_v2_create_season">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-hard text-white font-semibold text-sm transition-colors">
                    Add Season
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 dark:bg-slate-800/40 text-stone-600 dark:text-slate-400 uppercase text-xs tracking-wide">
                    <tr>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Dates</th>
                        <th class="px-4 py-2 text-left">Winner</th>
                        <th class="px-4 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seasons as $season):
                        get_template_part('template-parts/admin/partials/seasons-list-row', null, [
                            'season'            => $season,
                            'current_season_id' => $current_season_id,
                        ]);
                    endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</section>
```

- [ ] **Step 3: Smoke test — list renders correctly**

- Visit `http://bbj.localhost/admin/?tab=seasons` as admin.
  - Expected: Full seasons list appears, sorted newest (highest `season_number`) first.
  - The Current season row has a yellow left-border accent.
  - Status badges render: `Current` (yellow), `Completed` (grey), etc.
  - Winners show real names where set, em-dash otherwise.
  - Dates format like "Jul 17 – Oct 13, 2024" for finished seasons, "Jul 10, 2025 – TBD" for current/upcoming.
  - Clicking a season name navigates to `/admin/?tab=seasons&edit=<id>` — the edit pane will error (no pane-seasons-edit.php yet) or render blank; that's expected and fixed in Task 4.
- Visit `http://bbj.localhost/admin/?tab=seasons&error=not_found`.
  - Expected: red error banner "That season doesn't exist." appears above the list.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons.php wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-list-row.php
git commit -m "feat(admin): seasons list with status badges + current-season accent"
```

---

### Task 3: Add Season handler

**Files:**
- Create: `wp-content/plugins/bbj-v2/includes/Actions/form-submits/create-season.php`
- Modify: `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`

- [ ] **Step 1: Write the handler**

Create `wp-content/plugins/bbj-v2/includes/Actions/form-submits/create-season.php`:

```php
<?php
/**
 * Creates a blank draft season when the "Add Season" button is clicked.
 * Inserts a bigbrother-seasons CPT post + a companion wp_bbj_seasons row,
 * then redirects to the edit page for the new season.
 */

if (!defined('ABSPATH')) {
    exit;
}

function bbj_v2_create_season() {
    global $wpdb;

    // 1. Security + capability
    if (
        empty($_POST['bbj_v2_create_season_nonce']) ||
        ! wp_verify_nonce($_POST['bbj_v2_create_season_nonce'], 'bbj_v2_create_season_action') ||
        ! current_user_can('manage_options')
    ) {
        wp_die('Permission check failed');
    }

    // 2. Insert the CPT post as a draft
    $post_id = wp_insert_post([
        'post_type'   => 'bigbrother-seasons',
        'post_status' => 'draft',
        'post_title'  => 'New Season',
    ], true);

    if (is_wp_error($post_id) || ! $post_id) {
        wp_die('Failed to create season post.');
    }

    // 3. Insert the companion custom-table row
    $season_table = $wpdb->prefix . 'bbj_seasons';
    $inserted = $wpdb->insert(
        $season_table,
        [
            'post_id'   => $post_id,
            'full_name' => '',
        ],
        ['%d', '%s']
    );

    if (false === $inserted) {
        // Roll back the orphaned post so we don't leave garbage
        wp_delete_post($post_id, true);
        wp_die('Failed to create season row.');
    }

    $season_id = (int) $wpdb->insert_id;

    // 4. Redirect to the edit page for the new season
    $redirect = add_query_arg(
        ['tab' => 'seasons', 'edit' => $season_id, 'created' => 1],
        home_url('/admin/')
    );
    wp_safe_redirect($redirect);
    exit;
}
```

- [ ] **Step 2: Register the action**

In `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`, add this block after the existing `bbj_v2_edit_season_info` registration (around line 41):

```php
// Create a new season (draft)
add_action('admin_post_bbj_v2_create_season', 'BBJ_load_create_season_handler');

function BBJ_load_create_season_handler() {
    require_once BBJ_FORM_SUBMITS . 'create-season.php';
    bbj_v2_create_season();
}
```

- [ ] **Step 3: Smoke test — Add Season creates a draft and redirects**

- Visit `http://bbj.localhost/admin/?tab=seasons` as admin.
- Click the "Add Season" button.
  - Expected: browser lands on `http://bbj.localhost/admin/?tab=seasons&edit=<new_id>&created=1`.
  - The edit pane still doesn't render (Task 4), but the URL proves the handler worked.
- Navigate back to `http://bbj.localhost/admin/?tab=seasons`.
  - Expected: a new row titled "(Untitled draft)" with status `Draft` appears at the bottom of the list (no season_number yet, so it sorts last).
- In a separate tab, visit `http://bbj.localhost/wp-admin/edit.php?post_type=bigbrother-seasons`.
  - Expected: there's a Draft post titled "New Season". Trash it if you're going to re-test.
- Try clicking Add Season while logged out (open an incognito window).
  - Expected: redirects to wp-login.php — `bbj_v2_require_admin()` on the list page blocked you before the form could be rendered. If somehow submitted, `wp_die('Permission check failed')`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Actions/form-submits/create-season.php wp-content/plugins/bbj-v2/includes/Actions/action-list.php
git commit -m "feat(plugin): add bbj_v2_create_season handler for Add Season button"
```

---

### Task 4: Edit page shell + SeasonSwitcher

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php`

- [ ] **Step 1: Write the edit-page shell**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php`:

```php
<?php
/**
 * Admin shell — Seasons pane (edit view).
 * Receives $args['season_id'] — int, the wp_bbj_seasons.id to edit.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season_id = isset($args['season_id']) ? (int) $args['season_id'] : 0;
$season    = $season_id > 0 ? bbj_v2_get_season_by_id($season_id) : null;

if (!$season) {
    wp_safe_redirect(add_query_arg(
        ['tab' => 'seasons', 'error' => 'not_found'],
        home_url('/admin/')
    ));
    exit;
}

$season = (object) $season;
$all_seasons = bbj_v2_get_seasons('season_number', 'DESC');
$full_name   = (string) ($season->full_name ?? '');
$display_name = $full_name !== '' ? $full_name : 'New Season';
$post_id     = (int) ($season->post_id ?? 0);
$view_url    = $post_id ? get_permalink($post_id) : '';

$notices = [];
if (!empty($_GET['created'])) {
    $notices[] = ['tone' => 'info', 'text' => 'Season created. Fill in the details below and save.'];
}
if (!empty($_GET['updated'])) {
    $notices[] = ['tone' => 'success', 'text' => 'Season saved.'];
}
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">

    <!-- Breadcrumb + view link -->
    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <nav class="text-sm text-stone-500 dark:text-slate-500 mb-1">
                <a href="<?php echo esc_url(add_query_arg('tab', 'seasons', home_url('/admin/'))); ?>" class="hover:text-primary-500">Seasons</a>
                <span class="mx-1">›</span>
                <span>Edit <?php echo esc_html($display_name); ?></span>
            </nav>
            <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500">
                Edit <?php echo esc_html($display_name); ?>
            </h1>
        </div>
        <?php if ($view_url && $full_name !== ''): ?>
            <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-3 py-1.5 text-sm text-stone-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-stone-200 dark:border-slate-700 hover:bg-stone-50 dark:hover:bg-slate-700 transition-colors">
                View season ↗
            </a>
        <?php endif; ?>
    </div>

    <!-- Notices -->
    <?php foreach ($notices as $notice):
        $tone_classes = $notice['tone'] === 'success'
            ? 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-200 dark:border-green-800'
            : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-800';
    ?>
        <div class="mb-4 p-3 border <?php echo esc_attr($tone_classes); ?>"
             data-bbj-autodismiss="<?php echo $notice['tone'] === 'success' ? '3000' : ''; ?>">
            <?php echo esc_html($notice['text']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Season Switcher -->
    <div class="flex items-center gap-2 mb-6 text-sm">
        <label for="bbj-season-switcher" class="text-stone-600 dark:text-slate-400">Switch to:</label>
        <select id="bbj-season-switcher"
                onchange="if (this.value) { window.location = this.value; }"
                class="px-2 py-1 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <?php foreach ($all_seasons as $s):
                $opt_url = esc_url(add_query_arg(
                    ['tab' => 'seasons', 'edit' => (int) $s->id],
                    home_url('/admin/')
                ));
                $opt_name = $s->full_name !== '' ? $s->full_name : '(Untitled draft)';
                $selected = ((int) $s->id === $season_id) ? 'selected' : '';
            ?>
                <option value="<?php echo $opt_url; ?>" <?php echo $selected; ?>>
                    <?php echo esc_html($opt_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Tab nav + bodies (built in Task 5) -->
    <div id="bbj-seasons-edit-tabs">
        <p class="text-stone-600 dark:text-slate-400">Tabs render next task.</p>
    </div>

    <!-- Tiny JS: auto-dismiss success notices -->
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

- [ ] **Step 2: Smoke test — edit pane loads, switcher works, invalid id redirects**

- From the list, click any season row.
  - Expected: lands on `/admin/?tab=seasons&edit=<id>`. Breadcrumb shows `Seasons › Edit <name>`. Season switcher dropdown lists all seasons with current selected.
- Change the season switcher to a different season.
  - Expected: URL updates to the new `edit=<id>`, page reloads showing that season.
- Manually visit `http://bbj.localhost/admin/?tab=seasons&edit=99999` (non-existent id).
  - Expected: redirected back to `/admin/?tab=seasons&error=not_found`; red banner "That season doesn't exist." appears on the list.
- Visit the Add-Season-created draft from Task 3 (`?edit=<new_id>`).
  - Expected: breadcrumb reads "Edit New Season"; blue "Season created. Fill in the details below and save." notice visible.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php
git commit -m "feat(admin): season edit pane shell with switcher and notices"
```

---

### Task 5: Tab nav + in-tab stub partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-stub.php`
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php`

- [ ] **Step 1: Write the stub partial**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-stub.php`:

```php
<?php
/**
 * Reusable "Coming in Sprint A" inline stub used for inactive tabs
 * and for unfinished sections inside the Info tab.
 * Receives $args['label'] — string, the section / tab name.
 */

if (!defined('ABSPATH')) {
    exit;
}

$label = isset($args['label']) ? (string) $args['label'] : 'This section';
?>

<div class="p-6 bg-stone-50 dark:bg-slate-900/40 border border-dashed border-stone-300 dark:border-slate-700">
    <p class="font-semibold text-stone-700 dark:text-slate-300"><?php echo esc_html($label); ?></p>
    <p class="text-sm text-stone-500 dark:text-slate-500 mt-1">
        This section ships in Sprint A.
    </p>
</div>
```

- [ ] **Step 2: Write the tabs partial**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php`:

```php
<?php
/**
 * Admin shell — edit-season tab navigation + tab bodies.
 * Receives $args['season'] — the season row object.
 * Receives $args['season_id'] — int.
 *
 * Tab state is held in the URL hash (#info, #spoiler, #photos).
 * Pre-sprint default is #info since #spoiler is stubbed.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season    = $args['season'];
$season_id = (int) $args['season_id'];

$tabs = [
    ['id' => 'spoiler', 'label' => 'Spoiler Bar'],
    ['id' => 'info',    'label' => 'Season Info'],
    ['id' => 'photos',  'label' => 'Player Photos'],
];
?>

<div class="border-b border-stone-200 dark:border-slate-700 mb-6">
    <nav class="flex -mb-px gap-1" role="tablist" id="bbj-season-tabs">
        <?php foreach ($tabs as $tab): ?>
            <a href="#<?php echo esc_attr($tab['id']); ?>"
               data-bbj-tab="<?php echo esc_attr($tab['id']); ?>"
               role="tab"
               class="bbj-season-tab px-4 py-2 text-sm font-semibold border-b-2 border-transparent text-stone-500 hover:text-primary-500 dark:text-slate-400 dark:hover:text-secondary-500 transition-colors">
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Tab panels -->
<div data-bbj-tab-panel="spoiler" class="bbj-tab-panel">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Spoiler Bar',
    ]); ?>
</div>

<div data-bbj-tab-panel="info" class="bbj-tab-panel hidden">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-info', null, [
        'season'    => $season,
        'season_id' => $season_id,
    ]); ?>
</div>

<div data-bbj-tab-panel="photos" class="bbj-tab-panel hidden">
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Player Photos',
    ]); ?>
</div>

<script>
(function () {
    var VALID = ['spoiler', 'info', 'photos'];
    var DEFAULT_TAB = 'info'; // Pre-sprint default — Spoiler Bar is stubbed.

    function showTab(tabId) {
        if (VALID.indexOf(tabId) === -1) tabId = DEFAULT_TAB;

        document.querySelectorAll('.bbj-season-tab').forEach(function (el) {
            var active = el.getAttribute('data-bbj-tab') === tabId;
            el.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) {
                el.classList.add('text-primary-500', 'border-primary-500');
                el.classList.remove('text-stone-500', 'border-transparent');
            } else {
                el.classList.remove('text-primary-500', 'border-primary-500');
                el.classList.add('text-stone-500', 'border-transparent');
            }
        });

        document.querySelectorAll('.bbj-tab-panel').forEach(function (panel) {
            var show = panel.getAttribute('data-bbj-tab-panel') === tabId;
            panel.classList.toggle('hidden', !show);
        });
    }

    function getTabFromHash() {
        var h = (window.location.hash || '').replace(/^#/, '');
        return VALID.indexOf(h) !== -1 ? h : DEFAULT_TAB;
    }

    window.addEventListener('hashchange', function () {
        showTab(getTabFromHash());
    });

    // Initial render
    showTab(getTabFromHash());
})();
</script>
```

- [ ] **Step 3: Create a placeholder for the Info tab partial (filled out in Task 6)**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-info.php` with a minimal shell that renders the stub for now (so `get_template_part()` doesn't no-op):

```php
<?php
/**
 * Admin shell — Season Info tab body.
 * Receives $args['season'] — season row object.
 * Receives $args['season_id'] — int.
 *
 * Form shipping in Task 6. This file exists now so the tabs partial can include it.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
    'label' => 'Season Info (work-in-progress)',
]);
```

- [ ] **Step 4: Wire the tabs partial into `pane-seasons-edit.php`**

In `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php`, replace this block:

```php
<!-- Tab nav + bodies (built in Task 5) -->
<div id="bbj-seasons-edit-tabs">
    <p class="text-stone-600 dark:text-slate-400">Tabs render next task.</p>
</div>
```

with:

```php
<!-- Tab nav + bodies -->
<?php get_template_part('template-parts/admin/partials/seasons-edit-tabs', null, [
    'season'    => $season,
    'season_id' => $season_id,
]); ?>
```

- [ ] **Step 5: Smoke test — tabs switch, hash updates, reload persists**

- Visit `/admin/?tab=seasons&edit=<id>`.
  - Expected: three tabs render (Spoiler Bar, Season Info, Player Photos). Season Info tab is active by default; its panel shows the "Season Info (work-in-progress)" stub. Spoiler Bar + Photos panels are hidden.
- Click "Spoiler Bar" tab.
  - Expected: URL updates to `#spoiler`, the Spoiler Bar stub becomes visible, the active-tab underline moves.
- Reload the page with `#spoiler` in URL.
  - Expected: Spoiler Bar tab still active after reload (initial render reads the hash).
- Clear the hash (remove `#spoiler` from URL) and reload.
  - Expected: Info tab active by default.
- Click Player Photos tab, then back to Season Info, then Spoiler Bar.
  - Expected: panel visibility swaps correctly each time; browser back button steps through hash history.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-stub.php wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-info.php wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php
git commit -m "feat(admin): edit-season tab nav with hashchange + stub partial"
```

---

### Task 6: Info tab — BasicInfo + Dates live form

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-info.php`

- [ ] **Step 1: Replace the info-tab placeholder with the real form**

Replace the full contents of `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-info.php`:

```php
<?php
/**
 * Admin shell — Season Info tab body.
 * Receives $args['season'] — season row object.
 * Receives $args['season_id'] — int.
 *
 * BasicInfo + Dates sections are live. Images / Winners / Roster render as
 * "Coming in Sprint A" stubs within this tab.
 */

if (!defined('ABSPATH')) {
    exit;
}

$season    = $args['season'];
$season_id = (int) $args['season_id'];

$full_name     = (string) ($season->full_name ?? '');
$season_number = (string) ($season->season_number ?? '');
$abbreviation  = (string) ($season->abbreviation ?? '');
$start_date    = (string) ($season->start_date ?? '');
$end_date      = (string) ($season->end_date ?? '');
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-6">
    <?php wp_nonce_field('edit_season_action', 'edit_season_nonce'); ?>
    <input type="hidden" name="action" value="bbj_v2_edit_season_info">
    <input type="hidden" name="season_id" value="<?php echo esc_attr($season_id); ?>">

    <!-- BasicInfo -->
    <fieldset class="p-5 border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40">
        <legend class="px-2 font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500">
            Basic Info
        </legend>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-name">
                    Full name <span class="text-red-600">*</span>
                </label>
                <input type="text" id="bbj-season-name" name="season_name" required
                       value="<?php echo esc_attr($full_name); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-number">
                    Season number <span class="text-red-600">*</span>
                </label>
                <input type="text" id="bbj-season-number" name="season_number" required
                       value="<?php echo esc_attr($season_number); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-abbr">
                    Abbreviation
                </label>
                <input type="text" id="bbj-season-abbr" name="season_abbreviation"
                       value="<?php echo esc_attr($abbreviation); ?>"
                       placeholder="BB27"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>
        </div>
    </fieldset>

    <!-- Dates -->
    <fieldset class="p-5 border border-stone-200 dark:border-slate-700 bg-white dark:bg-slate-900/40">
        <legend class="px-2 font-osw text-lg uppercase tracking-wide text-primary-500 dark:text-secondary-500">
            Dates
        </legend>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-start">
                    Start date
                </label>
                <input type="date" id="bbj-season-start" name="season_start_date"
                       value="<?php echo esc_attr($start_date); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-stone-700 dark:text-slate-300 mb-1" for="bbj-season-end">
                    End date
                </label>
                <input type="date" id="bbj-season-end" name="season_end_date"
                       value="<?php echo esc_attr($end_date); ?>"
                       class="w-full px-3 py-2 border border-stone-300 dark:border-slate-700 bg-white dark:bg-slate-900">
            </div>
        </div>
    </fieldset>

    <!-- Images stub -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Images (cover + banner)',
    ]); ?>

    <!-- Winners stub -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Winners (Winner / Runner-up / AFP)',
    ]); ?>

    <!-- Roster stub -->
    <?php get_template_part('template-parts/admin/partials/seasons-edit-stub', null, [
        'label' => 'Roster (add / remove players)',
    ]); ?>

    <!-- Save -->
    <div class="flex items-center justify-end gap-3">
        <a href="<?php echo esc_url(add_query_arg('tab', 'seasons', home_url('/admin/'))); ?>"
           class="px-4 py-2 text-sm text-stone-600 dark:text-slate-400 hover:text-stone-800 dark:hover:text-slate-200">
            Back to list
        </a>
        <button type="submit"
                class="px-5 py-2 bg-primary-500 hover:bg-primary-hard text-white font-semibold text-sm transition-colors">
            Save Season
        </button>
    </div>

</form>
```

- [ ] **Step 2: Smoke test — form renders, existing values populate correctly**

- Visit `/admin/?tab=seasons&edit=<id>` for a known season (e.g. BB26) and click the Season Info tab.
  - Expected: fields are pre-filled with current values (full_name, season_number, abbreviation, start_date, end_date).
  - Images / Winners / Roster render as "Coming in Sprint A" stubs below the live fieldsets.
  - "Save Season" button visible at the bottom.
- Visit `/admin/?tab=seasons&edit=<draft_id>` (a draft created in Task 3).
  - Expected: fields are empty (full_name ""), date fields blank.

**Do not save yet** — the existing handler has a bug (wrong post ID in `wp_update_post`) that Task 7 fixes. If you save now, the row will update but the companion post title won't (existing behavior — no regression, just unfixed).

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-info.php
git commit -m "feat(admin): info tab with BasicInfo + Dates live, rest stubbed"
```

---

### Task 7: Fix `bbj_v2_edit_season_info()` handler

**Files:**
- Modify: `wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php`

**Context:** The existing handler has a pre-existing bug — it calls `wp_update_post(['ID' => $season_id, 'post_title' => $season_name])` but `$season_id` is the `wp_bbj_seasons.id` (custom-table PK), NOT the WP post ID. This silently no-ops. We also need to publish the draft created by `bbj_v2_create_season()` on first meaningful save, and guard the cache-bust to only fire for the current season.

- [ ] **Step 1: Read the current `bbj_v2_edit_season_info()` function (lines 4-64 of update-season.php)**

Confirm these blocks exist in the current file:

```php
// (line 4)
function bbj_v2_edit_season_info() {
    global $wpdb;
    // ... nonce + sanitization ...

    // 3) Update custom table
    $season_table = $wpdb->prefix . 'bbj_seasons';
    $data = [ ... ];
    $where = [ 'id' => $season_id ];
    // ... wpdb update ...

    // 4) Also update the WP post title so it stays in sync
    wp_update_post([
        'ID'         => $season_id,          // BUG: should be $season->post_id
        'post_title' => $season_name,
    ]);

    // bust cache
    bbj_spoiler_bar_bust_cache( $season_id );

    // 5) Redirect back with a success flag
    $redirect = wp_get_referer() ?: home_url();
    wp_safe_redirect( add_query_arg( 'updated', '1', $redirect ) );
    exit;
}
```

- [ ] **Step 2: Replace block 4 (post-title sync) with a fixed version**

Find this block:

```php
    // 4) Also update the WP post title so it stays in sync
    wp_update_post([
        'ID'         => $season_id,
        'post_title' => $season_name,
    ]);
```

Replace with:

```php
    // 4) Sync the companion CPT post (title + publish if still draft).
    //    Look up the real post_id from the custom-table row — prior code
    //    was passing the custom-table PK as the post ID, which silently
    //    no-op'd.
    $season_row = bbj_v2_get_season_by_id( $season_id );
    $post_id    = $season_row ? (int) $season_row->post_id : 0;

    if ( $post_id > 0 ) {
        $post_update = [
            'ID'         => $post_id,
            'post_title' => $season_name,
        ];
        // Publish the draft on first meaningful save (user set a real name).
        $existing_status = get_post_status( $post_id );
        if ( $existing_status === 'draft' && $season_name !== '' ) {
            $post_update['post_status'] = 'publish';
        }
        wp_update_post( $post_update );
    }
```

- [ ] **Step 3: Guard the cache-bust to only fire for the current season**

Find this block:

```php
    // bust cache
    bbj_spoiler_bar_bust_cache( $season_id );
```

Replace with:

```php
    // Bust spoiler-bar cache only when we edited the currently-active season.
    $current_season_id = (int) get_option( 'bbj_v2_current_season', 0 );
    if ( $current_season_id === (int) $season_id ) {
        bbj_spoiler_bar_bust_cache( $season_id );
    }
```

- [ ] **Step 4: Smoke test — full create-then-edit flow**

- Visit `/admin/?tab=seasons` and click **Add Season**.
  - Expected: new draft appears in the list; you land on the edit page with blue "Season created" notice.
- On the edit page, Season Info tab: enter a Full name ("Test Season"), Season number ("99"), Abbreviation ("TS99"), Start date (any past date), End date (any past date). Click **Save Season**.
  - Expected: page reloads at `/admin/?tab=seasons&edit=<id>&updated=1`. Green "Season saved." notice appears and auto-dismisses after ~3s.
  - Fields still show the new values.
- Navigate back to `/admin/?tab=seasons`.
  - Expected: the row is no longer `Draft` — it shows `Completed` (since end_date is in past). Name is "Test Season", abbreviation "TS99" chip visible.
- Visit `/wp-admin/edit.php?post_type=bigbrother-seasons` in a separate tab.
  - Expected: post title is "Test Season" (not "New Season"), status is `Published` (not `Draft`). **Fix confirmed.**
- Delete the test season from wp-admin when done (bin both the post and the `wp_bbj_seasons` row via phpMyAdmin if you want a clean state).
- Edit an existing real season (BB26) and change its name cosmetically then revert — confirm no cache disruption on non-current seasons (spoiler-bar cache is only busted when saving the current season).

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php
git commit -m "fix(plugin): bbj_v2_edit_season_info post-title sync + publish draft

The existing handler passed the custom-table PK to wp_update_post() as
the post ID, silently no-op'ing title syncs for years. Look up the real
post_id from the season row, and promote drafts to publish on first
meaningful save. Guard cache-bust to only fire for the current season."
```

---

### Task 8: Final full-flow smoke test + cleanup

**Files:** none modified — verification pass only.

- [ ] **Step 1: Run the full spec smoke-test checklist**

All 8 scenarios from the spec — do each one fresh:

1. Visit `/admin/?tab=seasons`. List shows all seasons, sorted by season_number DESC. Current season has a yellow left-border accent. Status badges render correctly.
2. Click "Add Season". Lands on `/admin/?tab=seasons&edit=<new>&created=1`. Info tab is the active tab. Fields are empty. Blue "Season created" notice visible.
3. Fill in full_name + season_number + dates → Save. Redirect to `/admin/?tab=seasons&edit=<id>&updated=1`. Green "Season saved" notice visible and auto-dismisses. Going back to list shows the new season with proper status badge + no longer a draft in wp-admin.
4. Edit an existing season. Fields pre-populated. Save works. Cache only busts when the saved season is the current one.
5. Visit `/admin/?tab=seasons&edit=99999`. Redirects to list with red "not found" banner.
6. On edit page, use Season Switcher dropdown. Navigates to other seasons without going through the list.
7. Tab nav: click Spoiler Bar → stub appears, URL becomes `#spoiler`. Reload → Spoiler Bar tab still active. Clear hash, reload → Info tab default. Browser back traverses tab history.
8. Log out (or open incognito). Hit `/admin/?tab=seasons` → redirects to wp-login.php.

- [ ] **Step 2: Visual QA — flat-editorial consistency**

Compare side-by-side with `/admin/?tab=overview`:
- Same `bg-white` + `border-stone-200` card treatment
- Same heading style (font-mainHead, primary-500 color)
- Same Oswald section headings inside fieldsets
- Same table header color/tracking on list page
- No rounded corners, no shadows

If any deviation, fix inline and commit as `chore(admin): match flat-editorial styling`.

- [ ] **Step 3: Update roadmap**

Edit `.claude/project/roadmap.md` — in the "What's shipped" section (top), add:

```markdown
- **Seasons admin pane** (`/admin?tab=seasons`) — flat list with status badges + current-season accent, Add Season draft flow, edit page shell with 3-tab layout (Spoiler Bar / Info / Photos), Season Info tab live for BasicInfo + Dates; Images / Winners / Roster stubbed for Sprint A 🟡
```

Update the "Last updated" date to today.

- [ ] **Step 4: Final commit + roadmap commit**

```bash
git add .claude/project/roadmap.md
git commit -m "docs(roadmap): mark seasons admin pane shipped on staging"
```

---

## Self-review notes

- **Spec coverage:** every spec section maps to at least one task. List page (Task 2), Add Season (Task 3), Edit shell (Task 4), tabs + stubs (Task 5), Info form (Task 6), handler fix (Task 7), full QA (Task 8).
- **Type consistency:** `$season_id` is always the `wp_bbj_seasons.id`; `$post_id` is always the WP post ID; fix in Task 7 makes the distinction explicit.
- **No new dependencies:** all code uses existing theme classes, existing plugin helpers, existing WP core functions.
- **Reversibility:** every change is in a new file or an isolated function; rolling back is straightforward via `git revert`.
- **Out of scope (confirmed, per spec):** delete action, pagination, Player roster add/remove, Images/Winners fields, Spoiler Bar editing, REST, mobile, search, filters.
