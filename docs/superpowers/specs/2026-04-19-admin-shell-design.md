# Front-End Admin + User Dashboard Shell — Design Spec

**Date:** 2026-04-19
**Branch:** `staging`
**Scope:** Build the unbranded shells for a front-end admin area (`/admin`) and a front-end user dashboard (`/dashboard`) inside the `bbj-v2-theme`. This spec delivers the **shell only** — navigation, layout, safeguards, and a single "Overview" pane placeholder. All other sub-panes are stubs that say "Coming soon." Each section is tuned in later sprints.

## Goals

- Unified, site-branded admin + member experience, visually consistent with the rest of the bbj-v2-theme (flat editorial aesthetic).
- Replace the "wp-admin feels scattered and unfriendly" complaint with a single entry point per role.
- Establish mandatory security discipline for front-end admin pages (a helper function called on line 1 of every admin template).
- Leave existing wp-admin plugin pages (in `bigbrotherjunkies-data`) fully functional. They migrate in later, one at a time, not in this sprint.

## Non-goals

- **No real data in any pane.** The Overview pane shows a placeholder welcome card. Every other sidebar link lands on a stub pane.
- **No Settings form, no Spoiler Bar Manager UI.** Those are separate sprints that slot into this shell.
- **No pretty-URL sub-page routing.** Sub-panes are reached via `?tab=<slug>` query param. Pretty permalinks (`/admin/posts`, `/admin/settings`) are a future enhancement.
- **No permission system.** We use `current_user_can('manage_options')`, `current_user_can('edit_posts')`, and `is_user_logged_in()`. A richer per-feature permission model (à la bbj-app's `blog_review` / `feed_updates` / `comment_moderation` capability keys) is out of scope.
- **No mobile hamburger.** Desktop layout only for v1. Mobile is a stretch we'll add once the shell proves itself.
- **No role simulator** ("Preview as" dropdown from bbj-app). Out of scope.
- **No new React components.** Pure PHP templates + vanilla JS for anything interactive (there's very little — just icon rows).

## Architecture

### Routes and templates

| Route | WordPress page | Theme template | Safeguard on line 1 |
|---|---|---|---|
| `/admin` | A new WP page titled "Admin", slug `admin` | `page-admin.php` | `bbj_v2_require_admin()` |
| `/dashboard` | A new WP page titled "Dashboard", slug `dashboard` | `page-dashboard.php` | `bbj_v2_require_logged_in()` |

Both use WordPress pages (not custom rewrite rules) so the URLs are managed in the WP admin, permalinks work out of the box, and we don't have to flush rewrite rules.

The pages are created once by a one-shot provisioning helper run during implementation: a small PHP snippet (executed via a throwaway `seed-admin-pages.php` at repo root that `require`s `wp-load.php`, creates the pages idempotently via `wp_insert_post`, prints their IDs, and is deleted before commit). Pattern matches the Task 2 taxonomy seed from the homepage redesign sprint.

### Sub-pane routing

Sub-panes within each shell are addressed by `?tab=<slug>`:

- `/admin?tab=posts`
- `/admin?tab=settings`
- `/dashboard?tab=notifications`

The template reads `get_query_var('tab')` (falling back to `$_GET['tab']` — we register `tab` via `query_vars` filter so WP doesn't strip it). If the tab is recognized, the template includes the matching partial. If not, it falls back to the Overview pane.

For this sprint, every sub-pane slug maps to a single shared stub partial (`template-parts/admin/pane-stub.php` and `template-parts/dashboard/pane-stub.php`) that renders a "Coming soon — {tab name}" placeholder. The Overview tab is the only one with a dedicated partial.

### Safeguard helpers

Two new functions in `functions.php`:

```php
function bbj_v2_require_admin(): void {
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(home_url(add_query_arg([])))); // current URL
        exit;
    }
    if (!current_user_can('manage_options')) {
        status_header(403);
        wp_die('You do not have permission to access this page.', 'Access Denied', ['response' => 403]);
    }
}

function bbj_v2_require_logged_in(): void {
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(home_url(add_query_arg([]))));
        exit;
    }
}
```

**Rule enforced via memory (`feedback_admin_page_safeguard.md`):** every `/admin/*` and `/dashboard/*` template and every form handler tied to the admin UI MUST call one of these on line 1, unconditionally.

### Query var registration

Add `tab` to WP's allowed query vars in the theme:

```php
add_filter('query_vars', function ($vars) {
    $vars[] = 'tab';
    return $vars;
});
```

This also lets the sidebar's active-state logic compare `get_query_var('tab')` against the expected slug without parsing `$_GET` directly.

## Component structure

### File tree (new files)

```
wp-content/themes/bbj-v2-theme/
├── page-admin.php                              (new)
├── page-dashboard.php                           (new)
├── functions.php                                (edit: add two safeguards + query_vars filter)
├── header.php                                   (edit: add user icon row for logged-in users)
└── template-parts/
    ├── admin/
    │   ├── sidebar.php                          (new)
    │   ├── pane-overview.php                    (new — placeholder welcome card)
    │   └── pane-stub.php                        (new — "Coming soon — {tab}")
    ├── dashboard/
    │   ├── sidebar.php                          (new)
    │   ├── pane-overview.php                    (new — placeholder welcome card)
    │   └── pane-stub.php                        (new — "Coming soon — {tab}")
    └── header/
        └── user-icons.php                       (new — shield/pencil/bell/avatar row)
```

### `page-admin.php` shape

```php
<?php
bbj_v2_require_admin(); // LINE 1 AFTER OPENING TAG. NO EXCEPTIONS.
get_header();

$active_tab = get_query_var('tab') ?: 'overview';
?>

<main class="bbj-admin-shell min-h-screen bg-stone-50">
    <div class="mx-auto max-w-screen-xl px-4 py-6 flex gap-6">
        <?php get_template_part('template-parts/admin/sidebar', null, ['active' => $active_tab]); ?>

        <section class="flex-1 min-w-0">
            <?php if ($active_tab === 'overview'): ?>
                <?php get_template_part('template-parts/admin/pane-overview'); ?>
            <?php else: ?>
                <?php get_template_part('template-parts/admin/pane-stub', null, ['tab' => $active_tab]); ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php get_footer(); ?>
```

`page-dashboard.php` mirrors this but uses `bbj_v2_require_logged_in()` and the `dashboard/` partials.

### Admin sidebar links (v1)

Rendered from a single array in `template-parts/admin/sidebar.php`, ported from `bbj-app/src/app/admin/layout.jsx` TAB list, trimmed to what makes sense now:

| Slug | Label | Icon (heroicon-ish SVG, inline) | Requires cap |
|---|---|---|---|
| `overview` | Overview | home | — |
| `posts` | Posts | document-text | `edit_posts` |
| `feed-updates` | Feed Updates | rss | `edit_posts` |
| `comments` | Comments | chat | `moderate_comments` |
| `players` | Players | users | `manage_options` |
| `seasons` | Seasons | calendar | `manage_options` |
| `announcements` | Announcements | megaphone | `manage_options` |
| `content-engine` | Content | pencil-square | `manage_options` |
| `users` | Users | users | `manage_options` |
| `stats` | Stats | chart-bar | `manage_options` |
| `settings` | Settings | cog | `manage_options` |
| `spoiler-bar` | Spoiler Bar | shield-check | `manage_options` |

Plus, at the bottom of the sidebar:
- "Back to Site" link → home URL
- Currently-logged-in user display ("Signed in as **{display_name}**")

**Note on "Requires cap" column:** in v1 the page-level gate (`bbj_v2_require_admin()` → `manage_options`) means anyone who reaches `/admin` already has `manage_options`, which supersets `edit_posts` and `moderate_comments`. So in practice all sidebar items render for any admin. The "Requires cap" column documents the design intent for when a finer-grained gate (e.g., letting editors into `/admin`) replaces the single `manage_options` check — at that point the sidebar will filter items per-cap.

Sidebar style:
- No card/shadow — flat, aligns with homepage/single aesthetic
- Width: ~13rem (w-52 per bbj-app reference)
- Sticky top-4 self-start on desktop
- Section separator: `border-b border-stone-200` + `text-xs uppercase tracking-wider text-stone-500` for group labels (we only use one group "Admin" in v1; grouping into Content / Revenue / Community / Setup comes when we actually have more real panes)
- Active state: `bg-primary-500 text-white` on the current tab, `text-stone-700 hover:bg-stone-100` otherwise

### User dashboard sidebar links (v1)

| Slug | Label | Section | Icon |
|---|---|---|---|
| `overview` | Overview | MY BBJ | home |
| `activity` | Activity | MY BBJ | lightning |
| `saved` | Saved | MY BBJ | bookmark |
| `notifications` | Notifications | MY BBJ | bell |
| `profile` | Profile | ACCOUNT | user-circle |
| `premium` | Premium | ACCOUNT | star |
| `settings` | Settings | ACCOUNT | cog |
| `feeds-blog` | Feeds Blog | EXPLORE | rss |
| `power-rankings` | Power Rankings | EXPLORE | chart-bar |
| `leaderboard` | Leaderboard | EXPLORE | trophy |

Plus at bottom, separated from the nav:
- "Logout" link → `wp_logout_url(home_url())`

Section headers use the same style as the admin sidebar: uppercase, tracking-wider, text-stone-500.

### Overview pane placeholders

Admin Overview (`template-parts/admin/pane-overview.php`) — one card:

> **Welcome, {display_name}.** This is your admin dashboard. Pick a section from the sidebar to get started. (Feature panes coming soon.)

User Overview (`template-parts/dashboard/pane-overview.php`) — one card:

> **Welcome back, {first_name or display_name}.** Your personal BBJ dashboard. Activity, saved posts, and notifications land here soon.

Both use a flat `<section>` with `p-6 bg-white border border-stone-200` — no shadow, matching the editorial aesthetic. Font sizing follows the same osw/yanone heading pattern used on the homepage.

### Stub pane

`pane-stub.php` receives a `tab` slug and renders:

> **Coming soon — {Tab Label}.** This section is part of the shell but hasn't been built out yet.

It can look up the label from the same array the sidebar uses (we can either duplicate or extract into a shared helper — the implementation plan decides).

## Header changes

Current `header.php` already handles login/logout CTAs for logged-out users and shows a user role dropdown for logged-in users. We ADD a new icon row for logged-in users, rendered via a new partial `template-parts/header/user-icons.php`.

### Icon row composition

Rendered in `header.php` only if `is_user_logged_in()`:

1. **Shield icon** → `/admin` (displayed only if `current_user_can('manage_options')`)
   - Title attr: "Admin"
2. **Pencil icon** → `/admin?tab=posts` (displayed only if `current_user_can('edit_posts')`)
   - Title attr: "New post"
   - For v1 this just links into the posts stub pane. Actual new-post flow comes later.
3. **Bell icon** → `/dashboard?tab=notifications` (always visible to logged-in users)
   - Title attr: "Notifications"
   - No unread badge yet — placeholder visual
4. **Avatar** → `/dashboard` (always visible to logged-in users)
   - Uses `get_avatar_url(get_current_user_id(), ['size' => 32])`
   - Title attr: "My dashboard"

All four icons use a shared minimal inline-SVG approach (heroicon-style, stroke 1.5, w-5/h-5). Avatar is an `<img>` wrapped in the same pill container as the others.

### Logged-out behavior

Unchanged. Existing login/register buttons stay. The icon row simply isn't rendered.

### Layout

The icon row is inserted into the existing `header.php` nav bar, right-aligned, immediately before the existing user role dropdown. The dropdown stays (for now) — icon row is additive, not replacing. The implementation plan specifies the exact line range to edit based on the current `header.php` structure.

## Data flow

Minimal. Everything renders from:
- `wp_get_current_user()` → display name, first name, avatar URL
- `current_user_can()` → capability checks for rendering conditional icons/sidebar items
- `get_query_var('tab')` → active pane selector

No custom SQL, no REST calls, no options reads. This is purely a structural shell.

## Error handling

- **Not logged in hits `/admin`:** `bbj_v2_require_admin()` → `wp_safe_redirect(wp_login_url(...))` with `redirect_to` = current URL, so login returns them here.
- **Logged-in non-admin hits `/admin`:** `wp_die(403)` with "Access Denied" message. Clean, not a redirect loop.
- **Not logged in hits `/dashboard`:** same redirect to login with return URL.
- **Invalid `tab` slug:** falls through to the generic stub pane. No error, no 404 — the sidebar still shows, the main pane says "Coming soon — {raw slug, escaped}."
- **WP pages "Admin" or "Dashboard" don't exist:** request 404s via normal WP routing. The implementation plan includes a one-shot provisioning step to create them.

## Testing

Local smoke for this shell:

1. **Logged out hits `/admin`:** redirects to login. ✓
2. **Logged out hits `/dashboard`:** redirects to login. ✓
3. **Logged-in subscriber hits `/admin`:** 403. ✓
4. **Logged-in subscriber hits `/dashboard`:** renders overview. ✓
5. **Logged-in admin hits `/admin`:** renders admin overview. ✓
6. **Logged-in admin hits `/admin?tab=settings`:** renders "Coming soon — Settings" stub. Sidebar highlights Settings. ✓
7. **Header icon row:** visible only for logged-in users. Shield only for admins. Pencil only for users with `edit_posts`. Bell + avatar for everyone logged in. ✓
8. **Page titles:** `/admin` shows "Admin - {site name}", `/dashboard` shows "Dashboard - {site name}". (These come from the WP page titles since we're using real WP pages.)
9. **No PHP warnings/notices** in either rendered page.
10. **Mobile viewport** — acceptable to have sidebar stack or break. Desktop-only is declared. Don't fail the smoke if mobile looks rough.

## Risks + mitigations

| Risk | Mitigation |
|---|---|
| Developer forgets `bbj_v2_require_admin()` on a new admin template, exposing it | Memory rule (`feedback_admin_page_safeguard.md`); code review discipline; consider a linter/grep check in CI later |
| WP page "Admin" or "Dashboard" gets deleted from WP admin by accident | Route 404s. Recovery is recreating the page with the right slug. A future hardening step could auto-create them on theme activation. |
| `tab` query var collides with another theme/plugin using the same var | Low risk — `tab` is generic but we own the query_vars filter. If a collision appears, rename to `admin_tab` / `dashboard_tab`. |
| Existing header.php role dropdown conflicts with new icon row | Implementation plan specifies exact header edit location; smoke test covers both logged-in and logged-out paths. |
| Future pretty URLs (`/admin/posts`) require rewrite changes that break the `?tab=` URLs | Rewrite rules can preserve query param fallback, and old query-param URLs are only used internally in v1. Low migration risk. |

## Deferred / future

- Pretty permalinks for sub-panes (`/admin/posts`, `/admin/settings`, `/dashboard/notifications`).
- Mobile responsive sidebar (hamburger + overlay).
- Actual feature panes: Settings form, Spoiler Bar Manager UI, Notifications list, Activity feed, etc.
- Permission system beyond simple WP caps (bbj-app-style `blog_review`, `feed_updates`, etc.).
- Role simulator ("Preview as") from bbj-app.
- Dark-mode polish across the shell.
- Custom post editor (for the "old lady friendly" non-Gutenberg editor eventually living under `/admin?tab=posts/new`).

## Acceptance

A signed-off v1 means:

- `/admin` renders the shell with sidebar + Overview pane for an admin.
- `/dashboard` renders the shell with sidebar + Overview pane for any logged-in user.
- All 10 sidebar items on each shell are clickable; non-Overview items land on a "Coming soon — X" pane.
- Header icon row (shield conditional / pencil conditional / bell / avatar) appears for logged-in users.
- Safeguard helpers exist and are called on line 1 of the two new templates.
- No regressions in existing pages (homepage, single, page) — they still render.
- Existing wp-admin plugin pages under BBJ Data / BBJ Ad Manager / BBJ Mailing are untouched.
