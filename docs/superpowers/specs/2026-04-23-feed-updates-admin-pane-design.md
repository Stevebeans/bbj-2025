# Feed Updates Admin Pane — Design

**Date:** 2026-04-23
**Sprint:** Sprint B (admin shell continues — next tab after Settings)
**Status:** Design approved, ready for implementation plan

---

## Problem

Live-feed posting today flows through the default WordPress editor (Posts → Feed Updates → Add New → Gutenberg). During an actual live-feed shift — when the site owner is watching feeds and posting ~10–30 updates an hour — the full Gutenberg page cycle per post is a pain. Each post requires loading the block editor, filling out sidebar panels, and clicking around meta boxes. There is no custom admin UI; this is greenfield work.

The goal is a single-screen admin pane at `/admin?tab=feed-updates` that makes a full shift possible without touching `wp-admin`. Scope is deliberately MVP — build the essentials, ship today, add polish later.

## Goals

- `/admin?tab=feed-updates` renders a focused pane in the existing flat-editorial admin shell
- **Quick-post form at the top:** headline, details, image, category, mode, social toggles, Post button
- **Scannable list of 50 most recent updates** directly below, each row showing headline, category, time, votes, social status, thumbnail
- **Inline edit** (expand row, fix typos, Save back to compact view) without reposting to social
- **Inline delete** with confirm
- **Descriptive SEO-friendly titles** (user-written headline becomes post title and slug), replacing the current auto-generated `"BB27 Feed Update - Jan 30, 3:45 PM PT"` pattern
- **Forward-compatible access control** via a new `bbj_v2_edit_feed_updates` capability, so the planned site-native permissions grid can grant/revoke access per role without touching this code

## Non-goals (deferred — backlog, not cancelled)

- Image editing on existing updates (MVP allows image on create only; to change the image, delete + re-post)
- Search within the list
- Filter-by-category in the admin list
- Pin / star updates
- Bulk actions
- Infinite scroll or "load more" beyond 50
- Drafts or scheduling
- Category manager card in Settings pane (separate follow-up)
- Taxonomy cleanup — retiring or renaming the 9 existing `update_type` terms
- Verifying `updater` role registration on staging (only matters once the cap is opened to non-admins)

---

## Architecture

New pane template in the theme, two new REST handlers in the plugin, one small `create` endpoint extension to accept headline + details + taxonomy, plus a capability refactor so the pane and all REST endpoints share a single gate.

### New files

```
wp-content/themes/bbj-v2-theme/
  template-parts/admin/pane-feed-updates.php       ← the pane (form + list)
  assets/js/admin-feed-updates.js                  ← vanilla JS, fetch + DOM only

wp-content/plugins/bigbrotherjunkies-data/src/Capabilities/
  FeedUpdatesCapability.php                        ← registers + seeds bbj_v2_edit_feed_updates
```

### Modified files

```
wp-content/themes/bbj-v2-theme/page-admin.php
  — add elseif ($active_tab === 'feed-updates') branch

wp-content/themes/bbj-v2-theme/inc/admin-shell.php
  — add bbj_v2_require_capability(string $cap) helper (sibling to require_admin)

wp-content/themes/bbj-v2-theme/functions.php  (or assets loader equivalent)
  — enqueue admin-feed-updates.js ONLY when $active_tab === 'feed-updates'

wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php
  — register PUT /feed-updates/{id} + DELETE /feed-updates/{id}
  — extend POST /feed-updates/create to accept 'title', 'details', 'update_type' (optional, backward-compatible)
  — refactor checkUpdaterPermission() from hardcoded role array to
      current_user_can('bbj_v2_edit_feed_updates')

wp-content/plugins/bigbrotherjunkies-data/src/Plugin.php  (or bootstrap)
  — wire FeedUpdatesCapability to register on plugin load; seed cap on version upgrade
```

### Reused as-is

- `get_posts(['post_type' => 'live-feed-updates', ...])` — server-render initial 50 rows
- `get_terms('update_type')` — populate category select dynamically; taxonomy changes elsewhere (wp-admin, future Settings category manager, direct DB) flow through automatically
- Existing image upload path inside `FeedUpdateRoutes::createFeedUpdate()` (`wp_handle_upload` → `set_post_thumbnail`) — already handles `multipart/form-data`; zero change needed
- Existing Bluesky + Facebook clients — only called by POST `/create`, never by PUT
- `_feed_update_mode`, `_social_posting_results` post meta — unchanged schema
- `wp_bbj_feed_ratings` vote table — unchanged; list rows read totals for display
- Toast auto-dismiss pattern from `pane-settings.php` (`data-bbj-autodismiss`)

