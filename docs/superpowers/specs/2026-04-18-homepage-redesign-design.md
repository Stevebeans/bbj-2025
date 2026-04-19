# Homepage redesign — design spec

**Date:** 2026-04-18
**Status:** Approved, ready for implementation plan
**Owner:** Steve Beans
**Scope:** Rebuild `front-page.php` for the `bbj-v2-theme` with a new 3-column editorial layout, richer feed-update cards, new sidebar widgets, and a top status strip. Adds two taxonomies to the `live-feed-updates` CPT.

## Goals

1. Restructure the homepage into a 3-column editorial grid that reads like a live-blog newsroom (see `BBJ Home Polished` mockup in `.claude/claude-design/bbj-home-page/`).
2. Preserve the H1-driven SEO pattern from the old theme — a single H1 on the hero post whose title targets "Big Brother N Spoilers".
3. Surface more signal above the fold: hero + curated spoilers list + Houseboard game state + ad inventory.
4. Add live-blog polish to the feed-update list (timeline dots, category + location chips, attribution, reply counts).
5. Keep PageSpeed intact — no new JS on the critical path, 5-minute cache on every query, reserved ad heights, `fetchpriority="high"` on the LCP hero image.

## Non-goals (v1)

- Live-polling House Pulse (5-minute cache is fine).
- Admin-curated event countdown beyond CBS airings.
- Feed-update comment thread UI.
- `update_type` filter UI on the feed list.
- Polished off-season variant (functional only).
- Real-time season premiere countdown widget.

## Page structure — desktop (≥ lg)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ STATUS STRIP — dark, full-width                                          │
│ ● BB27 · DAY 42 · 49% ELAPSED · NEXT CBS SHOW: Thu 8pm ET / 5pm PT ·    │
│   BB TIME 3:42 PM PT                                                     │
└──────────────────────────────────────────────────────────────────────────┘

┌────────────────────────┬───────────────────────────┬────────────────────┐
│ HERO POST (left)       │ MORE BB27 SPOILERS (ctr)  │ SIDEBAR (right)    │
│                        │                           │                    │
│ [4:3 hero image]       │ H2 More BB27 Spoilers     │ · Houseboard       │
│ H1 post title          │ 01 · post title           │ · Season Stats     │
│ excerpt · 2–3 lines    │ 02 · post title           │ · Recent Comments  │
│ date · byline          │ 03 · post title           │ · Sticky Ad        │
│                        │ [300×250 square ad]       │ · Paramount+       │
│                        │                           │ · Socials          │
│                        │                           │ · Newsletter       │
└────────────────────────┴───────────────────────────┘                    │
┌────────────────────────────────────────────────────┐                    │
│ HOUSE PULSE — 8 hourly bars, colored by volume     │ sidebar continues… │
├────────────────────────────────────────────────────┤                    │
│ H2 LATEST FROM THE FEEDS                           │                    │
│  5 rich feed-update cards (timeline + chips)       │                    │
│  Quick hits — 10 compact rows                      │                    │
│  "See all feed updates →"                          │                    │
├────────────────────────────────────────────────────┤                    │
│ [Leaderboard ad — 728×90 desktop / 320×50 mobile]  │                    │
├────────────────────────────────────────────────────┤                    │
│ H2 MORE BB27 STORIES                               │                    │
│ 3×3 post grid — thumbnail + title + date           │                    │
└────────────────────────────────────────────────────┘────────────────────┘
```

Main content column span = `lg:col-span-8`. Sidebar = `lg:col-span-4` with sticky-ad widget using `position: sticky; top: <header-offset>`.

## Page structure — tablet (md 768–1023)

Two-column: main content (full width of section) over sidebar. No 3-col split for hero / spoilers-list — they stack vertically inside the main column. Sidebar renders to the right.

## Page structure — mobile (< md)

Single column. Order top → bottom:

1. Status strip
2. Hero post (image + H1 + excerpt + date)
3. Houseboard (pulled up from sidebar for game-state above fold)
4. More BB27 Spoilers (3 titles)
5. Square ad (300×250)
6. House Pulse
7. Latest Feeds (5 rich + 10 compact)
8. Leaderboard ad (320×50 mobile size)
9. More BB27 Stories (9 cards, 1-col)
10. Remaining sidebar widgets — Season Stats → Recent Comments → Paramount+ → Socials → Newsletter
11. Sticky ad renders inline as a normal card (not sticky on mobile)

## File layout

```
wp-content/themes/bbj-v2-theme/
├── front-page.php                                (rewritten — orchestrator)
├── inc/
│   └── homepage-data.php                         (NEW — queries + cache helpers)
├── template-parts/
│   ├── home/
│   │   ├── status-strip.php                     (NEW)
│   │   ├── hero-post.php                        (MODIFIED — fits left column, 4:3 image)
│   │   ├── more-bb-spoilers.php                 (NEW)
│   │   ├── house-pulse.php                      (NEW)
│   │   ├── latest-feeds.php                     (NEW — replaces current feed-updates.php)
│   │   ├── more-bb-stories.php                  (NEW — 3×3 grid)
│   │   └── houseboard.php                       (MODIFIED — styled to fit sidebar card width)
│   ├── sidebar/
│   │   ├── season-stats.php                     (NEW)
│   │   ├── recent-comments.php                  (NEW)
│   │   ├── sticky-ad.php                        (NEW)
│   │   ├── paramount-plus.php                   (NEW — static block)
│   │   ├── socials.php                          (NEW — static block)
│   │   └── newsletter.php                       (existing, unchanged)
│   └── content/
│       └── feed-update-card.php                 (NEW — shared rich card)

