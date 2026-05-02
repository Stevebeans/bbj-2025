# Admin Settings Pane — Design

**Date:** 2026-04-22
**Sprint:** Sprint A (part 2 — completes Sprint A after the Spoiler Bar editor shipped)
**Status:** Design approved, ready for implementation plan

---

## Problem

The old wp-admin page at `/wp-admin/admin.php?page=bbj-v2-settings` owns the single most important global setting on the site: `bbj_v2_current_season`. Switching seasons at the start of a new BB season requires visiting that old page in the old admin theme. This spec ports it into the new flat-editorial admin shell at `/admin?tab=settings`, adds a read-only info card with a quick-jump link to the active season's spoiler bar, and drops the unused `bbj_v2_season_active` toggle (never wired up to any consumer).

## Goals

- `/admin?tab=settings` renders a clean card-based pane in the new admin shell
- Current season selector + Save (matches what the old wp-admin page did)
- Read-only info card: shows the current season's full_name, abbreviation, dates
- Quick-jump link: "Edit this season's spoiler bar →" deep-links to `/admin?tab=seasons&edit=<id>#spoiler`
- Cache-safe: switching seasons busts spoiler-bar cache for both old and new season ids

## Non-goals

- `bbj_v2_season_active` toggle (no code consumes it; YAGNI)
- Additional "future knobs" (add later when a concrete need appears)
- Season overlap validation or date-bounds enforcement
- Preserving the old `/wp-admin/admin.php?page=bbj-v2-settings` page (stays live, untouched, for rollback)

---

## Architecture

Same split as the rest of the admin shell: pane template in the theme, save handler in the plugin. No schema changes. No new REST.

### New files

```
wp-content/themes/bbj-v2-theme/
  template-parts/admin/pane-settings.php       ← the pane

wp-content/plugins/bbj-v2/includes/Actions/form-submits/
  set-current-season.php                        ← new bbj_v2_set_current_season() handler
```

### Modified files

```
wp-content/themes/bbj-v2-theme/page-admin.php
  — extend dispatcher: add an elseif for tab=settings → pane-settings

wp-content/plugins/bbj-v2/includes/Actions/action-list.php
  — register admin_post_bbj_v2_set_current_season + its loader wrapper
```

### Reused as-is

- `bbj_v2_get_seasons('start_date', 'DESC')` — sort matches the list-page convention
- `bbj_v2_get_season_by_id()` — read the currently-selected season for the info card
- `bbj_spoiler_bar_bust_cache($season_id)` — called twice on save (old id + new id) to be defensive
- `bbj_v2_require_admin()` — already on line 1 of `page-admin.php`, guards all panes

---

## Data flow

### Pane load (`/admin?tab=settings`)

1. `page-admin.php` → `pane-settings.php`
2. Read `get_option('bbj_v2_current_season', '')`
3. Load `$seasons = bbj_v2_get_seasons('start_date', 'DESC')`
4. Load `$current_season = $current_season_id ? bbj_v2_get_season_by_id((int) $current_season_id) : null` (array or null)
5. Render:
   - Header + success notice (if `?updated=1`)
   - Info card: if `$current_season` exists, show name + abbreviation + formatted date range + "Edit spoiler bar →" button. Otherwise show "No current season selected yet."
   - Form: `<select name="bbj_v2_season">` populated from `$seasons`, currently-selected option marked `selected`; Save button

### Save

1. Form POSTs to `admin-post.php?action=bbj_v2_set_current_season` with nonce `bbj_v2_set_current_season_nonce` / action `bbj_v2_set_current_season_action`
2. Handler `bbj_v2_set_current_season()`:
   - Verify nonce + `current_user_can('manage_options')`
   - `$old_id = (int) get_option('bbj_v2_current_season', 0)`
   - `$new_id = absint($_POST['bbj_v2_season'])`
   - Validate `$new_id > 0` and `bbj_v2_get_season_by_id($new_id)` returns a row; `wp_die` otherwise
   - `update_option('bbj_v2_current_season', $new_id)`
   - Bust cache for both seasons to avoid stale spoiler-bar HTML keyed by either id:
     - `if ($old_id > 0 && $old_id !== $new_id) bbj_spoiler_bar_bust_cache($old_id);`
     - `bbj_spoiler_bar_bust_cache($new_id);`
   - Redirect back to `/admin?tab=settings&updated=1`
