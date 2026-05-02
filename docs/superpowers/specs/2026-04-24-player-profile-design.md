# Player Profile (`single-bigbrother-players.php`) — Design Spec

**Date:** 2026-04-24
**Sprint:** B (Player + Season profiles)
**Status:** Approved, ready for implementation plan
**Design reference:** `.claude/claude-design/bbj-player-profile/bbj-home-page/project/BBJ Player Profile.html`

---

## 1. Goal

Ship a redesigned `single-bigbrother-players.php` based on the Claude Design mockup (editorial/magazine aesthetic). Every feed update, blog post, and homepage houseboard cell links to this template — it's the highest-visited page type after the homepage and currently renders a stub in the old rounded-card style.

**Done when:**
- Template renders without errors for any player record
- Layout matches the design for data we have
- Data-less sections are either removed cleanly or show a reserved "placeholder" card (see §7)
- Homepage links and feed-update author links resolve without 500s
- Mobile breakpoint (< 1000px) works
- Page is enqueuing its own CSS only on the player profile template

---

## 2. Design decisions

### 2.1 Typography (new — retires the Yanone brand lock)

| Role     | Font            | Weights          | Usage                                                  |
| -------- | --------------- | ---------------- | ------------------------------------------------------ |
| Display  | Oswald          | 400/500/600/700  | All headings, hero name (80px), section headers, stat numbers |
| Serif    | Source Serif 4  | 400/600/700 + it | Body copy in bio section, italic nicknames, pull-quotes |
| Sans     | Inter Tight     | 400/500/600/700  | Default body, meta, UI chrome                          |
| Mono     | IBM Plex Mono   | 400/500          | Tiny labels, breadcrumbs, stat keys, timestamps        |

This is the first template on the site using the new palette. Older templates continue with the legacy Roboto/Yanone until they're redesigned. The Google Fonts URL is enqueued conditionally (same `is_singular('bigbrother-players')` guard as the stylesheet) so other pages don't pay the fetch cost.

### 2.2 Color palette

CSS custom properties, set in `:root` of the template stylesheet:

```css
--bb-blue: #35546e;
--bb-blue-dk: #233C52;
--bb-yellow: #FFBF0F;
--bb-red: #D23B2B;
--bb-green: #1D8A5C;
--bb-purple: #6E4A9E;
--ink: #15181E;
--ink-2: #3A404B;
--muted: #6B7280;
--line: #E5E7EB;
--line-2: #D6DADF;
--paper: #FBFAF6;   /* page bg (eggshell) */
--paper-2: #F3F1EA; /* accent card bg, borders, dividers */
```

Cards render on `#fff` over the `--paper` page bg. The warm eggshell is intentional — it's the editorial-magazine feel the design is going for.

### 2.3 Site-wide eggshell (future, out of sprint)

User is leaning toward rolling the `--paper` eggshell to the site-wide body bg + relevant Tailwind tokens after seeing it in context on this template. **Not part of this sprint.** Decision point after player profile ships.

### 2.4 CSS approach

- New stylesheet: `wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css`
- Enqueued conditionally on `is_singular('bigbrother-players')` only — the design is CSS-heavy and should not load elsewhere
- Vanilla CSS with CSS custom properties; mirrors the design's token structure
- Not rewritten into Tailwind — the design uses vars + scoped classes that are faster to port and pixel-accurate. Future work: extract shared patterns to Tailwind components once more editorial templates ship

### 2.5 Chrome

Use the site's existing `get_header()` / `get_footer()`. Do not port the design's topbar/header/nav — header redesign is a separate sprint.

---

## 3. Data sources

| Table                          | Used for                                                                                             |
| ------------------------------ | ---------------------------------------------------------------------------------------------------- |
| `wp_bbj_players`               | Portrait, banner, first/last name, nickname, DOB, occupation, socials                                |
| `wp_bbj_geo` (by `post_id`)    | Hometown: `locality`, `administrative_area_level_1`                                                  |
| `wp_bbj_v2_player_season` (by `bbj_player`) | Per-season record: finish place, evicted date, HoH/PoV/nom/saved/votes/veto totals, current flags, misc |
| `wp_bbj_seasons` (by `bbj_season`) | Season title, abbreviation, start_date, end_date, winner/runner-up/afp                            |
| `wp_posts`                     | `post_content` as the bio copy, slug for permalink                                                   |

