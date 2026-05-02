# Weekly Tracker — Schema + Admin UX Design

**Date:** 2026-04-25
**Status:** Spec ready for implementation plan
**Owner:** Steve (BBJ)

## Problem

The current `wp_bbj_weeks_players` table captures partial per-player-per-week data for BB23-25 only — booleans for HoH / Veto / Nom / Saved / Evicted with no link from the saved player back to the veto holder, no comp type detail, and no place for week narrative. The "Week by Week" block on the player-profile design mockup, the season-profile evictions/comps tables, and the future weighted ranking formula all need richer per-week data.

## Goals

1. Replace the lossy `saved` boolean with a `saved_by_player_id` reference so we can answer "who did Cody save?" and "how many times was Amy saved by a non-self?"
2. Track comp wins by **type** (HoH / POV / Misc / future seasonal regulars) so a future weighted ranking formula can score by comp difficulty + opponent count.
3. Support **multiple comp wins per player per week** without baking single-win assumptions (twist-tolerant).
4. Provide an **admin form** to enter and edit a week's data incrementally throughout the week — not a step-by-step wizard.
5. Allow comp type categories to be added by the user without code changes.
6. Preserve BB1-21 historical totals during incremental manual backfill.

## Non-goals (explicit)

- Backfilling BB1-21 weekly data — owner does this manually or hires it out over time.
- The future weighted ranking formula itself — scoped separately when ready.
- A "freeze spoiler bar → snapshot week" shortcut button — follow-up.
- Public REST endpoints for the new junction tables — add when consumers need them.
- Touching the spoiler-bar `current_*` flags on `wp_bbj_v2_player_season` — those are this-week-only state, separate concern.

## Schema delta

### Modify `wp_bbj_weeks_players` (existing)

**Drop** (junction-derivable):
- `hoh tinyint(1)` — derive from `wp_bbj_week_comps WHERE comp_type=HOH`
- `pov tinyint(1)` — derive from `wp_bbj_week_comps WHERE comp_type=POV`
- `misc_comp tinyint(1)` — derive from `wp_bbj_week_comps WHERE comp_type=MISC`
- `saved tinyint(1)` — replaced by `saved_by_player_id`

**Add:**
- `saved_by_player_id BIGINT NULL` — NULL = not saved this week; player ID = veto holder; self ID = twist / power / self-save (intentionally allowed since twists rarely behave like a normal veto)

**Keep:**
- `id`, `player_id`, `season_id`, `week_id`
- `nom tinyint(1)` — was on the block at any point this week
- `evicted tinyint(1)` — multi-eviction-per-week supported (UI uses checkboxes, not radios)
- `veto_played tinyint(1)` — whether the veto was used at all (cached convenience flag; could be derived from `EXISTS saved_by_player_id` in any row)
- `voted_for BIGINT` — who this player voted to evict (0 = no vote / not eligible)
- `vote_to_win BIGINT` — finale jury vote
- `active tinyint(1)` — still in the house going into this week

### Modify `wp_bbj_weeks` (existing)

**Add:**
- `summary TEXT NULL` — owner-written editorial recap of the week (drives the "Week by Week" prose blocks on player + season profiles)

### NEW `wp_bbj_comp_types`

Admin-managed list of comp categories.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK auto_increment | |
| `slug` | VARCHAR(40) UNIQUE | URL-safe identifier (`hoh`, `pov`, `bb-comics`) |
| `name` | VARCHAR(80) | Display label |
| `sort_order` | INT DEFAULT 0 | Display order in admin pickers |
| `is_archived` | TINYINT(1) DEFAULT 0 | Soft delete — archived types disappear from new-comp pickers but historical rows still resolve |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Seeded with** (slug, name): `hoh / HOH`, `pov / Power of Veto`, `misc / Misc Comp`. Owner adds seasonal regulars as they appear (e.g., "BB Comics", "Battle Back").

### NEW `wp_bbj_week_comps` (junction)

One row per comp won. Multiple rows per player per week supported.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK auto_increment | |
| `week_id` | BIGINT | FK to `wp_bbj_weeks.id` |
| `player_id` | BIGINT | FK to `wp_posts.ID` of the player post |
| `comp_type_id` | BIGINT | FK to `wp_bbj_comp_types.id` |
| `opponents_count` | INT NULL | Optional override; when NULL, derive at render time from `active=1` count for that week minus 1. Useful when the comp didn't include all active players (e.g., Veto field of 5) |
| `notes` | VARCHAR(140) NULL | Free text flavor — "Endurance, 6 hours" |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** `(week_id, player_id)`, `(player_id, comp_type_id)`.

### Modify `wp_bbj_v2_player_season` (existing)

**Mark deprecated** (kept populated for legacy fallback, never written by new code):
- `bbj_total_hoh`
- `bbj_total_pov`
- `bbj_total_misc`

