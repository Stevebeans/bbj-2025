# BBJ v2 Theme — Roadmap

> Living document. Edit freely — I'll update it as sprints ship.
> Last updated: 2026-04-22

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
- **Feed Updates admin pane** (`/admin?tab=feed-updates`) — quick-post form (headline + details + image + category + mode + social toggles) with 50-row list below, inline edit + force-delete. Permission gated via `PermissionChecker::userCan('feed_updates')` so the native permissions UI drives access. REST: extended `POST /bbjd/v1/feed-updates/create` to accept user-written titles/details/taxonomy; added PUT + DELETE handlers; PUT never re-posts to social. See `docs/superpowers/specs/2026-04-23-feed-updates-admin-pane-design.md`. Sprint I scope pulled forward. 🟡

---

## Architectural decisions locked in

- **Directory split into separate pages** (not `/directory?tab=x`). Each gets its own URL + SEO + schema:
  - `/houseguests` — player directory
  - `/seasons` — seasons list
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

### Sprint B — Player + Season profiles ⬜

**Why:** Every feed update, post, and houseboard cell links to these. Highest-visited pages after homepage. Currently running as stubs with old rounded/shadow card aesthetic.

**Scope:**
- `single-bigbrother-players.php` — flat editorial aesthetic, player photo, vitals (age, hometown, occupation), season participation timeline, stats, related feed updates, related posts tagged with them
- `single-bigbrother-seasons.php` — flat editorial aesthetic, cast list, winner/runner-up/AFP, season stats, week-by-week summary, related posts

**Done when:** both page types render clean and the homepage + archives link to them with no 500s.

---

### Sprint C — Houseguests + Seasons directories ⬜

**Scope:**
- `archive-bigbrother-players.php` → `/houseguests` — grid of player cards, filters (season, alive/evicted, winner status), search
- `archive-bigbrother-seasons.php` → `/seasons` — grid of season cards with winner + photo

**Done when:** both archives are browsable with working filters.

---

### Sprint D — Feed Update Hub ⬜ 🎨

**Reference design:** `.claude/claude-design/bbj-home-page/project/BBJ Feed Updates Hub.html` + the screenshot you just shared (BB27 header, LIVE RIGHT NOW sidebar, filter chips, category checkboxes).

**Scope:**
- `archive-live-feed-updates.php` → `/live-feed-updates/` — auto-refresh thread with "ON THE FEEDS NOW" live banner, stats row (today/week/season), pinned discussion, filter chips (ALL / DRAMA / CEREMONY / STRATEGY / CHITCHAT / MEDIA), sort + date + search, day-grouped timeline
- Right rail: LIVE RIGHT NOW panel (HoH / Veto / Block / Next Evict) + WATCH ON PARAMOUNT+ CTA + category filter
- `single-live-feed-updates.php` — permalink view of a single update
- Auto-refresh every 30 sec via tiny REST poll (only on archive view, not single)

**Done when:** archive live-updates without page reload, filters actually filter, LIVE RIGHT NOW pulls from spoiler bar data (depends on Sprint A).

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