### 3.1 Derived values (computed in a helper, not in the template)

| Value             | Formula                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------- |
| Age now           | DateInterval between `date_of_birth` and `now`                                                |
| Age in house      | DateInterval between `date_of_birth` and `evicted_date` (or `season.start_date` if no evict)  |
| Days in house     | DateInterval between `season.start_date` and (`evicted_date` ?? `season.end_date` ?? `now`)   |
| Placement label   | Ordinal `bbj_finish_place`; "Winner" if rank 1, "Runner-up" if 2, "AFP" if `season.afp == player.post_id` |
| Week of eviction  | `floor((evicted_date - season.start_date) / 7) + 1`                                          |
| Career totals     | SUMs across junction rows WHERE `bbj_player = <post_id>`                                      |
| Season count      | COUNT DISTINCT `bbj_season` for this player                                                   |
| Castmates         | Junction rows WHERE `bbj_season = <current_season>` AND `bbj_player != <this_player>`         |

### 3.2 Season selection

The "current season" for hero/castmates is the player's **most recent** season by `bbj_season` start_date DESC. For active houseguests this is the ongoing season; for alumni it's their latest appearance.

---

## 4. Page structure (top to bottom)

### 4.1 Breadcrumb

```
Home  /  Houseguests  /  BB27  /  Keanu Soto
```

- "Home" → `home_url('/')`
- "Houseguests" → `/houseguests` (Sprint C target — OK if this 404s temporarily)
- "BB27" → permalink of the player's most recent season
- Player name → self, bolded, no link
- Mono font, uppercase, small

### 4.2 Hero

- Gradient background: dark blue → `--bb-blue` → faint purple (per design)
- Decorative radial gradients + vertical rule pattern
- Three-column grid: `auto 1fr auto` (portrait · meta · actions)
- Mobile (< 1000px): collapses to single column

**Portrait column:**
- 220px wide, 3:4 aspect, white 3px border, heavy drop shadow
- Render real profile picture from `wp_bbj_players.profile_picture` at `bbj_v2_profile_image` size
- Fallback: gradient background with player initials if no image
- **AFP badge** (absolute, top-left of portrait): only renders if the player's `post_id` matches any season's `season.afp`. Shows "AFP / Season XX"

**Meta column:**
- Kicker (mono caps, yellow): status line — `Houseguest · Season XX · {Winner|Runner-up|AFP|Jury|Pre-Jury|Active}`
- H1: player name, Oswald 80px, white
- Nickname (Source Serif italic, 22px, white 75% opacity) — only if `official_nickname` set
- Meta row: **From** (hometown), **Age** (in-house age), **Occupation**, **Days in house**. Pronouns dropped (no field)
- Tag chips (translucent pills):
  - "♥ America's Favorite" (gold, if AFP)
  - Jury status ("Jury member" / "Pre-jury") based on placement threshold
  - `Nx HoH`, `Nx PoV`, `Nx Nominated` — only rendered where N > 0

**Action column:**
- Compare → stubbed button, `aria-disabled="true"`, tooltip "Coming soon" (Sprint K)
- View Season → link to most recent season permalink
- Follow → omit entirely for MVP

### 4.3 Bio strip

Horizontal 5-column `grid-template-columns: repeat(5, 1fr)`, full-width card. Conditional cells — skip any row whose value is empty, redistribute remaining columns:

- Hometown (`locality, administrative_area_level_1`)
- Occupation
- Age in house
- Placement (e.g. "5th · AFP winner" or just "5th" or "Currently playing")
- Eviction ("Day 77 · Week 11" or "Still in house")

### 4.4 Bio & Background

Section header: "Bio & Background" + subtitle "The long version".

