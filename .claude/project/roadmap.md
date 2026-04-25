# BBJ v2 Theme — Roadmap

> Living document. Edit freely — I'll update it as sprints ship.
> Last updated: 2026-04-25

---

## Legend

- ✅ shipped on staging (may or may not be pushed to origin / deployed)
- 🟡 partially shipped (stub exists, needs real work)
- ⬜ not started
- 🔖 parked / not yet scoped
- 📝 sprint has a spec + plan in `docs/superpowers/`

Reading order: sprints are listed in priority order. Sprint letters are stable; if I insert something between A and B it becomes A.5. Estimates assume your part-time pace — real-world may be longer or shorter.

---

## What's shipped ✅

- Homepage (`front-page.php`) — 3-col editorial grid, rich houseboard (placeholder data until Sprint A), status strip, hero + spoilers block, house pulse, latest feeds, more stories, flat sidebar
- Single post (`single.php`) — editorial layout with typography, share bar, author box, related posts
- Page (`page.php`) — trimmed sibling of single
- Site-wide sidebar — Newsletter (rebranded to match Watch Live), Watch Live on Paramount+, Follow BBJ socials, hot posts, recent comments
- Flat editorial aesthetic — `bg-stone-50`, sharp edges, section-divider sidebar widgets
- **Admin shell** (`/admin/`) — dark-navy sidebar, 12 items in 4 sections, Overview pane, stubs for everything else 🟡
- **User dashboard shell** (`/dashboard/`) — dark-navy sidebar, 3 sections + logout, Overview pane, stubs for everything else 🟡
- Header icon row — shield (admins) / pencil (editors) / bell / avatar
- Safeguard helpers — `bbj_v2_require_admin()`, `bbj_v2_require_logged_in()` + noindex on admin/dashboard
- **Seasons admin pane** (`/admin?tab=seasons`) — flat list with status badges + current-season accent, Add Season draft flow, edit page shell with 3-tab layout (Spoiler Bar / Info / Photos); Season Info tab live for BasicInfo + Dates; Images / Winners / Roster stubbed for Sprint A 🟡
- **Spoiler Bar editor** (`/admin?tab=seasons&edit=<id>#spoiler`) — card-per-player UI on the default edit tab; adds `bbj_finish_place` column for correct double-eviction sort; uncached preview strip; Purge Cache button; reuses existing `bbj_v2_update_season()` handler
- **Settings pane** (`/admin?tab=settings`) — current-season dropdown + info card with quick-jump to the active season's spoiler bar; cache-bust on switch. Ports the old `/wp-admin/admin.php?page=bbj-v2-settings` page into the new admin shell.
- **Player profile** (`single-bigbrother-players.php`) — flat editorial aesthetic, vitals, season participation timeline, stats, related feeds + posts. Shipped on staging 2026-04-24. Captures LEFT JOIN + id-vs-post_id + `bbj_finish_place` query conventions.
- **Feed Updates Hub skeleton** (`/feed-updates/`) — full design ported from `BBJ Feed Updates Hub.html`. Server-renders day-grouped thread of all 11.5k+ updates with vanilla-JS category-tab + checkbox-filter + search + sort over the visible page. Page header (kicker / H1 / season+week+day+today-count meta strip), black "On the Feeds Now" livebar with HoH/Veto/Block summary + today/week/season counts, featured "latest update" card (placeholder until pinning lands), toolbar, day dividers, rich update cards w/ category accent stripe + author avatar + thumbnail + comment count + open link, Load-More pagination via `?paged=N`. Sidebar: Live Right Now (HoH/Veto/Block/Next-evict from `bbj_v2_get_spoiler_bar()`), Filter card, Follow Us, Newsletter (disabled placeholder), Hot This Week (top blog posts by 7d comment count), 300×600 ad slot. Defer: vote interactivity, auto-refresh (slated Premium), houseguest chips per update, trending hashtags, quote blocks, pinning. Shipped 2026-04-25. 🟡

  **URL trap discovered**: the live-feed-updates CPT registers with `rewrite => ['slug' => 'feed-updates']` (in `bbj-v2-plugin/src/PostTypes/FeedUpdates.php`). The internal post-type name and the URL slug differ. Real archive URL has always been `/feed-updates/` — and 11.5k+ singles already live at `/feed-updates/<slug>/`. No URL migration needed; canonical URL preserved. Old WP page at `/feed-updates/` (ID 50269, post_status=publish) is now orphaned — CPT archive rewrite rule wins request resolution, so the page never renders. Safe to trash.

  **Note**: any time a CPT/taxonomy/rewrite_slug is added or modified, the rewrite_rules option cache must be flushed. Easiest is wp-admin → Settings → Permalinks → Save. The cache predates the live-feed-updates CPT being registered, which is why this archive 404'd until I flushed it.

