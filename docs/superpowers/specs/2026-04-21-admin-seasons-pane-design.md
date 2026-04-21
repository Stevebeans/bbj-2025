# Admin Seasons Pane — Design

**Date:** 2026-04-21
**Sprint:** Pre-Sprint A (unblocks Sprint A — Site Settings + Spoiler Bar Manager)
**Status:** Design approved, ready for implementation plan

---

## Problem

Editing a season's spoiler bar on the live site currently takes ~4 minutes of hunting through `wp-admin` to find the hidden submenu page at `/wp-admin/admin.php?page=bbj-v2-edit-season`. The new front-end admin shell (`/admin?tab=seasons`) is stubbed with a "Coming soon" placeholder. This spec ships the seasons list + basic edit form inside the new shell, unblocking Sprint A (which fills the Spoiler Bar tab).

## Goals

- List all seasons at `/admin?tab=seasons` in a flat-editorial styled table
- Make the "current season" visually obvious in 1 second
- Add Season button that creates a draft + drops the user into the edit form
- Edit form with a 3-tab layout (Spoiler Bar / Season Info / Player Photos), matching the Next.js reference at `bbj-app\src\app\bigbrother-seasons\[slug]\edit\`
- Only the Season Info tab has working fields in this sprint; the Info tab itself is also a skeleton (BasicInfo + Dates live; Images / Winners / Roster are in-tab stubs)

## Non-goals

- Delete season action
- Pagination
- Player roster add/remove (`PlayersSection` from Next.js reference)
- Images / Winners field editing
- Spoiler Bar per-player editing (Sprint A)
- Player Photos scanner (Sprint A or later)
- Inline field validation, autosave, REST API
- Search / filter on list page
- Mobile layout for edit page (desktop-only for v1, matches rest of admin shell)

---

## Architecture

**Split:** templates in the **theme** (where the admin shell lives), write-handlers in the **plugin** (where all BBJ DB writes live). No schema changes. No new REST endpoints.

### New files

```
wp-content/themes/bbj-v2-theme/
  template-parts/admin/
    pane-seasons.php              ← list view (default for tab=seasons)
    pane-seasons-edit.php         ← edit view (when ?edit=<id> present)
    partials/
      seasons-list-row.php        ← one row in the list
      seasons-edit-tabs.php       ← the Spoiler / Info / Photos tab nav
      seasons-edit-info.php       ← Info tab body (BasicInfo + Dates + stubs)
      seasons-edit-stub.php       ← reusable "Coming in Sprint A" in-tab stub

wp-content/plugins/bbj-v2/includes/Actions/form-submits/
  create-season.php               ← new bbj_v2_create_season() handler
```

### Modified files

```
wp-content/themes/bbj-v2-theme/page-admin.php
  — extend tab dispatcher: if tab=seasons AND ?edit=<id>, load pane-seasons-edit;
    else load pane-seasons

wp-content/plugins/bbj-v2/includes/Actions/action-list.php
  — register new admin_post_bbj_v2_create_season action

wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php
  — change the redirect target in bbj_v2_edit_season_info() from
    /wp-admin/admin.php?page=bbj-v2-edit-season to
    /admin?tab=seasons&edit=<id>&updated=1
  — add companion-post sync: after writing to wp_bbj_seasons, call
    wp_update_post(['ID' => $post_id, 'post_title' => $full_name,
                    'post_status' => 'publish']) so the draft created by
    bbj_v2_create_season() publishes with a real title on first save
  — confirm existing cache-bust only fires when saved season is current
    (guard bbj_spoiler_bar_bust_cache($season_id) with an option check)
