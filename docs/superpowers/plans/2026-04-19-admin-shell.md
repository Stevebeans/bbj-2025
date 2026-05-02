# Front-End Admin + User Dashboard Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a front-end admin shell at `/admin` and a front-end user dashboard shell at `/dashboard` inside `bbj-v2-theme`, with a mandatory safeguard helper, a sidebar + overview placeholder, and a header icon row for logged-in users. Feature panes are "Coming soon" stubs that later sprints fill in.

**Architecture:** Two new WordPress pages ("Admin" and "Dashboard") use custom page templates in the theme. Sub-panes are selected by a `?tab=<slug>` query var. A single safeguard helper (`bbj_v2_require_admin()` / `bbj_v2_require_logged_in()`) is called on line 1 of every admin/dashboard template — this is the only line keeping admin content out of public hands, so it's mandatory and codified in memory (`feedback_admin_page_safeguard.md`). No new React dependencies; pure PHP + inline SVG.

**Tech Stack:** PHP 7.4+ (WordPress), Tailwind CSS 3.4 (already in theme), inline SVG (heroicon-style paths), no JS frameworks.

**Working branch:** `staging` (per no-worktrees rule and current active branch).

**Spec:** `docs/superpowers/specs/2026-04-19-admin-shell-design.md` (commit `3a6ac4f`).

---

## Pitfall: the Write tool can silently no-op on pre-existing untracked files

Before moving on from ANY task that uses `Write` on a file that might already exist on disk, verify the write landed by running a `head` or `grep` for a unique marker from the intended content. Do NOT trust the Write tool's success message alone. See `feedback_verify_write_on_untracked.md`. This plan marks verification steps where it matters.

---

## Task 1: Add safeguard helpers + `tab` query var to the theme

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/inc/admin-shell.php`
- Modify: `wp-content/themes/bbj-v2-theme/functions.php` (single line added to require the new include)

- [ ] **Step 1: Create `inc/admin-shell.php` with the two safeguards and the query_vars filter**

File content:

```php
<?php
/**
 * Admin / user-dashboard shell helpers.
 *
 * Every `/admin/*` and `/dashboard/*` front-end template MUST call one of
 * the safeguards below on line 1 (see feedback_admin_page_safeguard memory).
 * Front-end admin URLs are public — WP core does NOT auto-enforce a capability
 * check the way add_menu_page does. Forgetting the safeguard exposes the page.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Require an admin (manage_options) user. Redirect to login if logged-out,
 * return 403 if logged-in but not an admin.
 */
function bbj_v2_require_admin(): void
{
    if (!is_user_logged_in()) {
        $current = home_url(add_query_arg([]));
        wp_safe_redirect(wp_login_url($current));
        exit;
    }

    if (!current_user_can('manage_options')) {
        status_header(403);
        wp_die(
            esc_html__('You do not have permission to access this page.', 'bbj-v2-theme'),
            esc_html__('Access Denied', 'bbj-v2-theme'),
            ['response' => 403]
        );
    }
}

/**
 * Require any logged-in user. Redirect to login with return URL if logged-out.
 */
function bbj_v2_require_logged_in(): void
{
    if (!is_user_logged_in()) {
        $current = home_url(add_query_arg([]));
        wp_safe_redirect(wp_login_url($current));
        exit;
    }
}

/**
 * Register `tab` as an allowed query var so sub-pane routing works.
 */
add_filter('query_vars', function (array $vars): array {
    $vars[] = 'tab';
    return $vars;
});
```

- [ ] **Step 2: Verify the file wrote correctly**

Run: `head -5 wp-content/themes/bbj-v2-theme/inc/admin-shell.php`
Expected output begins with:
```
<?php
/**
 * Admin / user-dashboard shell helpers.
```

If not, re-run the Write with the exact content above.

- [ ] **Step 3: Require the new file from functions.php**

Edit `wp-content/themes/bbj-v2-theme/functions.php`. Find this block near the top (around line 19-25):

```php
require_once BBJ_V2_THEME_PATH . '/inc/setup.php';
require_once BBJ_V2_THEME_PATH . '/inc/enqueue.php';
require_once BBJ_V2_THEME_PATH . '/inc/template-functions.php';
require_once BBJ_V2_THEME_PATH . '/inc/dark-mode.php';
require_once BBJ_V2_THEME_PATH . '/inc/auth.php';
require_once BBJ_V2_THEME_PATH . '/inc/homepage-data.php';
```

Add one new line immediately after:

```php
require_once BBJ_V2_THEME_PATH . '/inc/admin-shell.php';
```

- [ ] **Step 4: PHP lint both files**

Run:
```bash
php -l wp-content/themes/bbj-v2-theme/inc/admin-shell.php
php -l wp-content/themes/bbj-v2-theme/functions.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Smoke test — load the homepage and confirm no fatals**

Run:
```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/bbj/
```

Expected: `200`.

Also run:
```bash
curl -s http://localhost/bbj/ | grep -i "fatal\|parse error" | head -3
```

Expected: (no output) — if anything prints, functions.php broke and needs fixing before commit.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/inc/admin-shell.php wp-content/themes/bbj-v2-theme/functions.php
git commit -m "$(cat <<'EOF'
feat(theme): admin-shell safeguards + tab query var

Adds bbj_v2_require_admin() and bbj_v2_require_logged_in() helpers that
must be called on line 1 of every /admin and /dashboard front-end template.
Registers `tab` as an allowed query var for sub-pane routing.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Provision the "Admin" and "Dashboard" WordPress pages

**Files:**
- Create (temporary, deleted at end of task): `seed-admin-pages.php` at repo root

No commit in this task — the provisioning effect is a database insert, not a file change. The seed script is deleted before the next task.

- [ ] **Step 1: Create `seed-admin-pages.php` at repo root**

File content:

```php
<?php
/**
 * One-shot provisioning script: creates WP pages "Admin" (slug: admin)
 * and "Dashboard" (slug: dashboard) if they don't already exist.
 * Idempotent — safe to re-run. Delete this file after verifying output.
 *
 * Usage:
 *   php seed-admin-pages.php
 * OR visit in browser: http://localhost/bbj/seed-admin-pages.php
 */