- **Houseguest archive skeleton** (`/bigbrother-players/`) — flat-aesthetic skeleton: server-renders all players in one query (post × `wp_bbj_players` × aggregated `wp_bbj_v2_player_season` stats), responsive 2/3/4/5-col card grid, vanilla-JS filter bar (search-as-you-type by name + season + status + sort). No design yet — placeholder layout for QA navigation. Shipped 2026-04-25. 🟡
- **Seasons archive skeleton** (`/bigbrother-seasons/`) — flat-aesthetic table: Season / Start / End / Winner / AFP / Runner-up; rows link to season profiles, name cells link to player profiles. Mobile horizontal scroll. No design yet. Shipped 2026-04-25. 🟡
- **Season profile** (`single-bigbrother-seasons.php`) — flat editorial aesthetic, sticky tab nav + scroll-spy, evictions/comps tables from `wp_bbj_weeks`, sidebar (TOC, Quick Facts, More Seasons, ad), object cache + `save_post` busters. Shipped on staging 2026-04-24; CSS-collision + content-fallback fixes 2026-04-25 (commits 16c859a..5903cdf).
- **Feed Updates admin pane** (`/admin?tab=feed-updates`) — quick-post form (headline + details + image + category + mode + social toggles) with 50-row list below, inline edit + force-delete. Permission gated via `PermissionChecker::userCan('feed_updates')` so the native permissions UI drives access. REST: extended `POST /bbjd/v1/feed-updates/create` to accept user-written titles/details/taxonomy; added PUT + DELETE handlers; PUT never re-posts to social. See `docs/superpowers/specs/2026-04-23-feed-updates-admin-pane-design.md`. Sprint I scope pulled forward. 🟡

---

## Page coverage audit (2026-04-25)

> Sources: bbj-v2-theme (current), legacy `BBJ` theme (live, deprecating), shelved bbj-app Next.js prototype at `C:/xampp/htdocs/bbj-app/src/app/`.
> Percentage = rough completeness vs. fully-designed and feature-complete. Skeletons w/ no design land at 30-40%.

### Public pages

| Slug | Page Name | Status   |
|------|-----------|--------|
| `/` | Homepage | ✅ Shipped |
| `/<post-slug>/` | Single post | ✅ Shipped |
| `/<page-slug>/` | Generic page | ✅ Shipped |
| `/feed-updates/` | Feed Updates Hub | 🟡 Partial (~70%) — Sprint D skeleton; design ported, voting / auto-refresh / pinning / chips / trending / quotes deferred |
| `/feed-updates/<slug>/` | Single feed update | 🟡 Partial (~50%) — template exists from earlier work, design pass not done |
| `/bigbrother-players/` | Player directory | 🟡 Partial (~30%) — Sprint C skeleton, no design yet |
| `/bigbrother-players/<slug>/` | Single player | ✅ Shipped (Sprint B) |
| `/bigbrother-seasons/` | Seasons directory | 🟡 Partial (~30%) — Sprint C skeleton, no design yet |
| `/bigbrother-seasons/<slug>/` | Single season | ✅ Shipped (Sprint B) |
| `/search/` | Search results | 🟡 Partial (~10%) — default WP stub; Sprint M |
| `/404` | 404 page | 🟡 Partial (~10%) — default WP stub; Sprint M |
| `/stats` | Site-wide stats | ⬜ Not started — Sprint J (replaces legacy `/big-brother-stats/`) |
| `/compare` | Player compare picker | ⬜ Not started — Sprint K |
| `/compare/<a-vs-b>/` | Specific matchup | ⬜ Not started — Sprint K (shareable URL) |
| `/player-map` | Geographic map | ⬜ Not started — Sprint L |
| `/users/<username>/` | Public user profile | ⬜ Not started — shares data with `/dashboard?tab=profile` (Sprint F) |
| `/contact/` | Contact form | ⬜ Not started — Sprint N (shortcode-based) |
| `/power-rankings/` | Power rankings | ⬜ Not started — referenced in nav but no template |
| `/forums/` | Forums | ⬜ Not started — referenced in nav but no template |
| `/watch-feeds/` | Watch live feeds CTA | ⬜ Not started — referenced in nav |

