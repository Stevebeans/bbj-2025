# Season Profile (single-bigbrother-seasons.php) — Design

**Status:** drafted 2026-04-24, awaiting user review.
**Design source:** `.claude/claude-design/bbj-season-profile/bbj-home-page/project/BBJ Season Profile.html`
**Sibling pattern:** `single-bigbrother-players.php` + `inc/player-profile-data.php` (shipped 2026-04-24).

---

## Goal

Replace the legacy BBJ-theme single-season template with a magazine-style profile page on bbj-v2-theme. Functional first — only sections backed by existing data ship in this iteration. Layout shells for deferred sections are NOT pre-built; we'll add them as their data lands.

## Why now

Player Profile shipped today. Season Profile is the natural next page in the editorial set, and the design + data discovery are fresh in context. Also: the user wants to think through Memorable Moments before BB28 starts — folding it into "Top Feed Updates by votes" (decision below) closes that loop without any new data model.

---

## In scope (MVP — ships in this iteration)

Sections that render with data we already have on staging/prod:

1. **Hero** — season number, post title, 4 strip stats, action buttons
2. **Season Switcher** — pills for nearby ±5 seasons + "All seasons" link
3. **Sticky Tab Nav** — only includes sections we render (no broken anchors)
4. **Overview** — `post_content` lead text + Season Facts dl
5. **Winners Podium** — 2-card (Winner + Runner-up). AFP card hidden until backfill.
6. **Cast Grid** — 8-col mini cards
7. **Eviction Order Table** — full data: week, day, houseguest, vote count, type, evicted-by HoH
8. **Comp Winners Weekly Table** — week, HoH, PoV, Nominees, Veto Used On
9. **Top Feed Updates** ← acts as Memorable Moments — top-N by `total_rating` within season's date window
10. **Articles** — category-tagged BBJ blog posts about this season

**Sidebar (sticky):** TOC · Quick Facts · More Seasons · 300×600 ad slot.

## Out of scope (deferred — no data yet)

- Twists & Powers (no twists CPT)
- Curated Memorable Moments quote grid (collapsed into Top Feed Updates above)
- Ratings + 6 gauges (no poll system)
- Sidebar Records Set (no records model)
- Sidebar Fan Poll · Best Moment (no poll system)
- Hero "tagline" subtitle (no season-tagline field)
- "Season-defining twist" callout in Overview (no twists data)
- Action button "Watch on Paramount+" (no per-season streaming URL field)
- Action button "⇣ Season Tracker CSV" (no CSV export endpoint yet)

These sections are NOT scaffolded as "coming soon" placeholders. They're omitted entirely; the sticky tab nav reflects that. We'll add them as separate features when their data lands.

---

## Data sources (per section)

| Section | Source(s) | Fallback for missing data |
|---|---|---|
| Hero name/number | `wp_posts.post_title`, derive number via `preg_match('/\d+/')` | n/a |
| Hero strip: Winner | Junction: `WHERE bbj_season=X AND finish_place=1` → join player | "TBD" if season has no finish_place=1 (current season) |
| Hero strip: Days | Aggregate from junction (max evicted_date − season start) OR `wp_bbj_seasons.end_date − start_date` | Hide if neither available |
| Hero strip: HG count | `COUNT(*) FROM wp_bbj_v2_player_season WHERE bbj_season=X` | Always present if any junction rows |
| Hero strip: Prize | Postmeta `bbj_prize_amount` if exists, else fall back to a hard-coded constant per BB era (`$750k` for current, `$500k` for older) | Hide if cannot determine |
| Season switcher | `get_posts(['post_type'=>'bigbrother-seasons', 'numberposts'=>11, ...])` window centered on current season | n/a |
| Overview lead | `post_content` (filtered through `the_content`) | "Season recap coming soon." |
| Season Facts dl | Mix of `wp_bbj_seasons` (legacy) + post_date + junction-derived counts. Use **LEFT JOIN** pattern from player profile. | Show whichever rows have data |
| Winners Podium | Junction `finish_place=1` and `=2`, joined with player data via the `id OR post_id` lookup pattern | Hide podium entirely if no `finish_place` data (current season) |
| Cast Grid | `bbj_v2_player_profile_castmates`-style query but for ALL houseguests (incl. winner). Reuse the `id OR post_id` join. | n/a |
| Eviction Order | `wp_bbj_weeks` JOIN `wp_bbj_weeks_players WHERE evicted=1`. Vote count = `COUNT(DISTINCT player_id) FROM weeks_players WHERE week_id=X AND voted_for=evictee_id` (the votes-cast). HoH name = `weeks_players WHERE week_id=X AND hoh=1`. Type derived: 2 evictions same week → Double; week_num = max → Finale; else Regular. | If no `wp_bbj_weeks` data for the season, show simple table from `bbj_evicted_date` + `finish_place` only |
| Comp Winners | `wp_bbj_weeks_players` rows grouped by week: hoh=1 → HoH cell, pov=1 → PoV cell, nom=1 → Nominees list, saved=1 → Veto used on | If no weeks data, hide section |
| Top Feed Updates | `WHERE post_type='live-feed-updates' AND post_status='publish' AND post_date BETWEEN season_start AND season_end ORDER BY (postmeta total_rating + 0) DESC LIMIT 9` | Hide section if 0 results (off-season + pre-feed-launch seasons) |
| Articles | `WHERE post_type='post' AND wp_term_relationships → term_taxonomy.term_id = season's category` | Need to confirm category convention at impl time. Hide if no matches. |
| Sidebar TOC | Derived from sections actually rendered (no dead anchors) | n/a |
| Sidebar Quick Facts | Same as Section Facts but condensed | Use whichever rows have data |
| Sidebar More Seasons | `get_posts` for ±4 nearest seasons | n/a |