require __DIR__ . '/wp-load.php';

function bbjd_seed_page(string $slug, string $title): int
{
    $existing = get_page_by_path($slug);
    if ($existing) {
        fwrite(STDOUT, "Page '{$slug}' already exists (ID: {$existing->ID}).\n");
        return (int) $existing->ID;
    }

    $id = wp_insert_post([
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '',
    ], true);

    if (is_wp_error($id)) {
        fwrite(STDERR, "FAILED to create '{$slug}': " . $id->get_error_message() . "\n");
        exit(1);
    }

    fwrite(STDOUT, "Created page '{$slug}' (ID: {$id}).\n");
    return (int) $id;
}

$admin_id     = bbjd_seed_page('admin', 'Admin');
$dashboard_id = bbjd_seed_page('dashboard', 'Dashboard');

fwrite(STDOUT, "\nDone. Admin page ID: {$admin_id}. Dashboard page ID: {$dashboard_id}.\n");
fwrite(STDOUT, "Delete this file before committing.\n");
```

- [ ] **Step 2: Verify the file wrote**

Run: `head -5 seed-admin-pages.php`
Expected: starts with `<?php` and the docblock.

- [ ] **Step 3: Run the seed script**

Run:
```bash
php seed-admin-pages.php
```

Expected output (first run):
```
Created page 'admin' (ID: <some number>).
Created page 'dashboard' (ID: <some number>).

Done. Admin page ID: X. Dashboard page ID: Y.
Delete this file before committing.
```

If pages already existed, the "already exists" messages are fine — idempotent.

- [ ] **Step 4: Smoke that the pages respond**

Run:
```bash
curl -s -o /dev/null -w "/admin: %{http_code}\n" http://localhost/bbj/admin/
curl -s -o /dev/null -w "/dashboard: %{http_code}\n" http://localhost/bbj/dashboard/
```

Expected: both return `200` (WP is rendering them with the default `page.php` template since we haven't added `page-admin.php` / `page-dashboard.php` yet). If you see `404`, the seed didn't run successfully — re-check Step 3 output.

- [ ] **Step 5: Delete the seed script**

Run:
```bash
rm seed-admin-pages.php
```

Then verify it's gone:
```bash
ls seed-admin-pages.php 2>&1
```
Expected: `ls: cannot access 'seed-admin-pages.php': No such file or directory`.

No commit for this task — the WP pages now exist in the database but there are no file changes to stage.

---

## Task 3: Create the admin sidebar partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php`

- [ ] **Step 1: Create `template-parts/admin/sidebar.php`**

File content:

```php
<?php
/**
 * Admin shell sidebar. Rendered by page-admin.php.
 * Receives $args['active'] — the current tab slug (defaults to 'overview').
 */

if (!defined('ABSPATH')) {
    exit;
}

$active = isset($args['active']) ? (string) $args['active'] : 'overview';

$items = [
    ['slug' => 'overview',       'label' => 'Overview',       'icon' => 'home'],
    ['slug' => 'posts',          'label' => 'Posts',          'icon' => 'document-text'],
    ['slug' => 'feed-updates',   'label' => 'Feed Updates',   'icon' => 'rss'],
    ['slug' => 'comments',       'label' => 'Comments',       'icon' => 'chat'],
    ['slug' => 'players',        'label' => 'Players',        'icon' => 'users'],
    ['slug' => 'seasons',        'label' => 'Seasons',        'icon' => 'calendar'],
    ['slug' => 'announcements',  'label' => 'Announcements',  'icon' => 'megaphone'],
    ['slug' => 'content-engine', 'label' => 'Content',        'icon' => 'pencil-square'],
    ['slug' => 'users',          'label' => 'Users',          'icon' => 'users'],
    ['slug' => 'stats',          'label' => 'Stats',          'icon' => 'chart-bar'],
    ['slug' => 'settings',       'label' => 'Settings',       'icon' => 'cog'],
    ['slug' => 'spoiler-bar',    'label' => 'Spoiler Bar',    'icon' => 'shield-check'],
];

$current_user = wp_get_current_user();
?>

<aside class="w-52 shrink-0 self-start sticky top-4 bg-white dark:bg-gray-900 border border-stone-200 dark:border-slate-800">
    <div class="px-4 py-3 border-b border-stone-200 dark:border-slate-700">
        <div class="text-xs uppercase tracking-wider text-stone-500">Admin</div>
        <div class="text-sm text-stone-800 dark:text-slate-200 mt-0.5 truncate">
            <?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?>
        </div>
    </div>

    <nav class="py-2 px-2" aria-label="<?php esc_attr_e('Admin navigation', 'bbj-v2-theme'); ?>">
        <?php foreach ($items as $item):
            $is_active = ($item['slug'] === $active);
            $url = $item['slug'] === 'overview'
                ? esc_url(home_url('/admin/'))
                : esc_url(add_query_arg('tab', $item['slug'], home_url('/admin/')));
            $classes = $is_active
                ? 'bg-primary-500 text-white'
                : 'text-stone-700 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800';
        ?>
            <a href="<?php echo $url; ?>"
               class="flex items-center gap-3 px-3 py-2 text-sm font-medium transition-colors <?php echo $classes; ?>"
               <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                <?php bbj_v2_admin_icon($item['icon']); ?>
                <span><?php echo esc_html($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-2 py-2 border-t border-stone-200 dark:border-slate-700">
        <a href="<?php echo esc_url(home_url('/')); ?>"
           class="flex items-center gap-2 px-3 py-2 text-sm text-stone-600 hover:bg-stone-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            <span><?php esc_html_e('Back to Site', 'bbj-v2-theme'); ?></span>
        </a>
    </div>
</aside>

<?php
/**
 * Helper: print an inline SVG by icon name. Kept local to avoid polluting
 * global scope. Paths come from heroicon set (stroke, 24x24, stroke 1.5).
 */
if (!function_exists('bbj_v2_admin_icon')) {
    function bbj_v2_admin_icon(string $name): void
    {
        $paths = [
            'home'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
            'document-text'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
            'rss'            => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"/>',
            'chat'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
            'users'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
            'calendar'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
            'megaphone'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>',
            'pencil-square'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>',
            'chart-bar'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            'cog'            => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'shield-check'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
        ];
        $svg = $paths[$name] ?? '';
        echo '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' . $svg . '</svg>';
    }
}
```

- [ ] **Step 2: Verify the file wrote**

Run: `head -10 wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php`
Expected: starts with `<?php` and the docblock referencing "Admin shell sidebar".

- [ ] **Step 3: PHP lint**

Run: `php -l wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php
git commit -m "$(cat <<'EOF'
feat(theme): admin shell sidebar partial

Sidebar with 12 nav items, inline heroicon-style SVGs, active-state
highlight, Back to Site link, and signed-in user display.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Create the admin overview + stub panes

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-overview.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-stub.php`

- [ ] **Step 1: Create `pane-overview.php`**

File content:

```php
<?php
/**
 * Admin shell — Overview pane (the only non-stub pane in v1).
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$first_name   = $current_user->first_name ?: $current_user->display_name ?: $current_user->user_login;
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        <?php printf(
            /* translators: %s: display or first name */
            esc_html__('Welcome, %s.', 'bbj-v2-theme'),
            esc_html($first_name)
        ); ?>
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        <?php esc_html_e('This is your admin dashboard. Pick a section from the sidebar to get started. Feature panes are being built out — the shell is live, the rooms are still being furnished.', 'bbj-v2-theme'); ?>
    </p>
</section>
```

- [ ] **Step 2: Verify**

Run: `head -5 wp-content/themes/bbj-v2-theme/template-parts/admin/pane-overview.php`
Expected: starts with `<?php` and the docblock.

- [ ] **Step 3: Create `pane-stub.php`**

File content:

```php
<?php
/**
 * Admin shell — generic "Coming soon" stub pane used for every non-overview tab.
 * Receives $args['tab'] — the current tab slug.
 */

if (!defined('ABSPATH')) {
    exit;
}

$tab_slug = isset($args['tab']) ? (string) $args['tab'] : '';

// Match labels to the admin sidebar item table (kept in sync by hand for v1).
$labels = [
    'posts'          => 'Posts',
    'feed-updates'   => 'Feed Updates',
    'comments'       => 'Comments',
    'players'        => 'Players',
    'seasons'        => 'Seasons',
    'announcements'  => 'Announcements',
    'content-engine' => 'Content',
    'users'          => 'Users',
    'stats'          => 'Stats',
    'settings'       => 'Settings',
    'spoiler-bar'    => 'Spoiler Bar',
];
$label = $labels[$tab_slug] ?? ucwords(str_replace('-', ' ', $tab_slug));
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        <?php printf(
            /* translators: %s: tab label */
            esc_html__('Coming soon — %s.', 'bbj-v2-theme'),
            esc_html($label)
        ); ?>
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        <?php esc_html_e('This section is part of the shell but hasn\'t been built out yet. Check back after the next sprint.', 'bbj-v2-theme'); ?>
    </p>
</section>
```

- [ ] **Step 4: Verify**

Run: `head -5 wp-content/themes/bbj-v2-theme/template-parts/admin/pane-stub.php`
Expected: starts with `<?php` and the docblock.

- [ ] **Step 5: PHP lint both**