### Auth / account / transactional

| Slug | Page Name | Status |
|------|-----------|--------|
| `/login/` | Custom login page | ⬜ Default WP — bbj-app had a custom design, not ported |
| `/reset-password/` | Password reset | ⬜ Default WP — bbj-app had custom UX |
| `/email/confirm/` | Email confirmation | ⬜ Not started — bbj-app reference |
| `/unsubscribe/` | Newsletter unsubscribe | ⬜ Not started — bbj-app reference |
| `/become-supporter/` | Premium upsell | ⬜ Not ported — OLD `page-become-supporter.php` exists; needs MemberPress wiring (Sprint G area) |
| `/checkout/success/` | Checkout success | ⬜ Not started — Stripe / MemberPress flow |
| `/checkout/cancel/` | Checkout cancel | ⬜ Not started — Stripe / MemberPress flow |
| `/privacy-policy/` | Privacy policy | ⬜ Likely a WP page; verify on prod |

### Admin tabs (`/admin/?tab=…`)

| Tab | Pane Name | Status |
|-----|-----------|--------|
| `overview` | Overview | ✅ Shipped |
| `feed-updates` | Feed Updates | 🟡 Partial (~80%) — MVP shipped (Sprint I); image edit on existing rows / search / filter / N+1 tune deferred |
| `settings` | Settings | ✅ Shipped |
| `seasons` | Seasons | 🟡 Partial (~50%) — basic info + spoiler bar shipped; images / winners / roster tabs stubbed |
| `posts` | Posts manager | ⬜ Stub — Sprint O (paired with Sprint P composer) |
| `comments` | Comments moderation | ⬜ Stub — Sprint O |
| `players` | Players CRUD | ⬜ Stub — Sprint O (currently still in old bbj-v2 plugin UI) |
| `announcements` | Broadcast notifications | ⬜ Stub — Sprint O |
| `content-engine` | FB post generator | ⬜ Stub — Sprint O low priority |
| `users` | Users + bulk cleanup | ⬜ Stub — Sprint O |
| `stats` | GA4 + GSC dashboard | ⬜ Stub — Sprint J |
| `ads` | Ad slots hub | ⬜ Stub — Sprint O low priority |
| `preview-as` | Role impersonation | ⬜ Not started — Sprint O |
| `bug-reports` | Bug reports inbox | ⬜ Not started — bbj-app reference, not on roadmap yet |
| `spoiler-bar` | Spoiler bar editor | ✅ Shipped (folded into seasons edit) |

### User dashboard tabs (`/dashboard/?tab=…`)

| Tab | Pane Name | Status |
|-----|-----------|--------|
| `overview` | Overview | 🟡 Partial (~30%) — basic shell, no real activity data |
| `activity` | Activity feed | ⬜ Stub — Sprint F |
| `saved` | Saved posts | ⬜ Stub — Sprint F (depends on save-button infra on cards) |
| `notifications` | Notifications | ⬜ Stub — Sprint E |
| `profile` | Public profile editor | ⬜ Stub — Sprint F |
| `premium` | MemberPress status | ⬜ Stub — Sprint G |
| `settings` | Account settings | ⬜ Stub — Sprint F |
| `feeds-blog` | Feeds Blog | ⬜ Stub — listed in sidebar but not yet in any sprint |
| `power-rankings` | Power Rankings | ⬜ Stub — listed in sidebar but not yet in any sprint |
| `leaderboard` | Leaderboard | ⬜ Stub — listed in sidebar but not yet in any sprint |

### Editor / composer (Sprint P)

| Slug | Page Name | Status |
|------|-----------|--------|
| `/editor/` | Editor landing | ⬜ Not started — Sprint P |
| `/editor/new/` | New post composer | ⬜ Not started — Sprint P |
| `/editor/<id>/` | Edit existing post | ⬜ Not started — Sprint P |
| `/preview/<id>/` | Preview drafts | ⬜ Not started — bbj-app had this |

### Legacy `BBJ` theme pages — decisions pending