wp-content/plugins/bigbrotherjunkies-data/
└── src/
    └── Taxonomies/                              (NEW dir)
        ├── UpdateTypeTaxonomy.php
        └── UpdateLocationTaxonomy.php
```

Files removed or obsoleted:

- `template-parts/home/feed-updates.php` — deleted in favor of `latest-feeds.php`.
- `template-parts/home/recent-posts.php` — deleted (was the previous 6-post generic list; replaced by `more-bb-stories.php`).
- `sidebar.php` — kept, but its content is rebuilt to include the new widget set.

## Data layer

All homepage queries live in `inc/homepage-data.php`. Each helper caches in the `bbj_v2` object-cache group. TTLs below.

| Helper | Returns | Cache key | TTL |
|---|---|---|---|
| `bbj_v2_is_active_season()` | `bool` | `homepage_active_season` | 300s |
| `bbj_v2_current_season_number()` | `int` (e.g. 26) | `homepage_current_season_number` | 300s |
| `bbj_v2_current_season_slug()` | `string` (e.g. `"big-brother-26"`) | `homepage_current_season_slug` | 300s |
| `bbj_v2_homepage_status()` | status strip payload (day, pct elapsed, next show, bb time) | `homepage_status` | 60s |
| `bbj_v2_homepage_more_spoilers($exclude_ids)` | 3 `WP_Post` objects | `homepage_more_spoilers` | 300s |
| `bbj_v2_homepage_bb_stories($exclude_ids)` | 9 `WP_Post` objects | `homepage_bb_stories` | 300s |
| `bbj_v2_homepage_house_pulse($hours=8)` | `array<int,int>` hour → count | `homepage_pulse` | 300s |
| `bbj_v2_homepage_latest_feeds($limit=15)` | array of feed-update post + term data | `homepage_feeds_15` | 60s |
| `bbj_v2_homepage_season_stats()` | `{hoh_leaders, pov_leaders, nom_leaders, week_count}` | `homepage_season_stats` | 300s |
| `bbj_v2_homepage_recent_comments($limit=5)` | comment rows | `homepage_recent_comments` | 60s |

Key queries:

- **More BB Spoilers**: `WP_Query(['category__and' => [current_season_term_id, spoilers_term_id], 'posts_per_page' => 3, 'post__not_in' => [$hero_id]])`.
- **BB Stories 3×3**: `WP_Query(['category_name' => current_season_slug, 'posts_per_page' => 9, 'post__not_in' => [$hero_id, ...$more_spoilers_ids]])`.
- **House Pulse**: direct `$wpdb->get_results` — `SELECT HOUR(post_date) AS h, COUNT(*) c FROM {$wpdb->posts} WHERE post_type='live-feed-updates' AND post_status='publish' AND post_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 8 HOUR) GROUP BY h`. Output normalized to 8 contiguous buckets (missing hours → 0).
- **Latest Feeds**: `WP_Query(['post_type' => 'live-feed-updates', 'posts_per_page' => 15])`. For each, attach `update_type` + `update_location` terms in a single post-loop pass.
- **Season Stats**: aggregate from `wp_bbj_v2_player_season` (counts of `hoh_wins`, `pov_wins`, `noms` per player ID, joined back to `wp_bbj_players` for names). Returns top 3 per category.

### Cache invalidation

| Hook | Keys busted |
|---|---|
| `save_post_post` | `homepage_more_spoilers`, `homepage_bb_stories` |
| `save_post_live-feed-updates` | `homepage_feeds_15`, `homepage_pulse` |
| `save_post_bigbrother-seasons` | `homepage_status`, `homepage_season_stats`, `homepage_active_season`, `homepage_current_season_number`, `homepage_current_season_slug` |
| `update_option_bbj_v2_current_season` | all `homepage_current_season_*`, `homepage_more_spoilers`, `homepage_bb_stories`, `homepage_status`, `homepage_season_stats` |
| `update_option_bbj_v2_season_active` | `homepage_active_season`, `homepage_status` |
| `comment_post`, `wp_set_comment_status` | `homepage_recent_comments` |
| `created_update_type`, `edited_update_type`, `created_update_location`, `edited_update_location` | `homepage_feeds_15` (chips may have changed) |

## Taxonomies (plugin)

Both registered in `BigBrotherJunkies\Data\Taxonomies`, bootstrapped from the plugin's main class. Both are non-hierarchical, REST + GraphQL enabled.

### `update_type`

- **Post type:** `live-feed-updates`
- **Hierarchical:** false
- **Rewrite:** `update-type/<slug>`
- **REST / GraphQL:** enabled
- **Starter terms** (seeded on plugin activation if missing): Drama, Ceremony, Strategy, Competition, Alliance, Eviction, Punishment, Reward, Showmance
- **Chip color map** (theme-side, in `feed-update-card.php`): DRAMA → red-100/red-900, CEREMONY → green-100/green-900, STRATEGY → slate-100/slate-700, COMPETITION → amber-100/amber-900, ALLIANCE → indigo-100/indigo-900, EVICTION → gray-700/white, default → gray-100/gray-900

### `update_location`

- **Post type:** `live-feed-updates`
- **Hierarchical:** false
- **Rewrite:** `update-location/<slug>`
- **REST / GraphQL:** enabled
- **Starter terms** (seeded on plugin activation): HoH Bathroom, HoH Room, Backyard, Hammock, Kitchen, Living Room, Have-Not Room, Storage, Pergola, Bathroom, Diary Room
- **Chip styling:** neutral gray (single class, no per-term variation)

### Migration

Existing `live-feed-updates` records are left with no terms assigned. Cards render without chips when the terms are empty — no backfill needed.

## Components — markup contracts

### Status strip

- Dark bg (`bg-gray-900 text-white`), Oswald uppercase, `h-auto py-2 px-4`, horizontal dot separators between segments.
- Segments:
  1. `● BB<N> · DAY <X>` — red dot + season label + day counter computed from `season.start_date` to today (Pacific).
  2. `<pct>% ELAPSED` — `(days_elapsed / total_season_days) * 100`, rounded, clamped 0–100.
  3. `NEXT CBS SHOW: <when>` — see "Next CBS show rule" below.
  4. `BB TIME <hh:mm a>` — `bbj_v2_bb_time()`, Pacific.
- **Off-season mode** (`bbj_v2_is_active_season() === false`): strip swaps to `OFF-SEASON · LAST SEASON: BB<N> · BB<N+1> PREMIERES <date>`, where `N` is the `bbj_v2_current_season` option (admin keeps it pointed at the most recently completed season during off-season). Premiere date from option `bbj_next_season_premiere` (nullable). If null, strip shows only the first two segments.

### Next CBS show rule

```
$now = DateTimeImmutable('now', new DateTimeZone('America/New_York'))
$airings = ['Sun 20:00', 'Wed 20:00', 'Thu 20:00']
$override = get_post_meta($current_season_id, 'bbj_next_show_override', true)
            // datetime string, nullable