Run:
```bash
php -l wp-content/themes/bbj-v2-theme/template-parts/admin/pane-overview.php
php -l wp-content/themes/bbj-v2-theme/template-parts/admin/pane-stub.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-overview.php wp-content/themes/bbj-v2-theme/template-parts/admin/pane-stub.php
git commit -m "$(cat <<'EOF'
feat(theme): admin shell overview + stub panes

Overview is the only non-stub pane in v1. Stub is a generic "Coming soon — {Label}"
card used by every other sidebar tab. Label map is kept in sync with sidebar.php by hand.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Create `page-admin.php` template

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/page-admin.php`

- [ ] **Step 1: Create `page-admin.php`**

File content:

```php
<?php
bbj_v2_require_admin(); // LINE 1 SAFEGUARD — DO NOT WRAP, DO NOT REMOVE.
/**
 * Front-end admin shell template.
 *
 * Auto-assigned to the "Admin" WP page via WP's page-{slug}.php hierarchy —
 * no Template Name header needed. Sub-panes selected via ?tab=<slug>.
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = get_query_var('tab');
if (!is_string($active_tab) || $active_tab === '') {
    $active_tab = 'overview';
}

get_header();
?>

<div class="bbj-admin-shell min-h-[60vh]">
    <div class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">

        <?php get_template_part('template-parts/admin/sidebar', null, [
            'active' => $active_tab,
        ]); ?>

        <section class="flex-1 min-w-0">
            <?php if ($active_tab === 'overview'): ?>
                <?php get_template_part('template-parts/admin/pane-overview'); ?>
            <?php else: ?>
                <?php get_template_part('template-parts/admin/pane-stub', null, [
                    'tab' => $active_tab,
                ]); ?>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 2: Verify the safeguard is on line 2 (first executable line after `<?php`)**

Run: `head -3 wp-content/themes/bbj-v2-theme/page-admin.php`
Expected:
```
<?php
bbj_v2_require_admin(); // LINE 1 SAFEGUARD — DO NOT WRAP, DO NOT REMOVE.
/**
```

If not, this is a SECURITY-CRITICAL issue — fix before proceeding. The safeguard MUST fire before any output or any includes.

- [ ] **Step 3: PHP lint**

Run: `php -l wp-content/themes/bbj-v2-theme/page-admin.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Assign the template to the "Admin" WP page**

The spec says the page template is auto-selected by WordPress via the `page-admin.php` filename convention (pages with slug `admin` automatically pick up `page-{slug}.php` per the WP template hierarchy). So no wp-admin action needed — the template attaches itself.

Verify by running:
```bash
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost/bbj/admin/"
```

Expected: `302` if you're logged-out (safeguard redirects to login). `403` if you're logged-in as a non-admin. `200` if you're logged-in as admin.

**Note:** To test the 200 case you need a browser session with admin cookies. The curl check is only valid for the unauthenticated redirect path. The proper smoke is Step 5 below.

- [ ] **Step 5: Manual browser smoke (admin path)**

1. Open `http://localhost/bbj/admin/` in a browser window where you're logged in as an admin.
2. Expected: admin shell renders with sidebar on the left (Overview highlighted), main content says "Welcome, {your first name}." and the "rooms are still being furnished" paragraph.
3. Click "Settings" in the sidebar. URL becomes `http://localhost/bbj/admin/?tab=settings`.
4. Expected: main content changes to "Coming soon — Settings." Sidebar highlight moves to Settings.
5. Click "Back to Site". Expected: homepage loads.
6. In a second browser (or incognito), visit `http://localhost/bbj/admin/` while logged OUT.
7. Expected: redirect to login page with `?redirect_to=...admin/` appended.
8. Log in as a subscriber (no `manage_options`) and visit `/admin/`.
9. Expected: 403 Access Denied page.

If any of these fail, fix before commit. Common issues:
- `?tab=` not propagating: check `query_vars` filter from Task 1 is active (the functions.php require_once line runs).
- Safeguard not firing: confirm `bbj_v2_require_admin()` is line 2 and no whitespace before `<?php`.
- Template not picking up: confirm the WP page slug is exactly `admin` (from Task 2 seed).

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/page-admin.php
git commit -m "$(cat <<'EOF'
feat(theme): page-admin.php template for /admin shell

Safeguard (bbj_v2_require_admin) on line 2, renders sidebar + pane via
get_template_part based on ?tab= query var. Overview is the only real pane;
every other tab falls through to the Coming soon stub.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Create the dashboard sidebar partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/dashboard/sidebar.php`

- [ ] **Step 1: Create `template-parts/dashboard/sidebar.php`**

File content:

```php
<?php
/**
 * User dashboard shell sidebar. Rendered by page-dashboard.php.
 * Receives $args['active'] — the current tab slug (defaults to 'overview').
 */

if (!defined('ABSPATH')) {
    exit;
}

$active = isset($args['active']) ? (string) $args['active'] : 'overview';