These exist in the soon-to-be-deprecated theme. Decide whether to port, replace, or trash before the theme flip.

| Legacy slug / template | Decision |
|------------------------|----------|
| `page-big-brother-stats.php` (`/big-brother-stats/`) | **Replace** with `/stats` (Sprint J) |
| `page-all-seasons.php` (`/all-seasons/`) | **Trash** — `/bigbrother-seasons/` covers it |
| `page-season-list.php` (`/season-list/`) | **Trash** — duplicate of `/all-seasons/` |
| `page-player-directory.php` (`/player-directory/`) | **Replace** — done; `/bigbrother-players/` covers it |
| `page-player-relationships.php` (`/player-relationships/`) | **Decision needed** — port, redesign, or trash? |
| `page-my-profile.php` (`/my-profile/`) | **Replace** with `/dashboard?tab=profile` (Sprint F) |
| `page-user-dashboard.php` (`/user-dashboard/`) | **Replace** — done; `/dashboard/` already covers it |
| `page-feed-updates.php` (`/feed-updates/`) | **Replaced** — CPT archive wins now; orphaned page can be trashed |
| `page-login.php` (`/login/`) | **Decision needed** — keep custom or use default WP login |
| `page-become-supporter.php` (`/become-supporter/`) | **Port** to bbj-v2-theme (Sprint G area) |
| `page-stripe-test.php` (`/stripe-test/`) | **Trash** — dev-only |
| `page-testing.php`, `page-testingtwo.php`, `page-testtwo.php` | **Trash** — dev scratchpads |

---

## Architectural decisions locked in

- **Directory split into separate pages** (not `/directory?tab=x`). Each gets its own URL + SEO + schema:
  - `/bigbrother-players/` — player directory (matches singular slug `/bigbrother-players/<name>/`; decided 2026-04-25 not to remap to `/houseguests`)
  - `/bigbrother-seasons/` — seasons list (already the live URL; matches singular slug)
  - `/stats` — site-wide stats
  - `/player-map` — geographic map of players
  - `/compare` (list) + `/compare/<a-vs-b>` (specific matchup, shareable)