### Capability design

Single new custom WP cap: **`bbj_v2_edit_feed_updates`**.

- Seeded to `administrator` role on first plugin activation/upgrade via a versioned one-shot (store a `bbj_v2_caps_version` option, run the seeder when version is below current).
- Pane guard: `bbj_v2_require_capability('bbj_v2_edit_feed_updates')` on line 1 of `page-admin.php` when `tab=feed-updates`.
- Endpoints using `checkUpdaterPermission` (refactored to the new cap): `POST /create`, new `PUT /{id}`, new `DELETE /{id}`, existing `GET /mode`, `POST /mode`, `GET /social-config`.
- The `POST /{id}/vote` endpoint is **unchanged** — it stays on `'is_user_logged_in'` since voting is open to any logged-in user, not tied to feed-update editing.
- Public endpoints (`GET /single/{slug}`, `GET /hashtag`) are unchanged — they stay on `__return_true`.
- The future native permissions grid in Settings is a UI layer over `$role->add_cap()` / `$role->remove_cap()`. No code in the pane or routes changes when that ships.

Backward-compat: refactoring `checkUpdaterPermission()` must preserve current behavior for admins. After the refactor, admins still return `true` (they have the cap via the seeder). Non-admins with `updater`/`editor`/`second_in_command` roles **temporarily lose** access to the updater-gated endpoints until the permissions grid grants them the cap. This is acceptable because (a) the only active writer today is the admin; (b) the mismatch between the admin-only pane and the broader REST allow list is a bug, not a feature.

### Why server-render the initial 50?

Fast first paint, no spinner, no initial fetch. If JS fails to load or breaks, the pane is still readable (rows + form fields present, mutations disabled). All mutations after page load are JS via `fetch()`.

### No build step

Plain JS file enqueued via `wp_enqueue_script`. Tailwind classes on the pane markup compile through the existing theme watch. No webpack, no bundler, no transpilation.

---

## Data flow

### Initial load — `/admin?tab=feed-updates`

1. `page-admin.php` calls `bbj_v2_require_capability('bbj_v2_edit_feed_updates')` on line 1 (redirect to login if logged-out, 403 if logged-in without cap).
2. Dispatcher routes to `pane-feed-updates.php`.
3. Pane queries 50 most recent `live-feed-updates` via `get_posts`, enriches each with vote totals + thumbnail URL + update_type term + mode meta.
4. Pane renders:
   - Quick-post form (headline input, details textarea, file input, category select, mode radios, social checkboxes, Post button)
   - `<ul>` with 50 server-rendered `<li data-id="{id}">` rows — each row contains both a display subtree and a (hidden) edit subtree
5. Pane calls `wp_localize_script('admin-feed-updates', 'BBJ_FEED', [...])` with:
   - `rest_root` (home URL + `/wp-json/bbjd/v1/feed-updates`)
   - `nonce` (`wp_create_nonce('wp_rest')`)
   - `social_config` (Bluesky configured boolean, Facebook configured boolean)
   - `current_user_name` (for optimistic display if ever needed)
6. Page sends. JS initializes, binds form submit, row Edit/Delete clicks, confirm dialogs, toast dispatcher.

### Create flow

1. User fills headline + (optional) details + (optional) image + category + mode + social checkboxes, clicks Post.
2. JS validates headline not empty; shows inline red text under headline if blank.
3. JS serializes form as `FormData` (multipart to carry the optional image) and POSTs to `/wp-json/bbjd/v1/feed-updates/create` with header `X-WP-Nonce: {nonce}`.
4. Handler `createFeedUpdate`:
   - Reads new optional params: `title` (becomes `post_title`), `details` (becomes `post_content`), `update_type` (term ID or slug; sets taxonomy via `wp_set_object_terms`).
   - If `title` omitted, falls back to existing `generateTitle($mode)` auto-format — Next.js integration continues to work unchanged.
   - If `details` omitted, `post_content` is empty string.
   - `post_name` auto-derived by WP from `post_title` — clean SEO slug.
   - Handles image upload if present (existing code path).
   - Posts to Bluesky / Facebook if toggled (existing code path).
   - Returns `201` with `{ update: {...}, social_results: {...} }`.