**Date-window for Top Feed Updates:** for legacy seasons (have `wp_bbj_seasons` row) use `start_date / end_date`. For modern seasons (BB22+) without that row, fall back to `season post_date` as start, `start + 100 days` as a generous end. Clean enough for MVP; refine later if seasons start overlapping.

---

## File structure

Mirror the player profile pattern.

**New files:**
- `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php` (the template — currently exists in old form, will be rewritten)
- `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php` (helper functions, namespaced `bbj_v2_season_profile_*`)
- `wp-content/themes/bbj-v2-theme/css/single-bigbrother-seasons.css` (page-specific styles ported from the design HTML)

**Modified files:**
- `wp-content/themes/bbj-v2-theme/inc/enqueue.php` — register the new CSS for the season profile page

**No plugin changes** for MVP. Data reads only.

## Helper function signatures (to mirror player profile)

```php
function bbj_v2_season_profile_data(int $post_id): array
function bbj_v2_season_profile_cast(int $post_id): array
function bbj_v2_season_profile_evictions(int $post_id): array        // wp_bbj_weeks-driven
function bbj_v2_season_profile_comps(int $post_id): array            // wp_bbj_weeks-driven
function bbj_v2_season_profile_top_feed_updates(int $post_id, int $limit = 9): array
function bbj_v2_season_profile_articles(int $post_id, int $limit = 4): array
function bbj_v2_season_profile_neighbors(int $post_id, int $window = 5): array
```

Each returns a normalized array shape that the template can iterate without further processing — same convention the player profile follows.

---

## Caching

Object cache group: `bbj_v2`, TTL 300s (matches existing convention).

Cache keys per season:
- `season_profile_data_{post_id}`
- `season_profile_cast_{post_id}`
- `season_profile_evictions_{post_id}`
- `season_profile_comps_{post_id}`
- `season_profile_top_feed_{post_id}`
- `season_profile_articles_{post_id}`

**Cache busters** (add to existing patterns):
- `save_post_bigbrother-seasons` → bust all keys for that season
- `save_post_bigbrother-players` → bust all season caches that include this player (broad — could just bust all, frequency is low)
- `save_post_live-feed-updates` → bust top-feed cache for the current season only (lookup via `bbj_v2_current_season` option)
- `save_post_post` → bust articles cache for any seasons whose category matches the post's categories

For the launch we can be coarse — bust all season caches on any of these saves. Optimize later if a measurable problem.

---

## Edge cases & data drift handling

Confirmed during today's player profile build (see `memory/references/bbj_data_schema.md`):

1. **`wp_bbj_seasons` row missing for BB22+** — use LEFT JOIN, fall back to `wp_posts.post_title` for name, derive abbreviation from title via regex.
2. **`wp_bbj_players.post_id = 0` for modern players** — match by `WHERE post_id = %d OR (id = %d AND post_id = 0)` everywhere player rows are joined.
3. **`finish_place` is the column name** (not `bbj_finish_place`).
4. **`finish_place === 1/2`** as primary Winner/Runner-up signal, not the season_winner/runner_up post-pointer.

Any new query in this build follows those rules. The schema reference doc is the source of truth.

## Sticky tab nav behavior

The current player profile doesn't use a sticky in-page tab nav. The season profile design does. Implementation:
- The tab nav is sticky-positioned (`position: sticky; top: 0`).
- Tabs scroll-spy: as the page scrolls, the tab whose section is in view gets the `.on` class. Use `IntersectionObserver` (vanilla JS, ~30 lines).
- Each tab is `<a href="#section-id">`; clicking jumps with smooth scroll.
- The list of tabs is generated from a PHP array of sections that actually rendered, so deferred sections never appear as dead links.

---

## Open issues to resolve at implementation time

1. **Articles category convention.** Need to confirm: do BB-season-specific articles get tagged with a "BB27" category, or is there a different scheme (postmeta, custom taxonomy)? Resolve by inspecting a known recent article on staging before writing the article query.
2. **Prize amount source.** Currently no per-season prize field. Options: hardcoded BB-era constant, postmeta override, OR skip the strip-stat for now.
3. **Action buttons.** Hero shows "Live Feed Updates" / "Watch on Paramount+" / "⇣ CSV". For MVP, ship "Live Feed Updates" only (links to the existing `BBJ Feed Updates Hub.html` page). Defer the others.
4. **Cast Grid status tags.** Design has Winner/Runner-up/AFP/Jury/Out. We can derive Winner/Runner-up from `finish_place`. Jury from `current_jury=1`. Out for everyone evicted who isn't jury. AFP requires the season's afp pointer — same caveat as Winners Podium 3rd card. AFP tag will simply not appear until that data lands.

These don't block writing the implementation plan; resolve at implementation step 1.

---

## Plan to verify "done"

After staging deploy:
1. Hit `https://stg-wp.bigbrotherjunkies.com/bigbrother-seasons/big-brother-27/` — current season, modern data, recent feed updates → expect Cast Grid populated, Eviction Order populated through current week, Top Feed Updates populated, Comp Winners populated, no AFP card, no Articles section if no BB27 category exists.
2. Hit `bigbrother-seasons/big-brother-13/` — legacy season, Rachel won → expect Winners Podium with Rachel + runner-up, full Cast Grid, Eviction Order from junction (without `wp_bbj_weeks` data → simpler table OK), Comp Winners section hidden if no weeks data, Top Feed Updates likely empty (predates feed updates).
3. Hit `bigbrother-seasons/big-brother-7/` — old season Janelle was in, no winner among local cast we tested → expect graceful empty states for sections without data.

Goal: at least one of the three test seasons exercises every conditional render branch.