3. Note: the public spoiler-bar shortcode reads `get_option('bbj_v2_current_season')` at render time and keys its cache by that season_id. So after the option flips, the next render uses a different cache key — old cached HTML for the prior season naturally stops being read. The cache-bust calls are defensive, not critical.

### Notices

- `?updated=1` → green "Current season updated." (auto-dismiss ~3s, matches existing admin notice pattern)

---

## Components & markup

Flat-editorial aesthetic — same wrapper and typography used in `pane-overview.php`, `pane-seasons.php`, `pane-seasons-edit.php`.

### Layout

```
┌ Settings ───────────────────────────────────────┐
│                                                 │
│  [✓ Current season updated.]  (if ?updated=1)   │
│                                                 │
│  CURRENT SEASON                                 │
│  ┌──────────────────────────────────────────┐  │
│  │ Big Brother 27  BB27                     │  │
│  │ Jul 10, 2025 – TBD                       │  │
│  │ [ Edit spoiler bar → ]                   │  │
│  └──────────────────────────────────────────┘  │
│                                                 │
│  Change current season                          │
│  [ Big Brother 27  ▾ ]  [ Save ]                │
│                                                 │
└─────────────────────────────────────────────────┘
```

- Outer wrapper: `<section class="p-6 bg-white border border-stone-200 dark:bg-gray-900 dark:border-slate-800">` (same as other panes)
- H1: `font-mainHead text-3xl text-primary-500 dark:text-secondary-500`
- Info card: bordered + slightly-grey background (`bg-stone-50 dark:bg-slate-900/40`)
- "Edit spoiler bar →" button: styled as a primary CTA (`bg-primary-500 hover:bg-primary-600 text-white`)
- Dropdown: standard `<select>` with the same styling as the seasons-edit SeasonSwitcher
- Save button: primary, matches other Save buttons in the admin
- Empty-state (no current season): replace the info card's content with muted text "No current season selected yet." and hide the edit link

---

## Error handling & edge cases

### Validation
- `$new_id <= 0` or no matching season row → `wp_die('Invalid season selection')`
- Missing nonce or bad capability → `wp_die('Permission check failed')`

### Edge cases

- **Current season id points at a deleted row** → `bbj_v2_get_season_by_id()` returns null; pane shows "(Season not found)" in the info card; dropdown still lets user pick a new one.
- **User saves the same season that's already current** → `update_option` returns false (no change); handler still fires cache-bust for `$new_id` only (skip-if-equal guard on old id prevents double-bust). Redirect succeeds; notice displays.
- **No seasons exist** → dropdown renders empty. Save button is disabled client-side via the `disabled` attribute when the dropdown has no options. Edge case is possible in a fresh install but not in real operation.
- **Two admins save different seasons concurrently** → last write wins. Not worth solving at 2-admin scale.

---

## Testing plan

Manual on staging. No automated tests (matches repo convention).

1. `/admin/?tab=settings` renders the pane. Info card shows the current season's name, abbreviation, dates.
2. Dropdown shows all seasons, sorted newest-first. Currently-selected season is marked.
3. Change dropdown, Save → `?updated=1`, green notice appears and auto-dismisses. Info card reflects new selection.
4. Click "Edit spoiler bar →" → lands on `/admin/?tab=seasons&edit=<id>#spoiler` for the selected season.
5. Homepage spoiler bar reflects the new current season after cache expires (300s) OR immediately after clicking Purge Cache on the spoiler bar tab.
6. Manually clear `bbj_v2_current_season` option in phpMyAdmin, reload the pane → info card shows "No current season selected yet." No edit link. Pick a season, Save → normal flow resumes.
7. Non-admin user hits `/admin/?tab=settings` → redirected to wp-login.php (existing `bbj_v2_require_admin()`).

---

## References

- Old wp-admin settings page (source to port from): `wp-content/plugins/bbj-v2/includes/Public/bbj-v2-settings.php`
- Admin dispatcher: `wp-content/themes/bbj-v2-theme/page-admin.php`
- Action registration pattern: `wp-content/plugins/bbj-v2/includes/Actions/action-list.php`
- Roadmap: `.claude/project/roadmap.md` (Sprint A)