**Reader logic:** for any season where `wp_bbj_week_comps` rows exist for the player, derive counts from junction. Otherwise fall back to the deprecated columns. Future migration drops the columns once backfill catches up.

`bbj_total_nom`, `bbj_votes_received`, `bbj_evicted_date`, etc. — keep, also derivable but lower-priority refactor target.

## Admin UX

### Where it lives

**`/admin?tab=seasons&edit=<id>#weeks`** — new tab on the existing season editor, alongside Spoiler Bar / Info / Photos.

**`/admin?tab=comp-types`** — new top-level admin pane for managing `wp_bbj_comp_types` (small CRUD: list, add, rename, archive).

### Weeks tab layout

```
┌─────────────────────────────────────────────────────────────────┐
│ Weeks                                            [+ Add Week]   │
├─────────────────────────────────────────────────────────────────┤
│ [ Week 1 ] [ Week 2 ] [ Week 3 ] [ Week 4 ]  ← week picker
├─────────────────────────────────────────────────────────────────┤
│ Week 5 · Jul 28 – Aug 3, 2025                                   │
│ start_date: [date input]  end_date: [date input]                │
│ summary: [textarea — your editorial week recap]                 │
├─────────────────────────────────────────────────────────────────┤
│ Players still in the game (12)                       [Save]    │
│                                                                 │
│  Player    Comps won         Nom?  Saved by    Evict?  Voted    │
│  ─────────────────────────────────────────────────────────────  │
│  Rachel    [HOH ✕] [+]       ☐     —           ☐       [Amy ▾]
│  Amy       [POV ✕] [+]       ☑     [self ▾]    ☐       [Cliff▾]
│  Cliffton  [+]               ☑     —           ☑       —
│  Mickey    [+]               ☐     —           ☐       [Cliff▾]
│  …                                                              │
└─────────────────────────────────────────────────────────────────┘
```

### Behaviors

- **"Players still in the game"** = rows from `wp_bbj_weeks_players` for this week with `active=1`. When "Add Week" creates a new week, rows are auto-seeded by copying `active=1, evicted=0` players from the previous week (or the full season cast for week 1).
- **Comps won column** = chips, each chip is a `wp_bbj_week_comps` junction row. `[+]` opens a comp-type picker (filtered to non-archived types). `✕` removes.
- **Saved by column** = dropdown of all season players (chosen by name) **plus a "Self / Twist" option at the top** that, when selected, stores the row's own `player_id` in `saved_by_player_id`. The schema doesn't distinguish "self-save" from "saved-by-twist" — they both resolve to `saved_by_player_id == player_id`. Display layer handles the twist case via the absence of a corresponding POV winner that week.
- **Evict?** column = **checkboxes** (multiple players evicted in same week supported — twist evictions, double-evictions where owner chose not to split into a new week record).
- **Voted column** (per row) = dropdown of currently-on-block players for vote target (filtered to rows where `nom=1` AND `saved_by_player_id IS NULL` — i.e., still on the block at eviction). Rows where the dropdown is **disabled** (i.e., this player doesn't vote): the HoH, and any player who is themselves a current nominee (can't vote for self/co-nominees in standard rules). Owner can override the disabled state if a twist breaks normal voting (e.g., HoH tie-breaker).
- **Save** = write-through. Saves whatever's in the form whenever clicked. Idempotent upserts on `wp_bbj_weeks_players` and `wp_bbj_week_comps`. Owner can pop in mid-week, edit a few cells, save, leave — perfectly normal flow.

### Comp-types pane

Tiny CRUD table. Columns: Name, Slug (auto-generated, editable), Sort order, Archived. Standard add/edit/save. No paged list (expected to stay small — under 30 rows even with seasonal additions).

## Display layer changes

### Player profile (`single-bigbrother-players.php`)

**Career stats tile** — switch from raw `bbj_total_*` reads to new helper:

```php
function bbj_v2_player_career_totals(int $player_post_id): array {
    // For each season the player appears in:
    //   if wp_bbj_week_comps has rows for this (player, season) → use COUNT
    //   else → fall back to wp_bbj_v2_player_season.bbj_total_*
    // Sum across seasons. Cache 1h. Bust on wp_bbj_week_comps save.
}
```

Returns: `['hoh' => N, 'pov' => N, 'misc' => N, 'noms' => N, ...]`.

**Week by Week block** — new helper `bbj_v2_player_weeks($player_post_id, $season_post_id)` joins `wp_bbj_weeks` + `wp_bbj_weeks_players` + `wp_bbj_week_comps` and renders mockup-style entries: week number, summary, comp badges, "Saved Cliffton with veto" snippets resolved from `saved_by_player_id` (when current row is the saver) or "Saved by Cody" (when current row is the saved).

