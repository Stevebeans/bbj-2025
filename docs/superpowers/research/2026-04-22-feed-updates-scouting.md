# Feed Updates — Pre-Brainstorm Scouting Report

**Date:** 2026-04-22 (scouted the evening before the Thursday brainstorm)
**Purpose:** Load this before brainstorming the `/admin?tab=feed-updates` pane so the session starts with full context instead of spending 30 minutes re-exploring.

---

## 1. CPT: `live-feed-updates`

- **Registered in:** `wp-content/plugins/bbj-v2/includes/PostTypes/FeedUpdates.php` (or similar — confirm path on Thursday; the scout found the file under `bbj-v2-plugin` which may be a typo in the scout output)
- `show_in_rest: true`
- `show_in_menu: true` (position 8)
- `has_archive: true`
- `supports: ['title', 'editor', 'thumbnail']`
- `rewrite: {slug: 'feed-updates', with_front: false}`
- `capability_type: 'post'`, `hierarchical: false`
- No custom rewrite rules beyond the slug

## 2. Taxonomy: `update_type`

- **File:** `wp-content/plugins/bigbrotherjunkies-data/src/Taxonomies/UpdateTypeTaxonomy.php`
- Non-hierarchical, `show_in_rest: true`, `show_admin_column: true`
- **Seed terms (9):** Drama, Ceremony, Strategy, Competition, Alliance, Eviction, Punishment, Reward, Showmance
- **Public hub design mockup chips (6):** ALL / DRAMA / CEREMONY / STRATEGY / CHITCHAT / MEDIA
- **Mismatch to resolve in brainstorm:** Chitchat + Media missing; Competition, Alliance, Eviction, Punishment, Reward, Showmance exist but not in mockup

## 3. Extra meta fields per feed update

- `_feed_update_mode` → `'feed'` or `'show'` (display hint)
- `_social_posting_results` → JSON with Bluesky URL + Facebook URL + errors after auto-post
- (No Meta Box / ACF fields — just plain post meta)

## 4. Voting

- **Table:** `wp_bbj_feed_ratings`
- Columns: `id`, `update_id`, `user_id`, `rating` (±1), `ip_address`, `updated_at`
- Referenced in `FeedUpdateRoutes.php:260`

## 5. REST endpoints (the big one)

All under `/wp-json/bbjd/v1/feed-updates/*` in `wp-content/plugins/bigbrotherjunkies-data/src/Api/FeedUpdateRoutes.php`:

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/create` | updater role | Create update + optional Bluesky + FB cross-post |
| GET | `/single/{slug}` | public | Fetch single update |
| POST | `/{id}/vote` | logged-in | Up/downvote (toggleable) |
| GET | `/mode` | updater | Get user's feed/show preference |
| POST | `/mode` | updater | Set user's feed/show preference |
| GET | `/hashtag` | public | Current-season hashtag (#BB27) |
| GET | `/social-config` | updater | Bluesky / FB config status |

Permission: `checkUpdaterPermission()` accepts `administrator`, `editor`, `updater`, `second_in_command`.

**Implication for admin pane:** We already have a fully functional create endpoint with social cross-posting. The admin UI should use `fetch()` against these routes — no need to write a PHP form POST handler.

**Missing endpoint for admin:** No `PUT /update/{id}` or `DELETE /{id}` exists yet. The admin pane brainstorm needs to decide: (a) add these to FeedUpdateRoutes, (b) use WP REST defaults `/wp/v2/live-feed-updates/{id}`, (c) skip inline edit/delete for v1.

## 6. Existing wp-admin UI (the pain point)

- **There is no custom admin page for feed-updates.** The user edits via the default WordPress editor (Posts → Feed Updates → Add New → Gutenberg).
- The "actively bad UX" is therefore the stock Gutenberg flow, not a broken custom UI.
- `bigbrotherjunkies-data/src/Admin/AdminLoader.php` shows Dashboard, Registrations, User Cleanup subpages — **no feed-updates tab**.
- This is greenfield work.

## 7. Public templates

- **Archive:** `wp-content/themes/BBJ/archive-live-feed-updates.php` — uses Ajax Load More shortcode with `theme_repeater="feed-list.php"`
- **Single:** `wp-content/themes/BBJ/single-live-feed-updates.php` — prev/next nav, breadcrumbs, comments
- **No shortcode** for embedding elsewhere

## 8. Caching / invalidation

- `transition_post_status` hook in `bigbrotherjunkies-data/src/Plugin.php` (~line 132-142) triggers `Revalidation::revalidateTag('feed-updates')` for Next.js ISR
- `do_action('breeze_clear_all_cache')` fires in `FeedUpdateRoutes::create()` (line 201)
- **No `wp_cache_*` keys** for feed updates — the object cache isn't in the hot path

## 9. Role system

- **`updater` role is referenced but not registered in this codebase.** Likely added via external User Role Editor plugin.
- Must verify on staging before admin pane ships: `wp role list | grep updater` or check via phpMyAdmin's `wp_usermeta.wp_capabilities`.
- Roles accepted: `administrator`, `editor`, `updater`, `second_in_command`

## 10. No existing React admin component

Checked `bbj-next/`, `bigbrotherjunkies-data/`, `bbj-tools/` — no React-based feed admin exists. Only tangential finds:
- `bbj-tools/.../PlayerTable.jsx` (unrelated — player editor)
- `bbj-tools/new-feed-updates-block/` (Gutenberg block, stub only, no logic yet)

Greenfield.

---

## Design implications flagged for brainstorm

1. **Use REST, not form POST.** Admin pane breaks the PHP-form-submit pattern we've used elsewhere. Justified: live-feed shifts need zero-reload create. First pane with fetch-based interactivity.
2. **Taxonomy alignment decision.** Match design (add Chitchat + Media; trim 5 unused), or keep 9 and subset public filters. Affects both admin pane and public hub.
3. **Verify `updater` role exists on staging** before ship. Quick DB check.
4. **Social posting is automatic** on create. Admin list should show posting status (Bluesky URL / FB URL / errors) — data is in `_social_posting_results` meta.
5. **Vote counts** available per update via `wp_bbj_feed_ratings`. List view can show them as a column.
6. **Missing endpoints:** PUT/DELETE don't exist in `/bbjd/v1/*`. Brainstorm call: add them, use WP core `/wp/v2/live-feed-updates/{id}`, or defer inline edit/delete.
7. **Mode field** (`_feed_update_mode`) — does the admin pane need to expose it? Tiny extra surface; defer unless the user specifically wants it.