5. JS:
   - Builds new `<li>` from a hidden template element in the DOM (cloned, populated).
   - Prepends to `<ul>`.
   - Clears form (headline, details, image preview; preserves mode + category + social checkboxes as sticky-per-shift).
   - Focuses headline input.
   - Toasts `Posted ✓` — or `Posted ✓ — Bluesky failed` / `Posted ✓ — Facebook failed` if `social_results` reports errors, with the new row also gaining an inline ✗ badge (hover tooltip shows the error message).

### Edit flow

1. User clicks Edit on a row.
2. JS toggles the row's `data-mode` from `display` → `edit`: display subtree hides, edit subtree shows (headline input, details textarea, category select, Save, Cancel).
3. Inputs are pre-filled from `data-*` attributes on the row.
4. Save clicked:
   - JS PUTs to `/wp-json/bbjd/v1/feed-updates/{id}` with JSON body `{ title, details, update_type }`.
   - Handler `updateFeedUpdate`:
     - Verifies cap.
     - Calls `wp_update_post(['ID' => $id, 'post_title' => $title, 'post_content' => $details])`.
     - Calls `wp_set_object_terms($id, $update_type, 'update_type', false)` if provided.
     - **Does NOT** instantiate `BlueskyClient` or `FacebookClient`. No social re-post on edit, ever.
     - Returns `200` with `{ update: {...} }` (same shape as create).
5. JS replaces the row's `data-*` attributes + display subtree text nodes with the updated values, toggles `data-mode` back to `display`, toasts `Updated ✓`.
6. Cancel simply toggles `data-mode` back without fetching.

### Delete flow

1. User clicks Delete on a row.
2. JS swaps the row's action buttons into an inline confirm: `Delete this update? [Confirm] [Cancel]`.
3. Confirm clicked:
   - JS DELETEs `/wp-json/bbjd/v1/feed-updates/{id}`.
   - Handler `deleteFeedUpdate`:
     - Verifies cap.
     - Calls `wp_delete_post($id, true)` — **force delete**, no trash. (Undelete is out of scope; if the user deletes by mistake, recovery is a separate problem.)
     - Returns `204`.
4. JS removes the `<li>` from the DOM. Toasts `Deleted`.
5. Cancel restores the original buttons.

### Row layout (display mode)

```
┌────────────────────────────────────────────────────────────────────┐
│ [🖼️] Amy and Zach in backyard  [Gossip] 3m  ▲12 ▼0  ✓BS ✓FB  [Edit][Del]│
└────────────────────────────────────────────────────────────────────┘
```

- `[🖼️]`: 32×32 featured-image thumbnail. Empty slot collapses if no image.
- Headline: truncates to one line with `text-overflow: ellipsis`. Hover shows full title.
- `[Gossip]`: category pill, color-coded (pill colors optional polish).
- `3m`: relative time. Wrap in `data-nosnippet` per `feedback_seo_time_handling` memory.
- `▲12 ▼0`: total votes from `wp_bbj_feed_ratings`.
- `✓BS ✓FB`: green check per platform if cross-post succeeded; `✗BS` / `✗FB` in red if failed (per `_social_posting_results`); absent if the toggle was off at create time.
- `[Edit][Del]`: small text-link or icon buttons.

### Row layout (edit mode)

```
┌────────────────────────────────────────────────────────────────────┐
│ [headline input — wide]                [category select]  [Save][X]│
│ [details textarea — 3 rows, full width]                            │
└────────────────────────────────────────────────────────────────────┘
```

Image is NOT editable in MVP. Mode is NOT editable in MVP. Both are intentional scope trims.

---

## Error handling

| Scenario | Behavior |
|---|---|
| Network error on any fetch | Toast `Request failed — check connection`. Form / edit state preserved so retry is free. |
| 403 from REST | Toast `Permission denied`. Shouldn't happen in practice (pane guard would have redirected), but defensive for cap-revoked-mid-session. |
| 400 validation (empty headline on create) | Caught client-side before fetch. Inline red text under the headline input. |
| Social post failure on create | Update is already saved — never block the save on social. Toast: `Posted ✓ — Bluesky failed` (or similar). New row gains an inline ✗ badge; hover tooltip shows the error from `_social_posting_results`. |
| Delete of non-existent ID | Toast `Already deleted`. Remove the row from DOM anyway. |
| PUT on non-existent ID | Toast `Update disappeared — refresh`. Leave edit form open for the user to copy text before refreshing. |
| REST nonce rejected | Treated as 403 (see above). Per `feedback_rest_nonce_collision` memory: use `X-WP-Nonce` — this pane's endpoints are standard WP REST, not the custom-nonce endpoints that collision applies to. |