### Season profile (`single-bigbrother-seasons.php`)

`bbj_v2_season_profile_data()` extended to pull comp junction. Existing evictions table unchanged. Add a "Week-by-week recap" rail driven by `wp_bbj_weeks.summary`.

### Player archive cards (Sprint C, just shipped)

`bbj_v2_archive_all_players()` currently `SUM(j.bbj_total_hoh)` etc. across `wp_bbj_v2_player_season` rows. Switch to use the new career-totals helper (cached). One join per player → fine for ~365 cards on a 1h cache.

### Spoiler bar

**No change.** Uses `current_*` flags on `wp_bbj_v2_player_season`, separate from this work.

## Migration

**Bootstrap SQL** at `docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql`:

1. `CREATE TABLE wp_bbj_comp_types` + seed HOH/POV/MISC
2. `CREATE TABLE wp_bbj_week_comps` with indexes
3. `ALTER TABLE wp_bbj_weeks ADD COLUMN summary TEXT NULL`
4. `ALTER TABLE wp_bbj_weeks_players ADD COLUMN saved_by_player_id BIGINT NULL`
5. Backfill junction rows from existing booleans:
   - For every `wp_bbj_weeks_players` row in BB23-25 where `hoh=1` → insert `(week_id, player_id, HOH.id)` if not exists
   - Same for `pov=1 → POV`, `misc_comp=1 → MISC`
6. Backfill `saved_by_player_id` where derivable (when `saved=1` and exactly one row in the same week has `pov=1` AND `veto_played=1` → assume that POV holder; otherwise leave NULL for manual entry)
7. Print BEFORE/AFTER snapshot counts.

**Idempotent** — re-runnable. Inserts only when matching junction row doesn't exist.

**Deferred to a follow-up SQL** (`drop-deprecated-week-booleans.sql`) once junction reads are verified working in production:
- Drop `hoh`, `pov`, `misc_comp`, `saved` columns from `wp_bbj_weeks_players`
- Drop `bbj_total_hoh`, `bbj_total_pov`, `bbj_total_misc` from `wp_bbj_v2_player_season`

Owner runs each SQL on local → staging → prod manually (per `reference_db_repairs.md` convention).

## Cache strategy

Builds on existing `bbj_v2` cache group + Cloudways Redis when enabled.

**New cache keys:**
- `bbj_v2_career_totals_<player_post_id>` (1h TTL)
- `bbj_v2_player_weeks_<player_post_id>_<season_post_id>` (1h TTL)
- `bbj_v2_archive_all_players` (existing — bust on comp-junction save too)
- `bbj_v2_season_profile_data_<season_post_id>` (existing — bust on comp-junction save too)

**Save hooks** in `inc/template-functions.php`:
- `save_post_bigbrother-players` — already exists
- New: hook into `wp_bbj_week_comps` insert/update/delete via custom action `bbj_v2_week_comp_saved` — bust the keys above for the affected player + season
- New: hook into `wp_bbj_weeks_players` insert/update via custom action `bbj_v2_week_player_saved` — same

## Open questions / follow-ups (NOT MVP)

1. **Real-time freeze button** that snapshots the spoiler bar into the current week's rows. Useful UX shortcut after a finale-night eviction. Defer until weekly entry workflow proves stable.
2. **Weighted ranking formula** — comp difficulty × opponent count, scaled by season position. Separate spec when ready.
3. **Public REST endpoints** for the junction so bbj-app or future consumers can read.
4. **BB1-21 backfill** — owner-driven content work. The fallback reader handles partial coverage gracefully so this can land season-by-season.
5. **Save-hook for season-level totals** — once all seasons backfilled, drop the deprecated columns and remove the fallback path.
6. **Veto-played derivation** — `veto_played` could be entirely derived from `EXISTS saved_by_player_id` for any row in the week. Keep for now (cached convenience), revisit during column cleanup.

## Architecture decisions

- **Junction is source of truth, columns are deprecated cache.** Single source of truth long-term, graceful degradation during incremental backfill.
- **Comp types as an admin-managed table, not a hardcoded enum or WP taxonomy.** Owner adds seasonal regulars without code changes; no WP-taxonomy overhead for what's effectively a small lookup.
- **Twist tolerance baked in.** `saved_by_player_id` allows self-saves; `wp_bbj_week_comps` allows multiple wins per player per week; evictions are checkbox-based (multiple per week allowed). No formula assumes "1 veto = 1 saved" or "1 evicted per week".
- **Grid edit, not wizard.** Owner can pop in mid-week and edit incrementally — matches how a BB week unfolds in real time.
- **Object cache, not denormalized counter columns.** Redis (when enabled on Cloudways) handles the read-pattern via `wp_cache_*` with the existing `bbj_v2` group, matching the season-profile and spoiler-bar pattern already shipped.