Layout: editorial float (not the design's grid).

- **`.copy`** is the main content area, rendering `the_content()` from the player's post
- **`.at-a-glance`** panel is **CSS-floated right** (`float: right; width: 220px; margin: 0 0 16px 24px`) so the bio text wraps around it naturally
- First paragraph of `.copy` gets a drop-cap treatment via `::first-letter`
- Pull-quotes are **not a separate field**. If the user wants one, they write a `<blockquote>` in the WP editor; the stylesheet styles it with the yellow left-border treatment from the design
- **`.at-a-glance`** dl rows are **all conditional** — only render rows that have a value:
  - Hometown (if geo data exists)
  - Birthday (if DOB — format "Aug 14, 1992")
  - Occupation (if set)
- Dropped (no field, defer): Height, Strengths, Weakness, Alignment, Fav show, Hashtag

### 4.5 Career statistics

Section header: "Career Statistics" + subtitle "Across N seasons" (N = distinct season count).

6-up grid:

| Stat        | Source                                               | Accent color |
| ----------- | ---------------------------------------------------- | ------------ |
| Seasons     | COUNT DISTINCT `bbj_season`                         | Blue         |
| HoH wins    | SUM `bbj_total_hoh`                                 | Yellow       |
| PoV wins    | SUM `bbj_total_pov`                                 | Green        |
| Nominated   | SUM `bbj_total_nom`                                 | Red          |
| Jury votes  | SUM `bbj_votes_received`                            | Blue         |
| Days        | SUM of per-season days-in-house                     | Purple       |

Each card: big Oswald number (44px) on top, mono label underneath, accent-colored 3px top border. Design also shows delta callouts ("+2 vs avg", "Wk 6", "★"). **Deltas are not in MVP** — placeholder `"—"` or hide. Requires cross-player averages.

### 4.6 Season history table

Section header: "Season History" + subtitle "Finale placements".

Columns: Season · Age · HoH · PoV · Nom · Votes · Days · Progress · Result

- Ordered by `bbj_season` start_date DESC
- Season cell: linked to single-season permalink
- Progress bar: percent = `(season_contestants - finish_place + 1) / season_contestants * 100` (higher bar = better placement). Season contestant count = COUNT of junction rows WHERE `bbj_season = X`
- Result pill: "Winner" / "Runner-up · 2nd" / "AFP · Nth" / "Jury · Nth" / "Evicted · Nth" / "Pre-jury · Nth"

### 4.7 Castmates grid

Section header: "Castmates · BB27" (BB27 = current season abbreviation dynamically) + subtitle "Who they played with".

8-column grid of 1:1 tiles. Each tile:
- Face: other player's `profile_picture` as a square crop; fallback gradient with initials
- Status tag (absolute, top-right): Winner / Runner-up / AFP / Jury / Out (based on their `finish_place` + AFP match)
- Name below (uppercase, Oswald)
- Link wraps entire tile → other player's profile
- The current player's tile (if shown — by design spec yes) gets a "You" badge (top-left) and a yellow border

Query: junction rows WHERE `bbj_season = <most_recent_season>` ordered by `finish_place` ASC.

### 4.8 Sidebar (`<aside>`, sticky at `top: 16px`)

Stacked cards in the design's order (placeholders live where the real cards will eventually land, so when the voting system ships it's a markup swap, not a re-layout):

1. **Reserved placeholder: AFP Odds**
   - Inert card in the right visual spot with helpful copy, not "Coming soon"
   - Example: "AFP voting runs all season. Polling system in the works — custom, not Jokers'."
   - Markup matches `.card.odds-card` so swap-in is trivial when fan-voting system ships

2. **Reserved placeholder: Fan Affinity**
   - "Needs 10+ fan ratings to display. Ratings open once the voting system ships."
   - Markup matches `.card.fan` skeleton

3. **Reserved placeholder: Fan Ranking**
   - "Season ranking opens once ratings accumulate."
   - Markup matches `.card.ranks` skeleton