- **Compare UX**: modal-to-open on player pages → commits to a `/compare/<slug>` URL for sharing/indexing
- **Notifications**: one data source feeds two surfaces — header bell modal (compact) + `/dashboard?tab=notifications` (full paginated view)
- **Profile data**: `/dashboard?tab=profile` and public `/users/<username>` share the same bio/avatar/karma data
- **Post editor**: custom composer (see image #18 mockup), NOT a Gutenberg fork. Strips fluff — just title, body, season dropdown, featured image, SEO title, slug, publish checklist
- **Admin stays PHP-shell** with React sprinkles where interactivity demands (e.g. spoiler-bar drag-drop) — per `project_admin_shell_architecture.md`
- **Old wp-admin pages stay** until migrated one-at-a-time, driven by actual pain

---

## Sprint roadmap

### Sprint A — Site Settings + Spoiler Bar Manager ✅

**Why first:** unblocks the homepage rich houseboard to show real BB27 data instead of placeholder Rachel/Amy/Rylie/Zach/etc. Highest user-visible payoff for smallest scope.

**Scope shipped:**
- ~~`/admin?tab=spoiler-bar` pane~~ — shipped as the `#spoiler` tab on `/admin?tab=seasons&edit=<id>` (card-per-player UI, `bbj_finish_place` sort, uncached preview, Purge Cache button). See `docs/superpowers/specs/2026-04-22-spoiler-bar-editor-design.md`.
- `/admin?tab=settings` pane ✅ — current-season dropdown, info card with quick-jump to spoiler bar. `bbj_v2_season_active` toggle dropped (no consumers). See `docs/superpowers/specs/2026-04-22-admin-settings-pane-design.md`.

**Follow-up work (not blocking Sprint A's done state):**
- Wire `bbj_v2_get_spoiler_bar()` to return live data so `front-page.php` houseboard flips from placeholder to real — depends on BB27 cast actually being entered into the Spoiler Bar editor (content work, not code).

---

### Sprint B — Player + Season profiles ✅ (shipped 2026-04-24)

**Why:** Every feed update, post, and houseboard cell links to these. Highest-visited pages after homepage. Were previously running as stubs with old rounded/shadow card aesthetic.

**Scope shipped:**
- `single-bigbrother-players.php` — flat editorial aesthetic, player photo, vitals (age, hometown, occupation), season participation timeline, stats, related feed updates, related posts tagged with them.
- `single-bigbrother-seasons.php` — flat editorial aesthetic, sticky tab nav + scroll-spy, sidebar (TOC, Quick Facts, More Seasons, ad), evictions/comps tables from `wp_bbj_weeks`, object cache + `save_post` busters.

**Follow-up: data-complete pass ⬜**

Both pages currently render against partial data (BB27 cast still being entered, older seasons have gaps in `wp_bbj_weeks`, finish places not all backfilled). Schedule a QA sweep once a season is fully populated:

- Walk a fully-loaded season + each cast member's player profile end-to-end on desktop
- Mobile QA pass on the same set — sticky tab nav, sidebar collapse, evictions/comps table overflow, photo aspect ratios
- Watch for empty-state holes (missing AFP, missing runner-up, week with no comps) and confirm graceful fallbacks
- Re-check the placeholder/fallback paths added in commits 07f18c8 (memorable-moments → `post_title`) and de9e05b (`.wrap` max-width) to make sure real data hasn't reintroduced the original layout issues
- Lighthouse / PageSpeed both page types once real images are in

**Trigger:** when the BB27 cast + spoiler bar are fully entered (Sprint A follow-up content work) — that's the first season with complete data top-to-bottom.

---

### Sprint C — Houseguests + Seasons directories 🟡 (skeletons shipped 2026-04-25)

**What shipped:**
- `archive-bigbrother-players.php` → `/bigbrother-players/` — server-rendered card grid (~365 players in one query), vanilla-JS filter bar: search-as-you-type, season select, status select (Winner/Runner-up/Played), sort (name asc/desc, most HOH, most POV, most seasons). Card shows avatar/initial, name, age, location, status badge, HOH/POV/NOM/Votes stat row. Skeleton aesthetic — inline styles, no design yet.
- `archive-bigbrother-seasons.php` → `/bigbrother-seasons/` — flat table: Season / Start / End / Winner / AFP / Runner-up. Rows link to season profile; name cells link to player profile. Mobile horizontal-scroll wrapper.
- Data layer: new `inc/archives-data.php` with `bbj_v2_archive_all_players()` + `bbj_v2_archive_all_seasons()`. Single SQL pass each, no caching yet.
- Header nav `/houseguests/` + `/seasons/` links retargeted to the actual CPT archive slugs; `single-bigbrother-players.php` breadcrumb same.

**Follow-ups (when designs land):**
- Claude Design pass for both archive aesthetics
- Add caching (object cache + `save_post` busters) once page is hit enough to matter
- Player archive: pagination or virtualization if 365 cards becomes a perf issue (currently fine in skeleton testing)
- Status filter semantics — for skeleton, "Winner/Runner-up/Played" derived from career-best `finish_place`; doesn't surface "currently in jury" or "currently evicted" because those are season-specific
- Seasons archive: click-to-sort columns, filter by decade, season photo column

**Done-when (full sprint):** both archives have a designed aesthetic, filter UX matches the design, and the data is cached.

---

### Sprint D — Feed Update Hub 🟡 (skeleton shipped 2026-04-25)

**What shipped:** see "What's shipped" up top — full design layout ported, real data wired (11.5k+ live-feed-updates, type/location terms, comment counts, sidebar Live Right Now from spoiler bar). DOM-level category-tab + checkbox-filter + search + sort works on the visible page; pagination via `?paged=N`.

**Follow-ups (when actual user need arises):**
- 🔒 Auto-refresh every 30s — slated as **Premium-only** feature (paywall via MemberPress). Surface a "Refresh" button gated by membership
- Vote interactivity — `wp_bbj_feed_ratings` table exists; needs REST endpoint + cast-vote/poll JS
- Pinned mechanic — currently shows most-recent update in featured slot. Add a `_bbj_pinned` post-meta flag + admin UI
- Trending hashtags — backburner content-mining job (scan title/content hourly for top mentions outside stop-list)
- Houseguest chips per update — auto-detect from content scan once trending-hashtag job exists
- Quote blocks — defer until content authors actually use a quote field
- Switch DOM-level page filter to AJAX/REST so filters apply across all 11.5k updates, not just the loaded page
- Newsletter form — wire to whichever ESP is on the homepage Newsletter widget
- Real "Next eviction" date instead of "Thursday" hardcode — needs a season-level or settings field
- Mobile polish — sidebar stacks correctly today but layout pass needed once Claude Design returns

**Cleanup follow-ups:**
- Trash the orphaned WP page at `/feed-updates/` (post ID 50269 on local; check IDs on staging+prod) — CPT archive rewrite wins request resolution so the page is unreachable, but it's clutter
- 301 `/live-feed-archives/*` → `/feed-updates/` via EPS Redirects plugin (old daily roundup URL pattern; 2 internal posts still link there)
- The unused `page-feed-updates.php` template can be deleted from the theme — nothing references it now

---

### Sprint E — Notifications system ⬜

**Scope:** one shared data source, two surfaces.

- Header bell → lightweight modal dropdown (image #16 pattern): last N notifications, "View All Notifications" link
- `/dashboard?tab=notifications` — full paginated list, mark-read, dismiss, empty state
- REST endpoint for both, lazy-loaded so logged-out users don't pay the cost
- Schema already exists in `bigbrotherjunkies-data` plugin — review what's there and fill in gaps

**Done when:** new comment reply pings the bell (unread badge), click modal shows it, click through to thread marks read.

---

### Sprint F — Dashboard pane cluster (Settings / Profile / Saved / Activity) ⬜

**Why batched:** all share user-context data fetches — efficient to build together.

**Scope:**
- `/dashboard?tab=settings` — name, email, avatar, notification prefs, password change
- `/dashboard?tab=profile` — public profile (bio, karma, comment history) — ALSO the `/users/<username>` public URL
- `/dashboard?tab=saved` — bookmark list. Needs a "save" button wired on post cards. `wp_bbj_saves` table or user meta.
- `/dashboard?tab=activity` — chronological personal feed (your comments, reactions, follows)

**Done when:** a user can edit their profile, save a post from the homepage, see it in Saved, and browse their recent activity.

---

### Sprint G — Premium pane ⬜

**Scope:**
- `/dashboard?tab=premium` — MemberPress integration. Shows tier, renewal date, "Manage subscription" link, past invoices if easy
- "ACT" badge in sidebar wires to real membership state (currently hardcoded)

**Done when:** active members see their real status; cancelled members see re-up CTA.

---

### Sprint H — Comments redesign ⬜ 🔖 *(separate spec when ready)*

React-based, lazy-loaded. You have a separate design in flight. When you're ready to scope, we'll brainstorm.

---

### Sprint I — Feed Updates admin pane ✅ (shipped 2026-04-23)

**MVP shipped:**
- `/admin?tab=feed-updates` — quick-post form (headline + details + image + category + mode + social toggles) + 50-row scannable list + inline edit + force-delete.
- REST: `POST /bbjd/v1/feed-updates/create` extended (user-written title/details/update_type; Next.js content+mode path still works); new `PUT /bbjd/v1/feed-updates/{id}` and `DELETE /bbjd/v1/feed-updates/{id}`.
- Permission: `PermissionChecker::userCan('feed_updates')` — admin permissions UI drives access.
- PUT explicitly skips Bluesky/Facebook cross-posting (no re-posts on typo fixes).

**Deferred to follow-ups:**
- Image editing on existing rows (MVP: delete + re-post)
- Search within admin list
- Filter-by-category in admin list
- Pin / star updates
- Bulk actions
- Infinite scroll beyond 50
- Drafts / scheduled posting
- Category manager card in Settings (separate spec — lets you edit `update_type` terms without wp-admin)
- Outer guard on `page-admin.php` is still `bbj_v2_require_admin()`; to open this pane to `updater` / `second_in_command` roles later, flip the outer guard to a per-tab `bbj_v2_require_permission()` model
- N+1 query tune-up on the 50-row render (~150 queries today — batch via `wp_get_object_terms(array_of_ids, ...)` + single SUM/GROUP BY on `wp_bbj_feed_ratings`)
- Category pill on newly-created client-prepended rows (shows after refresh today)

---

### Sprint J — Stats page (public) + Stats admin pane ⬜

**Scope:**
- `/stats` — site-wide stats: total players tracked, total seasons, total updates, most-quoted players, etc. Static + cached.
- `/admin?tab=stats` — GA4 + Google Search Console dashboard (traffic, top pages, referrers, search queries)

**Done when:** public stats page is indexable; admin stats pane shows last 30 days of traffic without opening Google Analytics.

---

### Sprint K — Compare ⬜

**Scope:**
- `/compare` — picker (select 2 players)
- `/compare/<player-a-slug>-vs-<player-b-slug>` — side-by-side stats, shareable URL, schema.org ComparisonPage if applicable
- Quick-compare modal on player profiles — lets user pick a second player, commits to the full compare URL

**Done when:** deep-linking `/compare/dr-will-vs-dan-gheesling` renders the matchup; modal-to-URL flow works.

---

### Sprint L — Player Map ⬜

**Scope:**
- `/player-map` — geographic map (Leaflet or similar) pinning each houseguest's hometown
- Filter by season, click pin → player card preview → link to profile

**Done when:** you can see the BB26 cast clustered vs BB8 (or whatever) at a glance.

---

### Sprint M — Search + 404 ⬜

**Scope:**
- `search.php` — unified results across posts, players, seasons, feed updates. Filters by type.
- `404.php` — branded, helpful (search box, "popular now" links)

**Done when:** search finds things; 404 doesn't look like a wp-admin page.

---

### Sprint N — Contact page ⬜

**Scope:** shortcode (`[bbj_contact]`) that renders a simple form. Drop it into any WP page.

---

### Sprint O — Admin panes (one per sprint, driven by pain) ⬜

Each is its own small sprint. Order is negotiable.

- `/admin?tab=comments` — comment moderation queue (the wp-admin one is painful)
- `/admin?tab=announcements` — broadcast to all users' notification inboxes
- `/admin?tab=users` — user list + bulk cleanup (after dead-member purge)
- `/admin?tab=players` — CRUD (currently in bbj-v2 plugin's old UI)
- `/admin?tab=seasons` — CRUD (currently in bbj-v2 plugin's old UI)
- `/admin?tab=posts` — "old-lady-friendly" composer (see below — separate design)
- `/admin?tab=preview-as` — role impersonation for admins to QA mod/admin-gated UI
- `/admin?tab=ads` — trimmed-down ad hub (later — lower priority)
- `/admin?tab=content-engine` — Facebook post generator (lowest priority; mostly keep "Generate" helper)

---

### Sprint P — Custom post editor (the "old-lady-friendly" composer) ⬜ 🎨

**Reference design:** image #18 — Next.js editor mockup.

**Scope:**
- Strip-down form: Title, content (basic toolbar: B/I/H2-H4/lists/quote/link/image/code/table/color), Season dropdown (single select, required), Featured image upload (drag-drop, required), SEO title (with AI-generate button), auto-slug, Publish checklist
- Publish button gated on checklist — can't publish without all boxes checked
- Draft auto-save

**Why later:** big build, affects daily authoring flow. Worth a proper spec + brainstorm session before starting.

---

### Sprint Q — Polling system ⬜ 🔖

**Scope TBD.** Its own CPT + voting + aggregation + sidebar widget. Own spec when we're ready.

---

## Parked / deferred

- Comments redesign (has its own design in flight)
- Custom Gutenberg blocks (stat-highlight, timeline-from-feeds) — after full site coverage
- Dark-mode polish across all new templates
- Mobile responsive sidebar for admin/dashboard (desktop-only for v1)
- PageSpeed profiling pass
- Role/permission system beyond WP caps (currently just `manage_options` / `edit_posts`)
- Role simulator in admin ("Preview as" — partially overlaps with `/admin?tab=preview-as` in Sprint O)
- Login reliability hardening for FB/IG WebViews (see `project_login_reliability` memory)

---

## How to update this doc

- Strike through a sprint with `~~text~~` when shipped, or flip the emoji to ✅ and move it to "What's shipped" up top
- When a sprint gets a spec, add 📝 next to it + link to `docs/superpowers/specs/YYYY-MM-DD-<slug>-design.md`
- When a sprint gets a plan, add 📝 next to it + link to `docs/superpowers/plans/...`
- If priority changes, just renumber — letters are for reference, not strict order

Related memories:
- `project_bbj_v2_theme_state.md` — short-form forward state (prose, less detailed than this doc)
- `project_admin_shell_architecture.md` — front-end admin architecture decision
- `project_homepage_redesign_progress.md` — closed-out homepage sprint history
- `feedback_admin_page_safeguard.md` — line-1 safeguard rule
