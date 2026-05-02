# Spoiler Bar Editor — Design

**Date:** 2026-04-22
**Sprint:** Sprint A (part 1 — Spoiler Bar tab on season edit page)
**Status:** Design approved, ready for implementation plan

---

## Problem

The pre-sprint shipped `/admin?tab=seasons&edit=<id>` with a stubbed Spoiler Bar tab. That tab is the highest-value target on the site — editing player statuses on the live homepage spoiler bar has been buried inside `/wp-admin/admin.php?page=bbj-v2-edit-season` (4-minute hunt) in a dense 20-column HTML table. This spec fills the Spoiler Bar tab with a card-per-player UI matching the Next.js reference at `bbj-app\src\app\bigbrother-seasons\[slug]\edit\components\`.

## Goals

- Replace the stub on the `#spoiler` tab with a functional editor
- Flip the default tab on the edit page from `#info` to `#spoiler` (Spoiler Bar is now the primary reason to open the edit page)
- Card-per-player layout, Active / Eliminated grouping, dense but scannable
- Add a `bbj_finish_place` column to `wp_bbj_v2_player_season` — a known gap that breaks sort on double-eviction weeks
- Server-rendered uncached preview at top of tab so the user sees just-saved state without waiting for the 300s cache
- Purge-cache button for convenience when iterating
- Reuse existing `bbj_v2_update_season()` handler after minor extension

## Non-goals

- Roster add/remove (belongs on Season Info tab, separate sprint)
- Dirty tracking / JS state management
- Client-side live preview (server-rendered uncached render instead)
- Finish-place backfill for historical seasons
- Nonce rename (`add_player_*` → `bbj_v2_update_season_*`) — cosmetic
- Bulk actions / drag-to-reorder / uniqueness validation on finish_place
- Mobile layout (desktop-only, matches existing admin shell)

---

## Architecture

**Split:** templates in the theme, write-handlers in the plugin, one small schema migration. No new REST endpoints. No React.

### Schema migration

Add one nullable column to `wp_bbj_v2_player_season` via `dbDelta()` in the plugin's existing `create-db-tables.php`:

```sql
bbj_finish_place TINYINT(3) UNSIGNED DEFAULT NULL
```

- Existing rows get NULL — no backfill
- `dbDelta()` is idempotent — safe to run on each plugin activation
- Sort tiebreaker: NULL sorts last, explicit values take precedence

### New files

```
wp-content/themes/bbj-v2-theme/template-parts/admin/partials/
  seasons-edit-spoiler.php         ← top-level Spoiler Bar tab body
  spoiler-preview-strip.php        ← uncached spoiler-bar render at top of tab
  spoiler-player-card.php          ← one player's dense card (all 19 fields + finish_place)

wp-content/plugins/bbj-v2/includes/Actions/form-submits/
  purge-season-cache.php           ← new bbj_v2_purge_season_cache() handler
```

### Modified files

```
wp-content/themes/bbj-v2-theme/template-parts/admin/partials/seasons-edit-tabs.php
  — flip DEFAULT_TAB from 'info' → 'spoiler' (now that #spoiler is live)
  — replace the #spoiler stub get_template_part with seasons-edit-spoiler

wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php
  — extend bbj_v2_update_season(): read $_POST['finish_place'][$player_id],
    coerce empty / 0 to NULL, write to bbj_finish_place column
  — everything else stays as-is

wp-content/plugins/bbj-v2/includes/Actions/action-list.php
  — register admin_post_bbj_v2_purge_season_cache + its loader

wp-content/plugins/bbj-v2/includes/Helpers/create-db-tables.php
  — add bbj_finish_place column to the CREATE TABLE

wp-content/plugins/bbj-v2/includes/Public/shortcodes/spoiler-bar.php
  — new optional second param: bbj_render_spoiler_bar($season_id, $skip_cache = false)
  — add bbj_finish_place ASC as a tiebreaker inside each bbj_spoiler_weight()
    bucket, before the evicted_date fallback
```

### Reused as-is

- `bbj_v2_update_season()` handler — just extended, not rewritten
- `bbj_v2_get_season_players($season_id, $size)` — returns roster rows with image URLs
- Nonce `add_player_nonce` / action `add_player_action` (semantically weird, intentionally not renamed to minimize churn)
- `bbj_spoiler_bar_bust_cache()` — fires unconditionally inside the existing handler on save
- `bbj_v2_require_admin()` — page-admin.php line 1 safeguard

---

## Data flow

### Tab load (`/admin?tab=seasons&edit=<id>#spoiler`)