```

1. If `$override` is a valid future datetime → use it verbatim.
2. Else compute the next of `$airings` from `$now`.
3. Format:
   - Same day (in ET): `"Tonight at 8pm ET / 5pm PT"`
   - Tomorrow: `"Tomorrow at 8pm ET / 5pm PT"`
   - Within 6 days: `"Thu at 8pm ET / 5pm PT"`
   - Else: `"Jul 17 at 8pm ET / 5pm PT"`
4. Off-season → segment hidden entirely.

Admin UI: single Meta Box text field `bbj_next_show_override` on the season CPT, accepts an ISO datetime.

### Hero post

- `<article>` in the left column.
- `<img>` 4:3 aspect, `fetchpriority="high"`, `loading="eager"`, sized via `srcset` for the registered `bbj_v2_index_hero` and `bbj_v2_index_mobile` image sizes.
- `<h1>` = post title. Exactly one H1 on the homepage.
- 2–3 line excerpt (`wp_trim_words($post->post_excerpt ?: $post->post_content, 40)`).
- Byline: date + author name (small, muted).
- Post-hero selector: reuse `_is_hero_post` meta; fallback to latest post in current-season category; final fallback to latest post.

### More BB<N> Spoilers (center column)

- `<section>` with `<h2>` `"More BB<N> Spoilers"` (dynamic).
- 3 `<article>` rows, each: thumbnail (`featured-thumbnail` size) + title (H3) + date.
- Below the 3 rows: `ad-placeholder` with `slot=homepage_right_mpu`, `size=300x250`, `mobile_size=300x250` (keeps CLS=0 across breakpoints since it's square).
- **Off-season:** heading becomes `"BB<N-1> Recap"`, query pulls from last completed season.

### House Pulse

- `<section>` full-width under the 3-col row.
- Title: `<h2>` `"House Pulse"` + small caption `"Updates/hr · last 8 hours"`.
- 8 bar divs in a flex row; each bar's height is fixed at 40px, width equally split.
- Bar color picked by the hour's count relative to the max in the window:
  - 0 of max → `bg-gray-200`
  - ≤20% → `bg-amber-200`
  - ≤50% → `bg-amber-400`
  - ≤80% → `bg-red-400`
  - > 80% → `bg-red-600`
- Hour labels under bars (small, every 2nd hour to save space).
- Empty state (all buckets 0 and active season): `"Quiet house · last update <X> ago"`.
- Off-season: section hidden.

### Latest Feeds (hybrid 5 + 10)

- `<section>` with `<h2>` `"Latest from the Feeds"`.
- First 5 items rendered via `feed-update-card.php`. Each card:
  - Left column: time (`3:24 PM`) + relative time (`18 min ago`, wrapped in `data-nosnippet` per prior SEO memory).
  - Colored timeline dot keyed to `update_type` term's color map.
  - Right column: `update_type` chip + `update_location` chip, then H3 title, then 2-line excerpt, then attribution (`@author_nickname`) + reply count + conditional CTA link.
- "Quick hits" sub-heading, then 10 compact rows: time + type chip + H4 title only.
- Bottom link: `"See all feed updates →"` → `/feed-updates/` archive.
- **Reply count** = comments count on the post (WP native). When comments are closed or the count is 0, the reply span renders `"No replies yet"` instead; the "Join the thread →" CTA hides entirely when comments are closed.
- **Attribution** = post author's display name prefixed with `@`; falls back to just the name if no username match.

### More BB<N> Stories (3×3)

- `<section>` with `<h2>` `"More BB<N> Stories"`.
- 9 `<article>` cards in a CSS grid: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`.
- Each card: thumbnail (`featured-thumbnail` size, 400×200) + H3 title + date.
- Hover: shadow lift, matches the existing design system pattern (see `.v2-primary-container-inner`).