Toast container: right-aligned stacking div injected at pane render. Each toast is a `<div data-bbj-autodismiss="3000">` reusing the Settings pane pattern.

---

## Testing (manual smoke, during implementation)

1. **Create one-liner:** Post headline only, Gossip category → appears in list, in `/feed-updates/` archive, in single-update page with correct slug.
2. **Create with details + image + both socials:** verify Bluesky + FB posts fire; verify `_social_posting_results` meta; verify 32×32 thumbnail in the row.
3. **Inline edit headline:** row updates in place; verify NO duplicate social post appears in Bluesky/FB feeds.
4. **Inline edit details + category:** both persist; row reflects new category pill after Save.
5. **Delete:** row disappears; confirm post is force-deleted (not in Trash) via wp-admin or `wp post list --post_type=live-feed-updates --post_status=trash`.
6. **Auth — logged out:** `/admin?tab=feed-updates` redirects to login with return URL.
7. **Auth — logged in as non-admin:** 403 page.
8. **Auth — cap revoked from admin** (via `wp shell` or a test script): pane access blocked with 403.
9. **Backward-compat — Next.js integration:** call POST `/bbjd/v1/feed-updates/create` with just `{ content, mode }` (no title) → endpoint still works, auto-title generated, untagged.
10. **SEO sanity:** check new updates have human-readable slugs (`jag-wins-hoh` not `bb27-feed-update-jan-30-345-pm-pt`).

No automated tests this sprint. No test infra exists on theme/plugin side; spinning one up is out of scope for a one-day build.

---

## Staging / prod deploy verification

After pushing to staging:

- `/admin?tab=feed-updates` loads for admin user; 50 rows server-rendered; form posts successfully on the first try.
- Confirm the cap was seeded on upgrade:
  ```
  wp shell
  > get_role('administrator')->has_cap('bbj_v2_edit_feed_updates');
  => true
  ```
- Visit `/feed-updates/` public archive after posting a test update → appears at top, correct slug, correct category.
- Check one social post actually landed on Bluesky / Facebook if toggles were enabled.

---

## Backlog (follow-ups, not this sprint)

Explicit list so nothing drifts:

- Image editing on existing updates
- Search within admin list
- Filter-by-category in admin list
- Pin / star updates
- Bulk actions (delete many, re-tag many)
- Infinite scroll or "load more" beyond 50
- Drafts / scheduled posting
- **Category manager card in Settings pane** (list / add / rename / delete `update_type` terms — separate spec, next Settings follow-up)
- Taxonomy cleanup — retire or rename the 9 existing terms to match public hub chips
- **Permissions matrix UI in Settings pane** — the big one the user sketched; this pane is forward-compatible and will plug in without change
- Verifying `updater` / `second_in_command` role registration on staging (only matters once the cap is granted to non-admin roles)
- Retire the `/wp-admin` Feed Updates menu (after confidence in the new pane)

---

## Open questions resolved during brainstorm

- **Q:** Title auto-generated or user-written? → **User-written headline.** SEO: unique, keyword-rich titles beat timestamped duplicates. The old hand-written "Jag wins HOH" style is the better UX + the better SEO.
- **Q:** What does the quick-post form capture? → **Headline + details + category + mode + image + social toggles.**
- **Q:** Taxonomy alignment (9 DB terms vs 6 mockup chips)? → **Defer.** Category dropdown reads `update_type` terms dynamically. Taxonomy management becomes a separate Settings-pane follow-up.
- **Q:** PUT/DELETE endpoints? → **Add to `/bbjd/v1/*`.** Keeps the pane's API under one namespace. ~30 lines each.
- **Q:** List layout — table, cards, or modal-edit? → **Dense table with inline edit.** Max scan density for shift use.
- **Q:** Access control — hardcoded role array or custom cap? → **Custom cap (`bbj_v2_edit_feed_updates`).** Forward-compatible with the planned site-native permissions grid.
- **Q:** Image upload in MVP? → **Yes.** Create only; no image editing on existing rows.