```

### Reused as-is

- `bbj_v2_edit_season_info()` — save handler for the Info tab
- `bbj_v2_get_seasons($order_by, $order)` — list query
- `bbj_v2_get_season_by_id($id)` — edit page query
- `bbj_spoiler_bar_bust_cache($season_id)` — cache-bust on save when the edited season is current
- `bbj_v2_require_admin()` — line-1 safeguard in both panes
- `wp_bbj_seasons` custom table + `bigbrother-seasons` CPT + Meta Box custom-table storage

### URL structure

- `/admin?tab=seasons` — list
- `/admin?tab=seasons&edit=<id>` — edit
- `/admin?tab=seasons&edit=<id>&created=1` — post-create landing
- `/admin?tab=seasons&edit=<id>&updated=1` — post-save landing
- `/admin?tab=seasons&error=not_found` — invalid edit id
- Sub-tab on edit page held in URL hash (`#spoiler`, `#info`, `#photos`) — no server involvement
- Default sub-tab is `#info` for pre-sprint (don't land on the stubbed `#spoiler`)

### Handler pattern

Form POST → `admin-post.php?action=bbj_v2_*` with WP nonce. No REST, no fetch.

---

## Data flow

### List page load (`/admin?tab=seasons`)

1. `page-admin.php` calls `bbj_v2_require_admin()` → guard
2. Dispatcher sees `tab=seasons` + no `edit` param → loads `pane-seasons.php`
3. Pane calls `bbj_v2_get_seasons('season_number', 'DESC')` → array of rows
4. For each row, compute status on the fly:
   - `$current_id = (int) get_option('bbj_v2_current_season')`
   - `Current` if `$row->id === $current_id`
   - `Draft` if `$row->full_name === ''`
   - `Upcoming` if `start_date > today`
   - `Completed` if `end_date < today`
   - `Active` otherwise
5. Winner name: if `$row->season_winner` set, `get_the_title($row->season_winner)`; else em-dash
6. Render list + Add Season button

### "Add Season" click

1. Form POSTs to `admin-post.php?action=bbj_v2_create_season` with nonce
2. `bbj_v2_create_season()` handler:
   - Verify nonce + `current_user_can('manage_options')`
   - `wp_insert_post(['post_type' => 'bigbrother-seasons', 'post_status' => 'draft', 'post_title' => 'New Season'])` → `$post_id`
   - `$wpdb->insert('wp_bbj_seasons', ['post_id' => $post_id, 'full_name' => ''])` → `$season_id`
   - Redirect to `/admin?tab=seasons&edit=<season_id>&created=1`

### Edit page load (`/admin?tab=seasons&edit=<id>`)

1. Guard → dispatcher → `pane-seasons-edit.php`
2. Validate `edit` param: `absint()`, then `bbj_v2_get_season_by_id($id)` — if null, redirect to `/admin?tab=seasons&error=not_found`
3. Render:
   - Breadcrumb: `Seasons / Edit <full_name or "New Season">`
   - SeasonSwitcher: plain `<select>` listing all seasons (value = id), `onchange` navigates to `?edit=<new_id>`
   - Tab nav: `Spoiler Bar` / `Season Info` / `Player Photos` — hash-driven
   - Info tab: BasicInfo (full_name, season_number, abbreviation) + Dates (start_date, end_date) as live inputs; Images / Winners / Roster render `seasons-edit-stub.php`
   - Spoiler Bar + Photos tabs render `seasons-edit-stub.php`

### Save (Info tab submit)

1. Form POSTs to `admin-post.php?action=bbj_v2_edit_season_info` with nonce + `season_id` hidden input
2. Existing `bbj_v2_edit_season_info()` validates + writes to `wp_bbj_seasons`
3. Handler also updates the companion post: `wp_update_post(['ID' => $post_id, 'post_title' => $full_name, 'post_status' => 'publish'])` — publishes the draft on first meaningful save
4. Cache bust: `bbj_spoiler_bar_bust_cache($season_id)` only if `(int) get_option('bbj_v2_current_season') === $season_id`
5. Redirect → `/admin?tab=seasons&edit=<id>&updated=1`

### Notices

- `?created=1` → "Season created. Fill in the details below." (info banner)
- `?updated=1` → "Season saved." (success banner, auto-dismiss via tiny inline JS after ~3s)
- `?error=not_found` → "That season doesn't exist." (error banner on list page)

---

## Components & markup

Styling matches the flat-editorial admin aesthetic already shipped (`bg-white` cards, `border-stone-200`, sharp corners, Oswald section headings).

### List page (`pane-seasons.php`)

```
┌ Seasons ──────────────────────── [+ Add Season] ┐
│                                                 │
│  Name                  #    Dates          Winner       Status     │
│  ─────────────────────────────────────────────────────────────────│
│  Big Brother 27  BB27  27   Jul 10 – TBD   —             Current ▍│  ← yellow accent left border
│  Big Brother 26  BB26  26   Jul 17–Oct 13  Chelsie Baham Completed │
│  Big Brother 25  BB25  25   Aug  2–Nov  9  Jag Bains     Completed │
└────────────────────────────────────────────────────────────────────┘
```

- Name is the row's primary click target → `/admin?tab=seasons&edit=<id>`
- Abbreviation rendered as a small chip next to full name
- Status badge classes reused from existing spoiler-bar pills (yellow = current, stone = completed, soft-blue = upcoming, red = draft)
- Add Season button is a `<form method="post" action="admin-post.php">` rendered as a styled submit — no GET link, because create-on-click needs nonce protection
- Default sort: `season_number DESC`. No sort toggles.
- Empty state: centered card with "No seasons yet — add your first one" + the Add Season button

### Edit page (`pane-seasons-edit.php`)

```
┌ Seasons › Edit Big Brother 27 ────────────── [View season →] ┐
│                                                              │
│  Switch to: [ Big Brother 27 ▾ ]                             │
│                                                              │
│  [Spoiler Bar] [✓ Season Info] [Player Photos]               │  ← tab nav
│  ─────────────────────────────────────────────────           │
│                                                              │
│  ┌ Basic Info ────────────────────────────────────┐          │
│  │ Full name   [ Big Brother 27                 ] │          │
│  │ Season #    [ 27 ]   Abbreviation [ BB27    ] │          │
│  └───────────────────────────────────────────────┘          │
│                                                              │
│  ┌ Dates ──────────────────────────────────────────┐         │
│  │ Start date  [ 07/10/2025 ]  End date [         ]│         │
│  └───────────────────────────────────────────────┘         │
│                                                              │
│  ┌ Images ──────────────────── Coming in Sprint A  ┐         │
│  ┌ Winners ───────────────── Coming in Sprint A   ┐         │
│  ┌ Roster ────────────────── Coming in Sprint A   ┐         │
│                                                              │
│  [ Save Season ]                                             │
└────────────────────────────────────────────────────────────┘
```

- Tab nav: plain `<a href="#spoiler">` / `#info` / `#photos`; tiny inline JS toggles the visible panel on `hashchange`, no framework
- Default tab: hash in URL → use it; else `#info` for pre-sprint (don't land on stubbed Spoiler Bar)
- Stub partial: muted card with tab/section name + "This section ships in Sprint A."
- Save button: plain inline button at bottom of form (no sticky SaveBar in v1)
- SeasonSwitcher: plain `<select>` with `onchange="window.location = ..."` — no React

### Reused from existing admin shell

- `template-parts/admin/sidebar.php` — Seasons item already exists
- `inc/admin-shell.php` — safeguard helpers
- Page wrapper / breadcrumb styles from `pane-overview.php`

---

## Error handling & edge cases

### Guards & validation

- `bbj_v2_require_admin()` on line 1 of `page-admin.php` (already present) — covers both list and edit panes
- Nonces: new `bbj_v2_create_season_nonce`; reuse existing `edit_season_nonce` for the Info save
- `absint()` on the `edit` param; `bbj_v2_get_season_by_id()` returns null → redirect to list with `?error=not_found`
- Info tab required fields (server-side only in v1): `full_name` non-empty, `season_number` numeric. Existing `bbj_v2_edit_season_info()` already does this — confirm no regression

### Edge cases

- **User clicks Add Season, then navigates away before saving.** → Empty-named draft row sits in the list as `Draft` status. Acceptable for v1; no auto-cleanup cron. User can trash from wp-admin if annoying.
- **Two admins edit the same season concurrently.** → Last write wins. No row-locking — not worth solving for a 2-admin site.
- **`bbj_v2_current_season` option points at a deleted season id.** → Status computation gracefully falls through to `Completed`/`Active`/etc for surviving rows. No crash.
- **CPT post deleted but `wp_bbj_seasons` row remains (or vice versa).** → Edit page loads what it can; missing post title falls back to `full_name`. Don't auto-heal; log and move on.
- **Seasons list with zero rows on a fresh install.** → Empty-state card.

### Cache

- On create: nothing to bust — the new row isn't in any cached query yet
- On info save: `bbj_spoiler_bar_bust_cache($season_id)` only if the saved season is current
- No new cache keys introduced

---

## Testing plan

Manual smoke-test checklist on staging (no automated tests — matches existing repo pattern):

1. `/admin?tab=seasons` loads; list shows all seasons with correct status badges; Current row has yellow accent
2. Click "Add Season" → lands on `/admin?tab=seasons&edit=<new_id>`, fields are empty, `?created=1` banner visible
3. Fill in `full_name` + `season_number` + dates → Save → `?updated=1` banner, post leaves Draft status (visible in wp-admin CPT list), list page shows the new season with its status badge
4. Edit an existing season → fields pre-populated; Save updates the DB + cache-busts if current; redirects back to edit page
5. Invalid `?edit=9999` → redirects to list with not-found banner
6. SeasonSwitcher dropdown navigates to other seasons without returning to list
7. Tab nav: `#info` / `#spoiler` / `#photos` hash-switching works; reload preserves tab; Spoiler Bar + Photos render their stubs cleanly
8. Non-admin user hitting `/admin?tab=seasons` → 403 (via `bbj_v2_require_admin`)

---

## References

- Next.js reference: `C:\xampp\htdocs\bbj-app\src\app\bigbrother-seasons\[slug]\edit\` (see `reference_bbj_app_paths.md` memory)
- Old PHP plugin pages (source for handler reuse):
  - `wp-content/plugins/bbj-v2/includes/Public/bbj-v2-seasons.php`
  - `wp-content/plugins/bbj-v2/includes/Public/bbj-v2-edit-season.php`
  - `wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php`
- Admin shell pattern: `wp-content/themes/bbj-v2-theme/page-admin.php`, `template-parts/admin/pane-overview.php`, `template-parts/admin/pane-stub.php`
- Roadmap: `.claude/project/roadmap.md` (Sprint A depends on this pre-sprint shipping)

---

## What ships next (Sprint A)

Once this is in, Sprint A fills the stubs:
- Spoiler Bar tab → `RosterStatusSection` equivalent (card-per-player UI, matching the Next.js design)
- Images / Winners / Roster stubs → live fields
- `/admin?tab=settings` pane for `bbj_v2_current_season` dropdown + `bbj_v2_season_active` toggle