$sections = [
    [
        'label' => 'My BBJ',
        'items' => [
            ['slug' => 'overview',      'label' => 'Overview',      'icon' => 'home'],
            ['slug' => 'activity',      'label' => 'Activity',      'icon' => 'lightning'],
            ['slug' => 'saved',         'label' => 'Saved',         'icon' => 'bookmark'],
            ['slug' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell'],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['slug' => 'profile',  'label' => 'Profile',  'icon' => 'user-circle'],
            ['slug' => 'premium',  'label' => 'Premium',  'icon' => 'star'],
            ['slug' => 'settings', 'label' => 'Settings', 'icon' => 'cog'],
        ],
    ],
    [
        'label' => 'Explore',
        'items' => [
            ['slug' => 'feeds-blog',     'label' => 'Feeds Blog',     'icon' => 'rss'],
            ['slug' => 'power-rankings', 'label' => 'Power Rankings', 'icon' => 'chart-bar'],
            ['slug' => 'leaderboard',    'label' => 'Leaderboard',    'icon' => 'trophy'],
        ],
    ],
];

$current_user = wp_get_current_user();
?>

<aside class="w-52 shrink-0 self-start sticky top-4 bg-white dark:bg-gray-900 border border-stone-200 dark:border-slate-800">
    <div class="px-4 py-3 border-b border-stone-200 dark:border-slate-700">
        <div class="text-xs uppercase tracking-wider text-stone-500">My BBJ</div>
        <div class="text-sm text-stone-800 dark:text-slate-200 mt-0.5 truncate">
            <?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?>
        </div>
    </div>

    <nav class="py-2 px-2" aria-label="<?php esc_attr_e('User dashboard navigation', 'bbj-v2-theme'); ?>">
        <?php foreach ($sections as $section): ?>
            <div class="px-3 pt-3 pb-1 text-xs uppercase tracking-wider text-stone-500">
                <?php echo esc_html($section['label']); ?>
            </div>
            <?php foreach ($section['items'] as $item):
                $is_active = ($item['slug'] === $active);
                $url = $item['slug'] === 'overview'
                    ? esc_url(home_url('/dashboard/'))
                    : esc_url(add_query_arg('tab', $item['slug'], home_url('/dashboard/')));
                $classes = $is_active
                    ? 'bg-primary-500 text-white'
                    : 'text-stone-700 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800';
            ?>
                <a href="<?php echo $url; ?>"
                   class="flex items-center gap-3 px-3 py-2 text-sm font-medium transition-colors <?php echo $classes; ?>"
                   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                    <?php bbj_v2_dashboard_icon($item['icon']); ?>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="px-2 py-2 border-t border-stone-200 dark:border-slate-700">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"
           class="flex items-center gap-2 px-3 py-2 text-sm text-stone-600 hover:bg-stone-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span><?php esc_html_e('Log out', 'bbj-v2-theme'); ?></span>
        </a>
    </div>
</aside>

<?php
/**
 * Helper: print an inline SVG by icon name for the dashboard sidebar.
 * Kept separate from bbj_v2_admin_icon because the icon sets only partly overlap.
 */
if (!function_exists('bbj_v2_dashboard_icon')) {
    function bbj_v2_dashboard_icon(string $name): void
    {
        $paths = [
            'home'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
            'lightning'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
            'bookmark'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>',
            'bell'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
            'user-circle'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
            'star'          => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
            'cog'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
            'rss'           => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"/>',
            'chart-bar'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
            'trophy'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4a5 5 0 0010 0V3M7 21h10M12 17v4M8 3h8M6 7H4a1 1 0 01-1-1V5a2 2 0 012-2h2m12 4h2a1 1 0 001-1V5a2 2 0 00-2-2h-2"/>',
        ];
        $svg = $paths[$name] ?? '';
        echo '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' . $svg . '</svg>';
    }
}
```

- [ ] **Step 2: Verify**

Run: `head -10 wp-content/themes/bbj-v2-theme/template-parts/dashboard/sidebar.php`
Expected: starts with `<?php` and the docblock referencing "User dashboard shell sidebar".

- [ ] **Step 3: PHP lint**

Run: `php -l wp-content/themes/bbj-v2-theme/template-parts/dashboard/sidebar.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/dashboard/sidebar.php
git commit -m "$(cat <<'EOF'
feat(theme): user dashboard shell sidebar partial

Three sections (My BBJ / Account / Explore), 10 nav items, inline SVG icons,
active highlight, and a Log out link at the bottom pointing to wp_logout_url.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Create dashboard overview + stub panes

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-overview.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-stub.php`

- [ ] **Step 1: Create `pane-overview.php`**

File content:

```php
<?php
/**
 * User dashboard shell — Overview pane (the only non-stub pane in v1).
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$first_name   = $current_user->first_name ?: $current_user->display_name ?: $current_user->user_login;
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        <?php printf(
            /* translators: %s: first or display name */
            esc_html__('Welcome back, %s.', 'bbj-v2-theme'),
            esc_html($first_name)
        ); ?>
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        <?php esc_html_e('Your personal BBJ dashboard. Activity, saved posts, and notifications all land here — we\'re wiring them up over the next few sprints.', 'bbj-v2-theme'); ?>
    </p>
</section>
```

- [ ] **Step 2: Verify**

Run: `head -5 wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-overview.php`
Expected: starts with `<?php` and the docblock.

- [ ] **Step 3: Create `pane-stub.php`**

File content:

```php
<?php
/**
 * User dashboard shell — generic "Coming soon" stub pane.
 * Receives $args['tab'] — the current tab slug.
 */