1. `page-admin.php` → `pane-seasons-edit.php` → `seasons-edit-tabs.php` → `seasons-edit-spoiler.php`
2. Spoiler pane renders:
   - Calls `bbj_v2_get_season_players($season_id, 'bbj_v2_profile_image')` → roster rows
   - Splits into two groups:
     - **Active**: `current_evicted == 0`; sorted by `full_name ASC`
     - **Eliminated**: `current_evicted == 1`; sorted by `bbj_finish_place ASC` (NULL last), then `bbj_evicted_date DESC` (fallback)
   - Renders the preview strip (uncached call to `bbj_render_spoiler_bar($season_id, true)`)
   - Renders Active cards, then Eliminated cards, via `spoiler-player-card.php`
   - Save + Purge Cache action bar at top AND bottom
3. Query-arg notices:
   - `?updated=1` → green "Spoiler bar saved" (auto-dismiss, ~3s)
   - `?purged=1` → blue "Cache purged"

### Save

1. Form POSTs to `admin-post.php?action=bbj_v2_update_season` with nonce `add_player_nonce`
2. Existing `bbj_v2_update_season()` runs:
   - Reads all 19 existing POST arrays keyed by player_id
   - **New:** reads `$_POST['finish_place'][$player_id]` via `absint()`; if input is blank or `0`, stores `NULL`
   - Updates every roster row via `$wpdb->update(link_table, $data, $where)`
   - Extended `$data` includes `'bbj_finish_place' => $finish_place_or_null`
   - Calls `bbj_spoiler_bar_bust_cache($season_id)` — unconditional
3. Redirects via `wp_get_referer()` + `?updated=1` → back to the same edit URL, `#spoiler` hash preserved

### Purge Cache

1. Separate tiny form POSTs to `admin-post.php?action=bbj_v2_purge_season_cache`
2. New handler:
   - Verify nonce `bbj_v2_purge_season_cache_nonce` + `current_user_can('manage_options')`
   - `absint()` on `$_POST['season_id']`
   - Call `bbj_spoiler_bar_bust_cache($season_id)`
   - Redirect with `?purged=1`
3. No DB writes — cache wipe only

### Public spoiler-bar sort change

In `bbj_render_spoiler_bar()`:
- Primary sort: existing `bbj_spoiler_weight()` bucket (HoH=1, PoV=2, Active=3, Nom=4, Jury=5, Evicted=6)
- **New secondary:** `bbj_finish_place ASC` (NULLs last)
- Tertiary fallback (unchanged): `bbj_evicted_date DESC`

Effect: double-eviction weeks where two players have the same evicted_date now sort by finish_place when set. Historical seasons with all-NULL finish_place fall back to date sort — unchanged behavior.

### Finish-place backfill

None. Fill by hand as needed. BB27 gets entries when evictions happen; older seasons stay NULL.

---

## Components & markup

Flat-editorial aesthetic. Dense but scannable. No JS state — pure PHP-rendered HTML inside one big `<form>`.

### Tab layout (`seasons-edit-spoiler.php`)

```
┌ Spoiler Bar — Big Brother 27 ──────────────────── [Save] [Purge Cache] ┐
│                                                                        │
│  LIVE PREVIEW (uncached)                                               │
│  ┌──────────────────────────────────────────────────────────┐         │
│  │ [WINNER|Ashley] [2ND|Vince] [Jury|Morgan] [Jury|Ava] ...│         │
│  └──────────────────────────────────────────────────────────┘         │
│                                                                        │
│  Active (2)                                                            │
│  [<player-card>]                                                       │
│  [<player-card>]                                                       │
│                                                                        │
│  Eliminated (14)                                                       │
│  [<player-card>]                                                       │
│  [<player-card>]                                                       │
│  ...                                                                   │
│                              [Save] [Purge Cache]                      │
└────────────────────────────────────────────────────────────────────────┘
```