### Sidebar widgets (order)

1. **Houseboard** — reuse existing `houseboard.php`, rework layout to a vertical card (2×2 grid becomes 1-col in the 320px sidebar width). HoH → PoV → Noms → Week #.
2. **Season Stats** — top 3 HoH winners, top 3 PoV winners, top 3 most-nominated. Each row: player name + count + tiny bar (% of max). Click → player page.
3. **Recent Comments** — last 5 approved comments site-wide. Each row: commenter avatar + name + excerpted content (30 chars) + link to the post + timestamp.
4. **Sticky Ad** — `ad-placeholder` with `slot=homepage_sidebar_sticky`, `size=300x600` (half-page) with `mobile_size=300x250`. Applied `class="lg:sticky lg:top-24"` so it pins during scroll at `lg+` only.
5. **Paramount+** — static promo block. Image + tagline + CTA "Watch on Paramount+" with affiliate URL placeholder.
6. **Socials** — static block with FB / IG / Twitter / Bluesky links and counts (counts can be hardcoded or pulled from the plugin's social sync — v1 uses hardcoded numbers, updated manually).
7. **Newsletter** — existing `newsletter.php`, unchanged.

## SEO

- **H1**: on the hero post's title only. One per page. The post title itself should contain the target keyword `"Big Brother <N> Spoilers"` — author discipline, not theme logic.
- **H2**: all section headings (More BB<N> Spoilers, House Pulse, Latest from the Feeds, More BB<N> Stories, The House, Season Stats, Recent Comments).
- **H3**: post titles inside lists (More BB<N> Spoilers rows, feed update cards, BB<N> Stories cards).
- **Internal linking**: each H2 section links to its archive (e.g., "Latest Feeds" wraps to `/feed-updates/`).
- **Schema.org**:
  - Site-level `WebSite` + `BreadcrumbList` already present from existing site setup; verify they still render on the new homepage.
  - Hero post: reuse current `NewsArticle` schema helper if present. Defer new schema work to a separate task.
- **data-nosnippet**: wrap relative time strings in the Latest Feeds rich + compact rows — per the memory note about stale "29 hours ago" Google snippets.
- **Section anchors**: each `<section>` gets an `id` (e.g., `id="house-pulse"`) so future deep-links work.

## Performance

- **LCP**: hero image. `fetchpriority="high"`, `loading="eager"`, proper `srcset`, no container-query layout that blocks painting.
- **CLS**: reserve space for every ad placeholder via `aspect-ratio` (already the pattern). Feed-update cards use a min-height so chip/attribution changes don't jump.
- **JS**: no new critical-path JS. House Pulse renders server-side (pure CSS bars). Sticky ad uses CSS `position: sticky`, no JS.
- **Caching**: every query helper caches 60–300s (see data layer table). `save_post_*` + taxonomy + option hooks bust.
- **Ads**: leaderboard ad renders with `ad-placeholder` mobile_size=320x50 (matches prior pattern). Sidebar half-page 300x600 hidden under lg.

## Accessibility

- One H1 enforced.
- Status strip is `aria-label="Current season status"`.
- House Pulse bars are `role="img"` + `aria-label="Updates per hour, last 8 hours"` on the container; individual bars get `<span class="sr-only">` text of count + hour.
- Feed update cards: `<article>` wrappers, post titles as H3, timeline dot is `aria-hidden="true"` (decorative).
- Timeline uses a real `<ol>` so screen readers get sequential structure.
- Skip-to-content link (already present in header) stays.

## Mobile-specific behavior

- Mobile order per "Page structure — mobile" section above.
- Sticky ad is NOT sticky on mobile (ad policy + PageSpeed).
- Latest Feeds: first 5 rich cards drop the left timeline gutter on mobile (time moves inline above chips). "Quick hits" rows compress to time + title only (chip hidden under sm).
- 3×3 grid becomes 1-col stack.

## Testing

- Manual smoke on desktop, `md`, and `<md` widths.
- Verify cache busting: create a new `live-feed-updates` post → homepage feed list updates within 60s (its own TTL), pulse within 300s.
- Verify off-season: set `bbj_v2_season_active = 0` in options → status strip + house pulse + feed sections transition correctly.
- Verify H1 uniqueness: view source, `grep -c '<h1'` → 1.
- Schema validation via Rich Results Test on the new URL.
- PageSpeed: target ≥ 90 desktop / ≥ 80 mobile, CLS ≤ 0.02.
- Lighthouse accessibility ≥ 95.

## Migration / rollout

1. Build on `feature/bbj-v2-theme`.
2. Deploy to staging via `push-staging`.
3. Verify on staging with real data (BB26 is currently the active season).
4. Merge to `master`, deploy to production.
5. Post-launch: monitor Breeze/Varnish cache and manually purge after first deploy.

## Open items tracked for later

- Season premiere countdown widget (off-season only) — static text for v1, live countdown is a future enhancement.
- `bbj_next_show_override` admin UI polish — v1 is a plain text datetime field on the season CPT Meta Box.
- Paramount+ affiliate link — needs a real URL before launch (placeholder for now).
- Socials follower counts — hardcoded initially; a sync job is out of scope.
- `feed-update-card.php` on archive/single pages — this spec covers the homepage version. Feed archive pages can adopt the same card in a follow-up.