if (!defined('ABSPATH')) {
    exit;
}

$tab_slug = isset($args['tab']) ? (string) $args['tab'] : '';

// Match labels to the dashboard sidebar item table (kept in sync by hand for v1).
$labels = [
    'activity'       => 'Activity',
    'saved'          => 'Saved',
    'notifications'  => 'Notifications',
    'profile'        => 'Profile',
    'premium'        => 'Premium',
    'settings'       => 'Settings',
    'feeds-blog'     => 'Feeds Blog',
    'power-rankings' => 'Power Rankings',
    'leaderboard'    => 'Leaderboard',
];
$label = $labels[$tab_slug] ?? ucwords(str_replace('-', ' ', $tab_slug));
?>

<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">
    <h1 class="font-mainHead text-3xl text-primary-500 dark:text-secondary-500 mb-2">
        <?php printf(
            /* translators: %s: tab label */
            esc_html__('Coming soon — %s.', 'bbj-v2-theme'),
            esc_html($label)
        ); ?>
    </h1>
    <p class="text-stone-600 dark:text-slate-400">
        <?php esc_html_e('This section is part of the shell but hasn\'t been built out yet. Check back after the next sprint.', 'bbj-v2-theme'); ?>
    </p>
</section>
```

- [ ] **Step 4: Verify**

Run: `head -5 wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-stub.php`
Expected: starts with `<?php` and the docblock.

- [ ] **Step 5: PHP lint both**

Run:
```bash
php -l wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-overview.php
php -l wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-stub.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-overview.php wp-content/themes/bbj-v2-theme/template-parts/dashboard/pane-stub.php
git commit -m "$(cat <<'EOF'
feat(theme): user dashboard overview + stub panes