Structure:
- Save + Purge Cache are TWO separate `<form>` elements (so different nonces and actions don't collide)
- Save form wraps all the player cards
- Purge form is tiny — season_id + nonce + action hidden inputs + button
- Action bars render once at top and once at bottom (both are the same two buttons via shared partial)

### Player card (`spoiler-player-card.php`)

Dense 2-row-ish layout. Left border colored by primary status.

```
┌─ Ashley Hollis (Winner) ────────────────────────────────────────────┐
│  [avatar]  Ashley Hollis  [Winner badge]  Fin# [1  ]                │
│            HoH[1] PoV[1] Nom[0] Veto[0] Misc[0] Saved[0] H/N[4] Votes[0]
│  Elim? (N) ( Y)  [x] Jury  Evicted Date: [2025-09-28]  Misc: [_____]  │
│  Status: [HoH] [PoV] [Nom] [Safe] [HN] [Misc]                       │
└──────────────────────────────────────────────────────────────────────┘
```

**POST fields rendered per card:**

| Visual | Input type | POST name |
|---|---|---|
| Finish place | `number` (min=1) | `finish_place[<player_id>]` |
| HoH count | `number` (min=0) | `hoh_count[<player_id>]` |
| PoV count | `number` (min=0) | `veto_count[<player_id>]` |
| Nom count | `number` (min=0) | `nom_count[<player_id>]` |
| Veto-played count | `number` (min=0) | `veto_played[<player_id>]` |
| Misc count | `number` (min=0) | `misc_count[<player_id>]` |
| Saved count | `number` (min=0) | `saved_count[<player_id>]` |
| H/N count | `number` (min=0) | `havenot_count[<player_id>]` |
| Votes received | `number` (min=0) | `votes_received[<player_id>]` |
| Elim radio N/Y | `radio` | `current_evicted[<player_id>]` |
| Jury checkbox | `checkbox` | `current_jury[<player_id>]` |
| Evicted date | `date` | `evicted_date[<player_id>]` |
| Misc notes text | `text` | `misc_notes[<player_id>]` |
| HoH toggle | `checkbox` | `current_hoh[<player_id>]` |
| PoV toggle | `checkbox` | `current_pov[<player_id>]` |
| Nom toggle | `checkbox` | `current_nom[<player_id>]` |
| Safe toggle | `checkbox` | `current_safe[<player_id>]` |
| HN toggle | `checkbox` | `current_havenot[<player_id>]` |
| Misc toggle | `checkbox` | `current_misc[<player_id>]` |

Status-toggle chips: `<label>` wraps a visually-hidden `<input type="checkbox">` + the visible pill text. Styled with Tailwind's `has-[:checked]:*` variant so clicking the pill toggles state — pure CSS, no JS.

### Primary-status left-border color

Computed per card in priority order (matches `bbj_status_prefix()` / `SpoilerBarPreview.jsx`):

```
Winner (row->id == season.season_winner)  → yellow  (secondary-500)
Runner-up (row->id == season.runner_up)   → sky blue
AFP (row->id == season.afp)                → pink
current_hoh                                 → emerald
current_pov                                 → purple
current_nom                                 → red
current_safe                                → green
current_havenot                             → slate
current_jury                                → indigo
current_evicted (no other status)           → grey
(none)                                      → stone
```

Note: Winner / runner_up / AFP are stored on `wp_bbj_seasons` (passed via `$args['season']`), not the link table.

### Preview strip (`spoiler-preview-strip.php`)

Thin wrapper around `bbj_render_spoiler_bar($season_id, $skip_cache = true)`. The second param is new — defaults to `false` so the public render path is unchanged. When true, the function skips the object-cache lookup and rebuilds the HTML from live DB values.

### Tab nav update (`seasons-edit-tabs.php`)

- Flip `DEFAULT_TAB` from `'info'` to `'spoiler'`
- Swap the `#spoiler` panel's `get_template_part` from `seasons-edit-stub` to `seasons-edit-spoiler` (pass `season` and `season_id` args)

### Reused theme classes

- `bg-white`, `border border-stone-200`, flat-editorial wrapper (same as other admin panes)
- `primary-500`, `primary-600`, `secondary-500` — all valid Tailwind classes per the theme config
- `font-mainHead`, `font-osw`, `text-3xl` for headings
- Status-toggle chips reuse the `text-xs font-semibold border` treatment from `seasons-list-row.php` status badges

---

## Error handling & edge cases

### Guards & validation

- `bbj_v2_require_admin()` already guards `page-admin.php` line 1 — covers tab render
- Save handler: existing `add_player_nonce` / `add_player_action` check (unchanged)
- Purge handler: `bbj_v2_purge_season_cache_nonce` / `bbj_v2_purge_season_cache_action` + `current_user_can('manage_options')`
- `finish_place` input: `absint()` on read; blank or `0` → `NULL` (not `0`), so the sort doesn't treat unset as 1st place
- `bbj_evicted_date` input: validated as `Y-m-d` before write (already present in existing handler); invalid date → NULL
- All numeric count fields: `absint()` coerces negatives and non-numerics to `0`
- Checkbox absence = `0` (existing handler pattern)

### Edge cases

- **Player marked Active but evicted_date is set** — ignored on public render (existing behavior sorts by `current_evicted` first, date is secondary). No data cleanup.
- **Player marked Evicted but no evicted_date** — renders as evicted, no date tooltip. Acceptable.
- **Two players with same `bbj_finish_place` value** — falls back to evicted_date DESC. If both are NULL too, original insert order. Not worth custom validation — the point of finish_place is disambiguating same-day evictions.
- **Finish place of `0`** — stored as NULL. UI enforces `min="1"`.
- **Finish place > roster size** — no validation. User error, not worth a rule.
- **Concurrent edits by two admins** — last write wins. Not worth solving at 2-admin scale.
- **Season with zero roster members** — empty Active + Eliminated sections with copy "No players yet. Add players from the Season Info tab." Save form still renders but has no input rows.
- **User clicks Purge Cache twice quickly** — idempotent, fine.

### Schema migration safety

- `dbDelta()` on an existing table with a new column is safe — existing rows get NULL
- Idempotent — if column exists, `dbDelta()` skips
- No data backfill, no downtime
- Triggered by plugin deactivate → reactivate (standard `register_activation_hook`-style `dbDelta()` invocation already present in `create-db-tables.php`). Implementation plan will include a one-line reactivation step.

---

## Testing plan

Manual smoke-test on staging. No automated tests (matches repo convention).

1. **Schema migration landed.** In phpMyAdmin: `wp_bbj_v2_player_season.bbj_finish_place` exists, TINYINT(3) UNSIGNED, default NULL. Existing rows NULL.
2. **Default tab flipped.** `/admin/?tab=seasons&edit=<BB26_id>` lands on `#spoiler` (not `#info`). Preview strip renders with BB26's state. Player cards render pre-filled.
3. **Single-field edit.** Change Morgan's finish_place from blank to `3`. Save. Notice "Spoiler bar saved." appears, `?updated=1`, Morgan's card shows `3`. phpMyAdmin: `bbj_finish_place=3`.
4. **Multi-field edit.** Toggle Vince's `current_hoh` ON, bump HoH count. Save. After reload: checkbox persisted, count incremented. phpMyAdmin matches.
5. **Eliminate a player.** Ashley: flip Elim=Y, set evicted_date, set finish_place=1, check Jury. Save. After reload: Ashley moves to Eliminated group, sorted first.
6. **Finish-place sort.** Two eliminated players with same evicted_date but finish_place 4 and 5. Public spoiler bar on homepage renders them in `4 → 5` order, not date-tiebreak.
7. **Purge Cache button.** Click. Notice "Cache purged." No data change. Homepage spoiler bar reflects current DB immediately.
8. **Preview freshness.** Edit a field, save, preview strip on the tab updates after redirect without the 300s wait (bypasses cache via `skip_cache=true`).
9. **Non-admin user.** Log out, hit the edit URL → redirect to wp-login.php.
10. **Public spoiler bar regression.** Visit homepage. Confirms order still correct for seasons with all-NULL finish_place (evicted_date sort still works).

---

## References

- Next.js reference components (visual spec): `bbj-app\src\app\bigbrother-seasons\[slug]\edit\components\RosterStatusSection.jsx`, `PlayerStatusCard.jsx`, `SpoilerBarPreview.jsx`, `StatusToggleGroup.jsx`, `EliminationFields.jsx`
- Old PHP editor (source for handler reuse): `wp-content/plugins/bbj-v2/includes/Public/bbj-v2-edit-season.php`
- Existing save handler: `wp-content/plugins/bbj-v2/includes/Actions/form-submits/update-season.php` (function `bbj_v2_update_season`)
- Public spoiler bar: `wp-content/plugins/bbj-v2/includes/Public/shortcodes/spoiler-bar.php`
- Schema: `wp-content/plugins/bbj-v2/includes/Helpers/create-db-tables.php`
- Pre-sprint spec that built the edit shell: `docs/superpowers/specs/2026-04-21-admin-seasons-pane-design.md`
- Roadmap: `.claude/project/roadmap.md` (Sprint A)

---

## What ships next (Sprint A continued, then Sprint B)

Once this is in:
- **Sprint A part 2:** `/admin?tab=settings` pane — `bbj_v2_current_season` dropdown + `bbj_v2_season_active` toggle. Small pane.
- **Sprint A part 3:** Season Info tab — fill the Images / Winners / Roster stubs with real fields (including "Add Player to Season" in the Roster section — the deferred piece from this sprint).
- **Sprint B:** Player + Season profile templates (`single-bigbrother-players.php`, `single-bigbrother-seasons.php`) — flat-editorial ports.