4. **Follow [Player]** — real content
   - Section header: "Follow {FirstName}"
   - 2-col grid of social links
   - Icons: X, Instagram, Facebook, TikTok (brand-specific inline SVG or Heroicons equivalent)
   - Only render entries with a non-empty URL
   - **No follower counts** — requires per-platform API integration, not worth it
   - If NONE of the socials are set, the entire Follow card is omitted (don't render an empty shell)

5. **Ad slot** — 300×600 via existing `template-parts/components/ad-placeholder.php` with slot name `player_profile_sidebar`

**HG Alerts card** from the design is omitted entirely (not rendered as a placeholder) — deferred to the mailing system revamp.

Sidebar `.stick` wrapper uses `position: sticky; top: 16px` on desktop; static on mobile.

---

## 5. Responsive

At `max-width: 1000px` breakpoint:
- `.grid` two-col → single column, sidebar stacks below
- Hero `.inner` collapses to single column, portrait shrinks to 160px
- Hero `h1` 80px → 52px
- `.biostrip` 5-col → 2-col with bottom borders instead of right
- `.statgrid` 6-col → 3-col
- `.cast-grid` 8-col → 4-col
- `.compare`, `.artrow` → 1-col (not in MVP but CSS stays as-is)
- `.stick` → `position: static`

---

## 6. Accessibility & SEO

- `h1` = player name (only one per page)
- `schema.org/Person` JSON-LD block with `name`, `birthDate`, `jobTitle`, `url`, `image` (portrait), `sameAs` array of populated social URLs
- Alt text on portrait: "{Name}, {Season abbreviation} houseguest"
- Alt text on castmate tiles: "{Castmate name}, {season abbreviation}"
- Disabled Compare action gets `aria-disabled="true"` and `title="Coming soon"`
- No auto-refreshing timestamps → no `data-nosnippet` handling required
- Breadcrumb should be wrapped in `nav[aria-label="Breadcrumb"]` with `ol`/`li` structure (even though visually it's a flat line) for screen readers + schema.org `BreadcrumbList` JSON-LD

---

## 7. Deferred sections (not in MVP)

Each entry: what it is · why deferred · unblocker.

- **Game Timeline** (week-by-week power map) — visually stunning. Requires a new per-week events table (e.g. `wp_bbj_v2_weekly_events`) capturing who won HoH/PoV/who was nominated by week. User had this system in earlier seasons. Historical seasons are hard to backfill without re-watching episodes. Suggest dedicated sprint. Markup pre-scaffolding: **not included** (too empty for MVP; add when the data system lands).
- **Week-by-week narrative cards** — editorial highlight cards per milestone week. Depends on same per-week data + a curated narrative field per event. Likely out of one-person curation scope for back seasons. Keep as stretch; real value is for the current season where user is writing recaps anyway.
- **Compare** — Sprint K. Full compare page design already exists at `bbj-home-page/project/` (not shipped). Visual slot in the hero actions + dedicated "Compare Keanu" section both stubbed for MVP.
- **Articles About [Player]** — post → player linkage. Proposed **Sprint B.5 (post-profile linkage)**: fuzzy name-scan of post title + content against `first_name`, `last_name`, `official_nickname`; paired with an admin override list for false positives/negatives. Avoids manual per-post tagging at bulk. Not this sprint.
- **Fan Voting System** — bundles AFP Odds + Fan Affinity + Fan Ranking. Season-long voting infrastructure. **Don't copy Jokers** (per user); build own. Brainstorm target: before next BB season starts. Three surfaces on this page + likely elsewhere (season hub, homepage widget). Reserved placeholder cards on this page in the meantime.
- **HG Alerts** — per-player subscription. Defer into the general mailing/notifications system revamp (Sprint E territory).
- **Career Stat deltas** ("+N vs avg") — needs cross-player averages per stat. Cheap to add once scaffolding for averages exists.
- **Follower counts on socials** — per-platform API auth + rate limits. Not worth the ops overhead.

---

## 8. File changes (implementation preview)

New files:
- `wp-content/themes/bbj-v2-theme/css/single-bigbrother-players.css` — full design port, scoped
- `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php` — helpers that fetch player + season + castmate data and return a well-shaped array for the template. Namespaced function prefix `bbj_v2_player_profile_*`

Modified:
- `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php` — full rewrite against the design
- `wp-content/themes/bbj-v2-theme/functions.php` — enqueue the new CSS and Google Fonts URL on `is_singular('bigbrother-players')` only; include `inc/player-profile-data.php`

No new DB tables. No new Meta Box fields. Pure template + data-aggregation work.

---

## 9. Open / small things to confirm during implementation

- Alt-text format for portrait image — ok to use "{Name}, {Season abbreviation} houseguest"?
- Schema.org `Person` — include `knowsAbout` or `affiliation` properties, or keep it minimal?
- Castmates grid: should **current player** appear in their own grid (with "You" badge as the design shows), or be removed from the list since we're already on their profile? Design shows self-inclusion; default: keep it.
- Breadcrumb "Houseguests" link — leave as `/houseguests` (will 404 until Sprint C) or point to the old `/bigbrother-players` archive?

These are small enough to resolve during implementation without re-specing.