Overview welcomes the user by first name. Stub is a generic "Coming soon — {Label}"
card used by every non-overview tab in the user dashboard shell.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Create `page-dashboard.php` template

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/page-dashboard.php`

- [ ] **Step 1: Create `page-dashboard.php`**

File content:

```php
<?php
bbj_v2_require_logged_in(); // LINE 1 SAFEGUARD — DO NOT WRAP, DO NOT REMOVE.
/**
 * Front-end user dashboard shell template.
 *
 * Auto-assigned to the "Dashboard" WP page via WP's page-{slug}.php hierarchy —
 * no Template Name header needed. Sub-panes selected via ?tab=<slug>.
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_tab = get_query_var('tab');
if (!is_string($active_tab) || $active_tab === '') {
    $active_tab = 'overview';
}

get_header();
?>

<div class="bbj-dashboard-shell min-h-[60vh]">
    <div class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">

        <?php get_template_part('template-parts/dashboard/sidebar', null, [
            'active' => $active_tab,
        ]); ?>

        <section class="flex-1 min-w-0">
            <?php if ($active_tab === 'overview'): ?>
                <?php get_template_part('template-parts/dashboard/pane-overview'); ?>
            <?php else: ?>
                <?php get_template_part('template-parts/dashboard/pane-stub', null, [
                    'tab' => $active_tab,
                ]); ?>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php get_footer(); ?>
```

- [ ] **Step 2: Verify safeguard on line 2**

Run: `head -3 wp-content/themes/bbj-v2-theme/page-dashboard.php`
Expected:
```
<?php
bbj_v2_require_logged_in(); // LINE 1 SAFEGUARD — DO NOT WRAP, DO NOT REMOVE.
/**
```

If not, fix before proceeding.

- [ ] **Step 3: PHP lint**

Run: `php -l wp-content/themes/bbj-v2-theme/page-dashboard.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual browser smoke (dashboard path)**

1. Visit `http://localhost/bbj/dashboard/` logged in as any user (subscriber OR admin both work).
2. Expected: dashboard shell renders, sidebar with three sections on the left (Overview highlighted), main content "Welcome back, {first name}."
3. Click "Notifications". URL becomes `/dashboard/?tab=notifications`. Main pane shows "Coming soon — Notifications." Sidebar highlight moves.
4. Click "Profile" (under ACCOUNT section). URL becomes `/dashboard/?tab=profile`. "Coming soon — Profile."
5. Click "Log out" at the bottom. Expected: logged out, redirected to home.
6. Logged-out, visit `/dashboard/`. Expected: redirect to login with `?redirect_to=...dashboard/`.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/page-dashboard.php
git commit -m "$(cat <<'EOF'
feat(theme): page-dashboard.php template for /dashboard shell

Safeguard (bbj_v2_require_logged_in) on line 2, renders sidebar + pane via
get_template_part based on ?tab= query var. Overview is the only real pane.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Create the header user-icons partial

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/header/user-icons.php`

- [ ] **Step 1: Create the partial**

File content:

```php
<?php
/**
 * Header user icons — shown for logged-in users only.
 * Displays (right-to-left): avatar, bell, pencil (if edit_posts), shield (if manage_options).
 *
 * Rendered by logo-bar.php inside the right-side `justify-self-end` block.
 * Unlike the /admin and /dashboard templates, this partial does NOT require
 * a safeguard — it's an additive nav element, not a protected surface.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    return;
}

$current_user = wp_get_current_user();
$can_admin    = current_user_can('manage_options');
$can_edit     = current_user_can('edit_posts');
$avatar_url   = get_avatar_url($current_user->ID, ['size' => 64]);
?>
<div class="flex items-center gap-2">
    <?php if ($can_admin): ?>
        <a href="<?php echo esc_url(home_url('/admin/')); ?>"
           class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
           title="<?php esc_attr_e('Admin', 'bbj-v2-theme'); ?>"
           aria-label="<?php esc_attr_e('Admin dashboard', 'bbj-v2-theme'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </a>
    <?php endif; ?>

    <?php if ($can_edit): ?>
        <a href="<?php echo esc_url(add_query_arg('tab', 'posts', home_url('/admin/'))); ?>"
           class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
           title="<?php esc_attr_e('New post', 'bbj-v2-theme'); ?>"
           aria-label="<?php esc_attr_e('New post', 'bbj-v2-theme'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
            </svg>
        </a>
    <?php endif; ?>

    <a href="<?php echo esc_url(add_query_arg('tab', 'notifications', home_url('/dashboard/'))); ?>"
       class="inline-flex items-center justify-center w-9 h-9 text-stone-600 hover:text-primary-500 hover:bg-stone-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
       title="<?php esc_attr_e('Notifications', 'bbj-v2-theme'); ?>"
       aria-label="<?php esc_attr_e('Notifications', 'bbj-v2-theme'); ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
    </a>

    <a href="<?php echo esc_url(home_url('/dashboard/')); ?>"
       class="inline-flex items-center justify-center"
       title="<?php esc_attr_e('My dashboard', 'bbj-v2-theme'); ?>"
       aria-label="<?php esc_attr_e('My dashboard', 'bbj-v2-theme'); ?>">
        <img src="<?php echo esc_url($avatar_url); ?>"
             alt=""
             class="w-9 h-9 rounded-full object-cover"
             width="36" height="36"
             loading="lazy"
             decoding="async">
    </a>
</div>
```

- [ ] **Step 2: Verify**

Run: `head -10 wp-content/themes/bbj-v2-theme/template-parts/header/user-icons.php`
Expected: starts with `<?php` and the docblock referencing "Header user icons".

- [ ] **Step 3: PHP lint**

Run: `php -l wp-content/themes/bbj-v2-theme/template-parts/header/user-icons.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/header/user-icons.php
git commit -m "$(cat <<'EOF'
feat(theme): header user-icons partial

Icon row for logged-in users: shield (admins only, → /admin), pencil
(editors+, → /admin?tab=posts), bell (all logged-in, → /dashboard?tab=notifications),
avatar (→ /dashboard). Returns early for logged-out users.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Integrate the user-icons partial into `logo-bar.php`

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php`

The existing logo-bar has a right-side block that shows either a user-name link (logged-in) or a Log In button (logged-out). We preserve that block and insert the icon row to its LEFT (so the icons read: shield / pencil / bell / avatar / [existing user name link]). For logged-out users nothing changes — the partial returns early.

- [ ] **Step 1: Read the current logo-bar block to confirm the edit target**

Run: `grep -n 'justify-self-end' wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php`
Expected output: one line number, around line 37, pointing at `<div class="justify-self-end">`.

If the structure has changed since this plan was written, re-inspect the file before editing.

- [ ] **Step 2: Edit logo-bar.php — wrap the right-side block in a flex container that also holds the icons**

Find this block in `wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php` (currently around lines 37-53):

```php
        <div class="justify-self-end">
            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(admin_url('profile.php')); ?>"
                   class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="text-sm font-osw uppercase tracking-wider"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                </a>
            <?php else : ?>
                <button type="button"
                        data-bbj-auth-open="login"
                        class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="text-sm font-osw uppercase tracking-wider"><?php esc_html_e('Log In', 'bbj-v2-theme'); ?></span>
                </button>
            <?php endif; ?>
        </div>
```

Replace it with:

```php
        <div class="justify-self-end flex items-center gap-3">
            <?php get_template_part('template-parts/header/user-icons'); ?>

            <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo esc_url(admin_url('profile.php')); ?>"
                   class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="text-sm font-osw uppercase tracking-wider"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                </a>
            <?php else : ?>
                <button type="button"
                        data-bbj-auth-open="login"
                        class="flex items-center gap-1 text-gray-700 dark:text-gray-200 hover:text-primary-500 transition">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="text-sm font-osw uppercase tracking-wider"><?php esc_html_e('Log In', 'bbj-v2-theme'); ?></span>
                </button>
            <?php endif; ?>
        </div>
```

Two changes only:
1. `<div class="justify-self-end">` becomes `<div class="justify-self-end flex items-center gap-3">`.
2. The first child inside is now `<?php get_template_part('template-parts/header/user-icons'); ?>` (which returns early for logged-out users, so it's effectively a no-op when not logged in).

- [ ] **Step 3: PHP lint**

Run: `php -l wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual browser smoke**

1. Logged out, visit `http://localhost/bbj/`. Right side of the logo bar shows the Log In button. No icons. ✓
2. Logged in as a subscriber (no `manage_options`), visit `/`. Right side shows: bell + avatar + user-name link. No shield, no pencil. ✓
3. Logged in as an editor (`edit_posts` but not `manage_options`), visit `/`. Right side shows: pencil + bell + avatar + user-name link. ✓
4. Logged in as admin, visit `/`. Right side shows: shield + pencil + bell + avatar + user-name link. ✓
5. Click shield → lands on `/admin` → renders admin shell. ✓
6. Click avatar → lands on `/dashboard` → renders dashboard shell. ✓
7. Click bell → lands on `/dashboard/?tab=notifications` → "Coming soon — Notifications." ✓
8. Click pencil → lands on `/admin/?tab=posts` → "Coming soon — Posts." ✓

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/bbj-v2-theme/template-parts/header/logo-bar.php
git commit -m "$(cat <<'EOF'
feat(theme): integrate user-icons row into logo-bar

Adds get_template_part call for user-icons inside the right-side
justify-self-end block, switched to a flex container so the icons
sit to the left of the existing user-name link. Logged-out behavior
unchanged (the partial returns early).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Final acceptance smoke + follow-up note

No code in this task. Walk the 10 acceptance tests from the spec and document any follow-ups.

- [ ] **Step 1: Run each acceptance test**

For each, mark PASS/FAIL. If FAIL, fix and re-run (create a follow-up task/commit as needed before moving on).

1. **Logged out hits `/admin`:** → redirects to login. ☐ PASS
2. **Logged out hits `/dashboard`:** → redirects to login. ☐ PASS
3. **Logged-in subscriber hits `/admin`:** → 403. ☐ PASS
4. **Logged-in subscriber hits `/dashboard`:** → renders overview. ☐ PASS
5. **Logged-in admin hits `/admin`:** → renders admin overview. ☐ PASS
6. **Logged-in admin hits `/admin?tab=settings`:** → "Coming soon — Settings" stub; sidebar highlights Settings. ☐ PASS
7. **Header icon row:** → visible only for logged-in users; shield only for admins; pencil only for users with `edit_posts`; bell + avatar for all logged-in. ☐ PASS
8. **Page titles:** → `/admin` shows "Admin - {site name}", `/dashboard` shows "Dashboard - {site name}". ☐ PASS
9. **No PHP warnings/notices** in either rendered page. ☐ PASS
   - Run: `curl -s http://localhost/bbj/admin/?tab=settings | grep -iE "notice:|warning:|fatal" | head -3` (after logging in via browser cookie if curl test is inconvenient, at minimum do a visible inspect in DevTools)
10. **Mobile viewport:** acceptable to have sidebar stack or break. Desktop-only declared in spec. ☐ ACKNOWLEDGED

- [ ] **Step 2: Git log check**

Run: `git log --oneline staging...HEAD | head -20`

Expected: 9 commits from this sprint (Tasks 1, 3, 4, 5, 6, 7, 8, 9, 10 each commit — Tasks 2 and 11 have none). Review commit messages for consistency.

- [ ] **Step 3: Update the forward roadmap memory**

Edit `C:\Users\sbeli\.claude\projects\C--xampp-htdocs-bbj\memory\project_bbj_v2_theme_state.md` — move the "Admin shell" item out of "Next, in order" and add a new "Shipped on staging" bullet describing the admin + dashboard shell landing. This is a memory edit, not a git commit.

Specifically:
- Under "Shipped on staging (local smoke green)", add:
  > **Front-end admin + user dashboard shells** — `/admin` and `/dashboard` pages with sidebar, overview pane, stub sub-panes. Header icon row (shield/pencil/bell/avatar) for logged-in users. Safeguard helper discipline codified. Feature panes are Coming-soon stubs; next sprints fill them in one at a time.
- Under "Next, in order", remove the "Admin / user dashboard shell — where front-end site settings live..." bullet and replace with the next real sprint — likely **Site Settings page** and **Spoiler Bar Manager** as separate sprints slotting into the shell.

- [ ] **Step 4: No commit**

Task 11 has no code changes — it's verification + bookkeeping. The shell is shipped once all 10 acceptance tests pass and the memory is updated.

---

## Spec coverage checklist

- [x] Safeguard helpers → Task 1
- [x] `tab` query var → Task 1
- [x] WP pages provisioned → Task 2
- [x] `page-admin.php` template → Task 5
- [x] `page-dashboard.php` template → Task 8
- [x] Admin sidebar partial → Task 3
- [x] Admin overview pane → Task 4
- [x] Admin stub pane → Task 4
- [x] Dashboard sidebar partial → Task 6
- [x] Dashboard overview pane → Task 7
- [x] Dashboard stub pane → Task 7
- [x] Header user-icons partial → Task 9
- [x] `logo-bar.php` integration → Task 10 (spec said `header.php`; actual host is `logo-bar.php` — corrected)
- [x] 10 acceptance tests → Task 11

## Deferred (out of this plan, per spec)

- Pretty URL rewrites for sub-panes (future plan, if warranted)
- Mobile responsive sidebar (future plan)
- Actual Settings form UI (future plan: site-settings)
- Actual Spoiler Bar Manager UI (future plan: spoiler-bar-manager)
- Permission system beyond WP caps (future plan)
- Role simulator (future plan)
- Custom post editor for the dashboard (future plan)
