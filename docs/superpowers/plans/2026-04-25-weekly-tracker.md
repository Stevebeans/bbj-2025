# BBJ Weekly Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the lossy boolean-per-week tracking in `wp_bbj_weeks_players` with a junction-table model that records who saved whom (twist-tolerant), tracks comps by admin-managed type, and powers the Week-by-Week block on player profiles + the week-recap rail on season profiles. Ship a usable mid-week admin editor first; display layer follows.

**Architecture:** Two new MySQL tables (`wp_bbj_comp_types` + `wp_bbj_week_comps`) plus column additions on existing `wp_bbj_weeks_players` and `wp_bbj_weeks`. Junction table is the source of truth; deprecated boolean / total columns remain populated for legacy fallback during incremental BB1-21 backfill. Object-cache (`wp_cache_*` with `bbj_v2` group, Redis-friendly) sits between junction and display layer. Admin lives as new tabs on the existing `/admin?tab=seasons&edit=<id>` editor and a new top-level `?tab=comp-types` pane.

**Tech Stack:** WordPress 6.x · PHP 8.x · MySQL/MariaDB · Tailwind CSS (already used by admin shell) · `bbj-v2-theme` (active theme) · `bbj-v2-plugin` (CPT + table installers, namespaced PSR-4) · `bbj-v2` plugin (legacy helpers).

**Spec source:** [`docs/superpowers/specs/2026-04-25-weekly-tracker-design.md`](../specs/2026-04-25-weekly-tracker-design.md)

**Verification approach:** No PHP unit-test runner is configured on this project. Each task uses one of:
- `php -l <file>` for syntax checks
- `mysql.exe -u root bbj_db -e "<sql>"` for DB schema/data assertions
- Browser load + visual / DB-state inspection for admin form behavior
- `wp_cache_get` reads in a one-off WP-CLI-style script for helper functions

The test-first discipline is preserved: each task defines what success looks like BEFORE implementation, then verifies after.

---

## File structure

### Create

| Path | Responsibility |
|---|---|
| `docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql` | One-shot migration: creates new tables, alters existing tables, seeds comp types, backfills junction from existing booleans for BB23-25. Idempotent. |
| `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php` | All query helpers: `bbj_v2_player_career_totals()`, `bbj_v2_player_weeks()`, `bbj_v2_season_weeks()`, `bbj_v2_active_players_for_week()`, etc. Cache layer included. |
| `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php` | Comp Types CRUD pane (`/admin?tab=comp-types`). |
| `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-weeks.php` | Weeks tab for the season editor (`/admin?tab=seasons&edit=<id>#weeks`). |
| `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/week-edit-form.php` | The per-week grid edit form (rendered by pane-seasons-weeks.php). |
| `wp-content/themes/bbj-v2-theme/template-parts/player/week-by-week.php` | "Week by Week" display block on the player profile. |

### Modify

| Path | Change |
|---|---|
| `wp-content/themes/bbj-v2-theme/functions.php` | `require_once` the new `inc/weekly-tracker-data.php`. |
| `wp-content/themes/bbj-v2-theme/page-admin.php` | Route `?tab=comp-types`. |
| `wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php` | Add Comp Types entry under System; add `tag` icon for it. |
| `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php` | Add Weeks tab to the existing tab layout. |
| `wp-content/themes/bbj-v2-theme/inc/template-functions.php` | New save hooks: `bbj_v2_week_comp_saved`, `bbj_v2_week_player_saved` action busters. |
| `wp-content/themes/bbj-v2-theme/inc/player-profile-data.php` | `bbj_v2_player_profile_career_totals()` switches to junction-aware path. |
| `wp-content/themes/bbj-v2-theme/inc/archives-data.php` | `bbj_v2_archive_all_players()` consumes new career-totals helper. |
| `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php` | Renders the new Week-by-Week block via `get_template_part`. |
| `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php` | Add `bbj_v2_season_profile_weeks()` helper for the recap rail. |
| `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php` | Render week-recap rail using `wp_bbj_weeks.summary`. |
| `.claude/project/roadmap.md` | Flag the new "Sprint R — Weekly Tracker" entry. |
| `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-roadmap.php` | Add the new admin tabs to the audit + a Sprint R card. |

---

## Phase 1 — Schema bootstrap (foundation)

### Task 1: Write the bootstrap SQL migration

**Files:**
- Create: `docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql`

- [ ] **Step 1: Write the BEFORE snapshot expectations**

Define what we expect to see before running:
- `wp_bbj_comp_types` table does not exist
- `wp_bbj_week_comps` table does not exist
- `wp_bbj_weeks_players` does NOT have `saved_by_player_id` column
- `wp_bbj_weeks` does NOT have `summary` column

- [ ] **Step 2: Verify the BEFORE state matches**

Run:
```
/c/xampp/mysql/bin/mysql.exe -u root bbj_db -e "
SHOW TABLES LIKE 'wp_bbj_comp_types';
SHOW TABLES LIKE 'wp_bbj_week_comps';
SHOW COLUMNS FROM wp_bbj_weeks_players LIKE 'saved_by_player_id';
SHOW COLUMNS FROM wp_bbj_weeks LIKE 'summary';
"
```
Expected: all four queries return zero rows.

- [ ] **Step 3: Write the migration SQL**

Create `docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql`:

```sql
-- =============================================================================
-- Bootstrap: weekly tracker junction tables + column additions
-- Date: 2026-04-25
-- Spec: docs/superpowers/specs/2026-04-25-weekly-tracker-design.md
--
-- What this does:
--   1. Creates wp_bbj_comp_types (admin-managed list of comp categories)
--   2. Creates wp_bbj_week_comps (junction: who-won-what-comp-which-week)
--   3. Adds saved_by_player_id to wp_bbj_weeks_players
--   4. Adds summary TEXT to wp_bbj_weeks
--   5. Seeds HOH / POV / MISC comp types
--   6. Backfills junction rows from existing hoh/pov/misc_comp booleans
--   7. Best-effort backfills saved_by_player_id where unambiguously derivable
--
-- Idempotent. Safe to re-run.
--
-- How to run:
--   - Local:    mysql -u root bbj_db < docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql
--   - Staging:  same with staging DB credentials
--   - Prod:     same with prod DB credentials
-- =============================================================================

-- ----- 1. wp_bbj_comp_types -----
CREATE TABLE IF NOT EXISTS wp_bbj_comp_types (
    id BIGINT NOT NULL AUTO_INCREMENT,
    slug VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- 2. wp_bbj_week_comps (junction) -----
CREATE TABLE IF NOT EXISTS wp_bbj_week_comps (
    id BIGINT NOT NULL AUTO_INCREMENT,
    week_id BIGINT NOT NULL,
    player_id BIGINT NOT NULL,
    comp_type_id BIGINT NOT NULL,
    opponents_count INT NULL,
    notes VARCHAR(140) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_week_player (week_id, player_id),
    KEY idx_player_type (player_id, comp_type_id),
    UNIQUE KEY uniq_week_player_type (week_id, player_id, comp_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- 3. saved_by_player_id on wp_bbj_weeks_players -----
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wp_bbj_weeks_players'
      AND COLUMN_NAME = 'saved_by_player_id'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE wp_bbj_weeks_players ADD COLUMN saved_by_player_id BIGINT NULL AFTER saved',
    'SELECT "saved_by_player_id already exists"'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----- 4. summary on wp_bbj_weeks -----
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wp_bbj_weeks'
      AND COLUMN_NAME = 'summary'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE wp_bbj_weeks ADD COLUMN summary TEXT NULL AFTER end_date',
    'SELECT "summary already exists"'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----- 5. Seed comp types -----
INSERT IGNORE INTO wp_bbj_comp_types (slug, name, sort_order)
VALUES
    ('hoh',  'HOH',           10),
    ('pov',  'Power of Veto', 20),
    ('misc', 'Misc Comp',     30);

-- ----- 6. Backfill junction rows from booleans -----
INSERT IGNORE INTO wp_bbj_week_comps (week_id, player_id, comp_type_id)
SELECT wp.week_id, wp.player_id, ct.id
  FROM wp_bbj_weeks_players wp
  INNER JOIN wp_bbj_comp_types ct ON ct.slug = 'hoh'
 WHERE wp.hoh = 1;

INSERT IGNORE INTO wp_bbj_week_comps (week_id, player_id, comp_type_id)
SELECT wp.week_id, wp.player_id, ct.id
  FROM wp_bbj_weeks_players wp
  INNER JOIN wp_bbj_comp_types ct ON ct.slug = 'pov'
 WHERE wp.pov = 1;

INSERT IGNORE INTO wp_bbj_week_comps (week_id, player_id, comp_type_id)
SELECT wp.week_id, wp.player_id, ct.id
  FROM wp_bbj_weeks_players wp
  INNER JOIN wp_bbj_comp_types ct ON ct.slug = 'misc'
 WHERE wp.misc_comp = 1;

-- ----- 7. Best-effort saved_by backfill -----
-- For each saved=1 row, if exactly ONE other row in the same week has pov=1 AND
-- veto_played=1, stamp that POV holder as the saver. Skip ambiguous cases.
UPDATE wp_bbj_weeks_players saved_row
INNER JOIN (
    SELECT week_id, MIN(player_id) AS pov_player_id, COUNT(*) AS pov_count
      FROM wp_bbj_weeks_players
     WHERE pov = 1 AND veto_played = 1
     GROUP BY week_id
    HAVING COUNT(*) = 1
) pov ON pov.week_id = saved_row.week_id
   SET saved_row.saved_by_player_id = pov.pov_player_id
 WHERE saved_row.saved = 1
   AND saved_row.saved_by_player_id IS NULL;

-- ----- AFTER snapshot -----
SELECT 'AFTER' AS stage,
       (SELECT COUNT(*) FROM wp_bbj_comp_types)        AS comp_types_count,
       (SELECT COUNT(*) FROM wp_bbj_week_comps)        AS week_comps_count,
       (SELECT COUNT(*) FROM wp_bbj_weeks_players
         WHERE saved_by_player_id IS NOT NULL)         AS saved_by_filled;
```

- [ ] **Step 4: Run the migration**

```
/c/xampp/mysql/bin/mysql.exe -u root bbj_db < "C:/xampp/htdocs/bbj/docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql"
```
Expected output: an `AFTER` row with `comp_types_count=3`, `week_comps_count > 0` (~250-450 rows from BB23-25 backfill), `saved_by_filled > 0`.

- [ ] **Step 5: Verify schema state**

```
/c/xampp/mysql/bin/mysql.exe -u root bbj_db -e "
DESCRIBE wp_bbj_comp_types;
DESCRIBE wp_bbj_week_comps;
SHOW COLUMNS FROM wp_bbj_weeks_players LIKE 'saved_by_player_id';
SHOW COLUMNS FROM wp_bbj_weeks LIKE 'summary';
SELECT * FROM wp_bbj_comp_types;
"
```
Expected: both new tables present with the columns specified, both new columns present, three seeded comp types.

- [ ] **Step 6: Verify backfill correctness**

```
/c/xampp/mysql/bin/mysql.exe -u root bbj_db -e "
-- Each season should have approximately the same number of HOH wins as
-- distinct weeks (since one HOH per week — twist weeks may have 2).
SELECT s.post_title, COUNT(DISTINCT w.id) AS weeks,
       SUM(CASE WHEN ct.slug='hoh'  THEN 1 ELSE 0 END) AS hoh_count,
       SUM(CASE WHEN ct.slug='pov'  THEN 1 ELSE 0 END) AS pov_count,
       SUM(CASE WHEN ct.slug='misc' THEN 1 ELSE 0 END) AS misc_count
  FROM wp_bbj_weeks w
  INNER JOIN wp_posts s ON s.ID = w.season_id
  LEFT JOIN wp_bbj_week_comps wc ON wc.week_id = w.id
  LEFT JOIN wp_bbj_comp_types ct ON ct.id = wc.comp_type_id
 GROUP BY s.ID
 ORDER BY s.post_title;
"
```
Expected: HOH count ≈ weeks for each populated season (BB23, BB24, BB25). POV count ≈ weeks. Misc may be 0.

- [ ] **Step 7: Re-run the migration to verify idempotence**

Run the migration command from Step 4 a second time. Expected: no errors, second `AFTER` row shows identical counts to the first.

- [ ] **Step 8: Commit**

```
git add docs/repairs/2026-04-25-week-comps-junction-bootstrap.sql
git commit -m "feat(weekly-tracker): bootstrap SQL for comp-types + week-comps junction"
```

---

### Task 2: Create the data helper file (skeleton)

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/functions.php`

- [ ] **Step 1: Define the function inventory**

Write a header comment listing all helper functions to be implemented. Empty function bodies for now — they get filled in subsequent tasks.

- [ ] **Step 2: Verify the file does not exist yet**

Run:
```
ls "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php" 2>&1
```
Expected: "No such file or directory".

- [ ] **Step 3: Create the file**

Write `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`:

```php
<?php
/**
 * Weekly tracker data helpers — junction-aware reads with object cache.
 *
 * Spec: docs/superpowers/specs/2026-04-25-weekly-tracker-design.md
 *
 * Public API:
 *   bbj_v2_comp_types_active()        — list of non-archived comp types
 *   bbj_v2_comp_types_all()           — every comp type incl. archived (admin pane)
 *   bbj_v2_player_career_totals(int)  — career stat counts (junction-first, fallback to legacy columns)
 *   bbj_v2_player_weeks(int, int)     — week-by-week timeline for a player in one season
 *   bbj_v2_season_weeks(int)          — every week of a season, with summary + cast
 *   bbj_v2_active_players_for_week(int, int) — players still in the house going INTO this week
 *   bbj_v2_save_week_comp(int, int, int, ?int, ?string) — upsert wp_bbj_week_comps row
 *   bbj_v2_delete_week_comp(int)      — remove a wp_bbj_week_comps row by id
 *   bbj_v2_save_week_player(array)    — upsert wp_bbj_weeks_players row
 *
 * All read helpers use wp_cache_* with the 'bbj_v2' group. Cache is busted
 * via do_action('bbj_v2_week_comp_saved', $week_id, $player_id) and
 * do_action('bbj_v2_week_player_saved', $week_id, $player_id) — wired in
 * inc/template-functions.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Function bodies implemented in subsequent tasks.
function bbj_v2_comp_types_active(): array { return []; }
function bbj_v2_comp_types_all(): array { return []; }
function bbj_v2_player_career_totals(int $player_post_id): array { return []; }
function bbj_v2_player_weeks(int $player_post_id, int $season_post_id): array { return []; }
function bbj_v2_season_weeks(int $season_post_id): array { return []; }
function bbj_v2_active_players_for_week(int $season_post_id, int $week_id): array { return []; }
function bbj_v2_save_week_comp(int $week_id, int $player_id, int $comp_type_id, ?int $opponents_count = null, ?string $notes = null): int { return 0; }
function bbj_v2_delete_week_comp(int $id): bool { return false; }
function bbj_v2_save_week_player(array $row): int { return 0; }
```

- [ ] **Step 4: Register the file in functions.php**

Edit `wp-content/themes/bbj-v2-theme/functions.php`. Add a require_once line after the existing `archives-data.php` line:

```php
require_once BBJ_V2_THEME_PATH . '/inc/feed-updates-hub-data.php';
require_once BBJ_V2_THEME_PATH . '/inc/weekly-tracker-data.php';  // ← add this
require_once BBJ_V2_THEME_PATH . '/inc/admin-shell.php';
```

- [ ] **Step 5: Verify PHP lint passes**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/functions.php"
```
Expected: "No syntax errors detected" for both.

- [ ] **Step 6: Verify file loads without fatal**

Browser-load `/admin/` while logged in as admin. Expected: page renders without WSOD or fatal error.

- [ ] **Step 7: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php wp-content/themes/bbj-v2-theme/functions.php
git commit -m "feat(weekly-tracker): scaffold inc/weekly-tracker-data.php"
```

---

### Task 3: Implement comp-types read helpers

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`

- [ ] **Step 1: Define expected behavior**

`bbj_v2_comp_types_active()` returns rows from `wp_bbj_comp_types` where `is_archived = 0`, ordered by `sort_order ASC, name ASC`. Cached for 1h under key `bbj_v2_comp_types_active`.

`bbj_v2_comp_types_all()` returns every row (active + archived) with the same ordering. Cached under `bbj_v2_comp_types_all`.

Both return arrays of associative arrays with keys: `id`, `slug`, `name`, `sort_order`, `is_archived`.

- [ ] **Step 2: Verify the cache miss baseline**

We expect first call to query the DB. After call, subsequent reads come from cache. No verification step here (PHP function — observed in step 4).

- [ ] **Step 3: Implement the functions**

Replace the stubs in `inc/weekly-tracker-data.php`:

```php
function bbj_v2_comp_types_active(): array
{
    $cache_key = 'bbj_v2_comp_types_active';
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, slug, name, sort_order, is_archived
           FROM {$wpdb->prefix}bbj_comp_types
          WHERE is_archived = 0
          ORDER BY sort_order ASC, name ASC",
        ARRAY_A
    ) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}

function bbj_v2_comp_types_all(): array
{
    $cache_key = 'bbj_v2_comp_types_all';
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, slug, name, sort_order, is_archived
           FROM {$wpdb->prefix}bbj_comp_types
          ORDER BY is_archived ASC, sort_order ASC, name ASC",
        ARRAY_A
    ) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}
```

- [ ] **Step 4: Verify via WP-CLI-style eval**

Save this as a temporary verification file `wp-content/themes/bbj-v2-theme/_verify-comp-types.php` (deleted after):

```php
<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';
$active = bbj_v2_comp_types_active();
$all = bbj_v2_comp_types_all();
echo "Active count: " . count($active) . "\n";
echo "All count: " . count($all) . "\n";
print_r($active);
```

Run via:
```
php "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-comp-types.php"
```

Expected: `Active count: 3`, `All count: 3`, the printed array contains hoh/pov/misc with `is_archived=0`.

- [ ] **Step 5: Delete the verify file**

```
rm "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-comp-types.php"
```

- [ ] **Step 6: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php"
```
Expected: "No syntax errors detected".

- [ ] **Step 7: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php
git commit -m "feat(weekly-tracker): bbj_v2_comp_types_active/all helpers"
```

---

## Phase 2 — Comp Types CRUD pane

### Task 4: Build the Comp Types pane (read-only listing)

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php`
- Modify: `wp-content/themes/bbj-v2-theme/page-admin.php`
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php`

- [ ] **Step 1: Define expected behavior**

Visiting `/admin/?tab=comp-types` as admin renders a table of all comp types with columns: Name, Slug, Sort, Archived, Edit. No edit actions wired yet — just read.

- [ ] **Step 2: Verify the route 404s currently**

Browser-load `/admin/?tab=comp-types` while logged in as admin. Expected: the admin shell renders but the section shows the generic stub pane (since no `elseif` branch exists for `comp-types` in `page-admin.php` yet).

- [ ] **Step 3: Create the pane file**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php`:

```php
<?php
/**
 * Admin pane: Comp Types CRUD.
 *
 * Lists all comp types from wp_bbj_comp_types. Add / edit / archive forms
 * post to admin-post.php handlers wired in Task 5.
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

$types = bbj_v2_comp_types_all();
?>

<div class="space-y-6">
  <header class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-stone-900 pb-4">
    <div>
      <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-stone-500">Game data</p>
      <h1 class="font-osw text-3xl text-primary-500">Comp Types</h1>
      <p class="mt-1 text-sm text-stone-600">Categories used when logging weekly comp wins. Add seasonal regulars as they appear.</p>
    </div>
  </header>

  <section class="rounded-md bg-white border border-stone-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-stone-50 border-b border-stone-200">
        <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600">
          <th class="text-left font-semibold px-5 py-2 w-[180px]">Name</th>
          <th class="text-left font-semibold px-5 py-2 w-[140px]">Slug</th>
          <th class="text-left font-semibold px-5 py-2 w-[80px]">Sort</th>
          <th class="text-left font-semibold px-5 py-2 w-[100px]">Status</th>
          <th class="text-right font-semibold px-5 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($types)): ?>
          <tr><td colspan="5" class="px-5 py-6 text-center text-stone-500 italic">No comp types yet.</td></tr>
        <?php else: foreach ($types as $t): ?>
          <tr class="border-t border-stone-100">
            <td class="px-5 py-2 text-stone-900"><?php echo esc_html($t['name']); ?></td>
            <td class="px-5 py-2 font-mono text-[12px] text-stone-700"><?php echo esc_html($t['slug']); ?></td>
            <td class="px-5 py-2 text-stone-600"><?php echo (int) $t['sort_order']; ?></td>
            <td class="px-5 py-2">
              <?php if ((int) $t['is_archived'] === 1): ?>
                <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-stone-100 text-stone-700">Archived</span>
              <?php else: ?>
                <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-green-100 text-green-900">Active</span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-2 text-right text-stone-400 italic text-[12px]">edit (Task 5)</td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>
</div>
```

- [ ] **Step 4: Wire the route in page-admin.php**

Edit `wp-content/themes/bbj-v2-theme/page-admin.php`. Add a branch for `comp-types` after the `roadmap` branch:

```php
            <?php elseif ($active_tab === 'roadmap'): ?>
                <?php get_template_part('template-parts/admin/pane-roadmap'); ?>
            <?php elseif ($active_tab === 'comp-types'): ?>
                <?php get_template_part('template-parts/admin/pane-comp-types'); ?>
```

- [ ] **Step 5: Add sidebar entry**

Edit `wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php`.

In the `Game` section's items array, add a `comp-types` entry after `spoiler-bar`:

```php
    [
        'label' => 'Game',
        'items' => [
            ['slug' => 'players',        'label' => 'Players',       'icon' => 'users'],
            ['slug' => 'seasons',        'label' => 'Seasons',       'icon' => 'calendar'],
            ['slug' => 'spoiler-bar',    'label' => 'Spoiler Bar',   'icon' => 'shield-check'],
            ['slug' => 'comp-types',     'label' => 'Comp Types',    'icon' => 'tag'],   // ← add
        ],
    ],
```

In the `bbj_v2_admin_icon` helper's `$paths` array, add a `tag` entry (before the closing `];`):

```php
            'tag'            => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h.008v.008H6V6z"/>',
```

- [ ] **Step 6: Verify route renders**

Browser-load `/admin/?tab=comp-types` while logged in as admin. Expected: the table shows the three seeded types (HOH, Power of Veto, Misc Comp), all marked Active, all with "edit (Task 5)" placeholder.

- [ ] **Step 7: Verify sidebar entry visible + active**

Same page, sidebar should show "Comp Types" under Game with the tag icon highlighted as active.

- [ ] **Step 8: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/page-admin.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php"
```

- [ ] **Step 9: Commit**

```
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php \
        wp-content/themes/bbj-v2-theme/page-admin.php \
        wp-content/themes/bbj-v2-theme/template-parts/admin/sidebar.php
git commit -m "feat(admin): comp-types pane (read-only listing)"
```

---

### Task 5: Wire add / edit / archive on Comp Types pane

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php`
- Modify: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/inc/template-functions.php`

- [ ] **Step 1: Define expected behavior**

Pane shows an "Add new" form (name + slug input + sort number) plus per-row inline edit (name + sort number + archive toggle button). Form submits to `admin-post.php?action=bbj_v2_save_comp_type`. Handler validates admin-only via `bbj_v2_require_admin()`, sanitizes inputs, upserts via `$wpdb->replace`, busts the comp-types cache, redirects back with a notice.

- [ ] **Step 2: Verify the post handler doesn't exist yet**

```
grep -n "bbj_v2_save_comp_type" wp-content/themes/bbj-v2-theme/inc/template-functions.php
```
Expected: no matches.

- [ ] **Step 3: Add the save+archive handlers in template-functions.php**

Edit `wp-content/themes/bbj-v2-theme/inc/template-functions.php`. Append at end of file (before any closing PHP tag):

```php
/**
 * Comp Types: save (create or update) handler.
 * Posts to admin-post.php?action=bbj_v2_save_comp_type.
 */
add_action('admin_post_bbj_v2_save_comp_type', 'bbj_v2_handle_save_comp_type');
function bbj_v2_handle_save_comp_type(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_save_comp_type');

    global $wpdb;
    $id   = isset($_POST['id'])   ? absint($_POST['id'])         : 0;
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $slug_raw = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $slug = $slug_raw !== '' ? sanitize_title($slug_raw) : sanitize_title($name);
    $sort = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;

    $redirect = add_query_arg('tab', 'comp-types', home_url('/admin/'));

    if ($name === '' || $slug === '') {
        wp_safe_redirect(add_query_arg('bbj_msg', 'name_required', $redirect));
        exit;
    }

    $data = [
        'name'       => $name,
        'slug'       => $slug,
        'sort_order' => $sort,
    ];

    if ($id > 0) {
        $wpdb->update("{$wpdb->prefix}bbj_comp_types", $data, ['id' => $id]);
    } else {
        $wpdb->insert("{$wpdb->prefix}bbj_comp_types", $data + ['is_archived' => 0]);
    }

    do_action('bbj_v2_comp_types_changed');
    wp_safe_redirect(add_query_arg('bbj_msg', 'saved', $redirect));
    exit;
}

/**
 * Comp Types: archive / unarchive toggle.
 */
add_action('admin_post_bbj_v2_toggle_comp_type', 'bbj_v2_handle_toggle_comp_type');
function bbj_v2_handle_toggle_comp_type(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_toggle_comp_type');

    global $wpdb;
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $redirect = add_query_arg('tab', 'comp-types', home_url('/admin/'));

    if ($id <= 0) {
        wp_safe_redirect($redirect);
        exit;
    }

    $current = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT is_archived FROM {$wpdb->prefix}bbj_comp_types WHERE id = %d",
        $id
    ));
    $wpdb->update(
        "{$wpdb->prefix}bbj_comp_types",
        ['is_archived' => $current ? 0 : 1],
        ['id' => $id]
    );

    do_action('bbj_v2_comp_types_changed');
    wp_safe_redirect(add_query_arg('bbj_msg', 'toggled', $redirect));
    exit;
}

/**
 * Cache buster for comp-types reads.
 */
add_action('bbj_v2_comp_types_changed', 'bbj_v2_bust_comp_types_cache');
function bbj_v2_bust_comp_types_cache(): void
{
    wp_cache_delete('bbj_v2_comp_types_active', 'bbj_v2');
    wp_cache_delete('bbj_v2_comp_types_all',    'bbj_v2');
}
```

- [ ] **Step 4: Replace the read-only pane with the editable version**

Overwrite `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php`:

```php
<?php
/**
 * Admin pane: Comp Types CRUD.
 *
 * Lists all comp types, plus an Add form and per-row edit/archive form.
 * Posts to admin-post.php handlers in inc/template-functions.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

$types = bbj_v2_comp_types_all();
$msg   = isset($_GET['bbj_msg']) ? sanitize_key($_GET['bbj_msg']) : '';
$msg_text = [
    'saved'          => 'Comp type saved.',
    'toggled'        => 'Archive status updated.',
    'name_required'  => 'Name and slug are required.',
][$msg] ?? '';
?>

<div class="space-y-6">
  <header class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-stone-900 pb-4">
    <div>
      <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-stone-500">Game data</p>
      <h1 class="font-osw text-3xl text-primary-500">Comp Types</h1>
      <p class="mt-1 text-sm text-stone-600">Categories used when logging weekly comp wins. Add seasonal regulars as they appear.</p>
    </div>
  </header>

  <?php if ($msg_text): ?>
    <div class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
      <?php echo esc_html($msg_text); ?>
    </div>
  <?php endif; ?>

  <!-- Add new -->
  <section class="rounded-md bg-white border border-stone-200 p-5">
    <h2 class="font-osw text-lg text-primary-500 mb-3">Add new comp type</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="grid grid-cols-1 sm:grid-cols-[1fr_180px_120px_auto] gap-3 items-end">
      <?php wp_nonce_field('bbj_v2_save_comp_type'); ?>
      <input type="hidden" name="action" value="bbj_v2_save_comp_type">
      <label class="text-sm">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Name</span>
        <input type="text" name="name" required class="w-full px-3 py-2 border border-stone-300 rounded text-sm" placeholder="e.g. Battle Back">
      </label>
      <label class="text-sm">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Slug (auto if blank)</span>
        <input type="text" name="slug" class="w-full px-3 py-2 border border-stone-300 rounded text-sm font-mono" placeholder="auto-generated">
      </label>
      <label class="text-sm">
        <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Sort</span>
        <input type="number" name="sort_order" value="50" class="w-full px-3 py-2 border border-stone-300 rounded text-sm">
      </label>
      <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-osw uppercase tracking-wider rounded">Add</button>
    </form>
  </section>

  <!-- List -->
  <section class="rounded-md bg-white border border-stone-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-stone-50 border-b border-stone-200">
        <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600">
          <th class="text-left font-semibold px-5 py-2 w-[220px]">Name</th>
          <th class="text-left font-semibold px-5 py-2 w-[140px]">Slug</th>
          <th class="text-left font-semibold px-5 py-2 w-[100px]">Sort</th>
          <th class="text-left font-semibold px-5 py-2 w-[100px]">Status</th>
          <th class="text-right font-semibold px-5 py-2 w-[200px]">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($types)): ?>
          <tr><td colspan="5" class="px-5 py-6 text-center text-stone-500 italic">No comp types yet.</td></tr>
        <?php else: foreach ($types as $t): ?>
          <tr class="border-t border-stone-100 align-top">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="contents">
              <?php wp_nonce_field('bbj_v2_save_comp_type'); ?>
              <input type="hidden" name="action" value="bbj_v2_save_comp_type">
              <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
              <input type="hidden" name="slug" value="<?php echo esc_attr($t['slug']); ?>">
              <td class="px-5 py-2"><input type="text" name="name" value="<?php echo esc_attr($t['name']); ?>" class="w-full px-2 py-1 border border-stone-300 rounded text-sm"></td>
              <td class="px-5 py-2 font-mono text-[12px] text-stone-700"><?php echo esc_html($t['slug']); ?></td>
              <td class="px-5 py-2"><input type="number" name="sort_order" value="<?php echo (int) $t['sort_order']; ?>" class="w-20 px-2 py-1 border border-stone-300 rounded text-sm"></td>
              <td class="px-5 py-2">
                <?php if ((int) $t['is_archived'] === 1): ?>
                  <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-stone-100 text-stone-700">Archived</span>
                <?php else: ?>
                  <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-green-100 text-green-900">Active</span>
                <?php endif; ?>
              </td>
              <td class="px-5 py-2 text-right">
                <button type="submit" class="px-3 py-1 bg-primary-500 text-white text-xs font-osw uppercase tracking-wider rounded">Save</button>
            </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
                  <?php wp_nonce_field('bbj_v2_toggle_comp_type'); ?>
                  <input type="hidden" name="action" value="bbj_v2_toggle_comp_type">
                  <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                  <button type="submit" class="px-3 py-1 bg-stone-100 text-stone-700 text-xs font-osw uppercase tracking-wider rounded">
                    <?php echo (int) $t['is_archived'] === 1 ? 'Unarchive' : 'Archive'; ?>
                  </button>
                </form>
              </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>
</div>
```

- [ ] **Step 5: Verify add flow**

Browser-load `/admin/?tab=comp-types`. Use the Add form to add `Battle Back` with sort 40. Submit. Expected: redirect back, "Comp type saved." message visible, new row appears in the table.

- [ ] **Step 6: Verify edit flow**

Change Battle Back's sort from 40 to 25, click Save. Expected: row updates and the page redirects with the saved confirmation; reloading the page persists the new value.

- [ ] **Step 7: Verify archive toggle**

Click Archive on Battle Back. Expected: status flips to "Archived", button now reads "Unarchive". Click again — flips back.

- [ ] **Step 8: Verify cache busts**

```
/c/xampp/mysql/bin/mysql.exe -u root bbj_db -e "SELECT id, slug, name, sort_order, is_archived FROM wp_bbj_comp_types ORDER BY sort_order;"
```
Expected: Battle Back row reflects the latest state. Reload `/admin/?tab=comp-types` — row matches DB state (proving the cache was busted).

- [ ] **Step 9: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/template-functions.php"
```

- [ ] **Step 10: Commit**

```
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-comp-types.php \
        wp-content/themes/bbj-v2-theme/inc/template-functions.php
git commit -m "feat(admin): comp-types add/edit/archive flows"
```

---

## Phase 3 — Weeks tab on the season editor

### Task 6: Implement remaining read helpers (active players + season weeks)

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`

- [ ] **Step 1: Define expected behavior**

`bbj_v2_active_players_for_week(int $season_post_id, int $week_id): array` — for the given week, returns players who are still in the house going INTO this week. Logic:
- If `wp_bbj_weeks_players` rows exist for this `week_id` → return those rows (already seeded when the week was created).
- Otherwise → return rows from `wp_bbj_v2_player_season` where the player has not yet been evicted in any prior week of this season. (Used when a brand-new week is being created; no seeded rows yet.)

Each row in the return array has: `player_id`, `player_name`, `nom`, `evicted`, `veto_played`, `voted_for`, `vote_to_win`, `saved_by_player_id`, `active`, plus a `comps` array of joined `wp_bbj_week_comps` rows for that player+week.

`bbj_v2_season_weeks(int $season_post_id): array` — returns all weeks for the season ordered by `week_num ASC`. Each row has `id, season_id, week_num, start_date, end_date, summary` plus `comp_count` (junction count) and `evicted_count`.

- [ ] **Step 2: Verify functions return empty arrays currently (stubs)**

Same verification approach as Task 3 (temporary verify file).

- [ ] **Step 3: Implement `bbj_v2_season_weeks`**

In `inc/weekly-tracker-data.php`, replace the stub:

```php
function bbj_v2_season_weeks(int $season_post_id): array
{
    $cache_key = 'bbj_v2_season_weeks_' . $season_post_id;
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT w.id, w.season_id, w.week_num, w.start_date, w.end_date, w.summary,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_week_comps wc WHERE wc.week_id = w.id) AS comp_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_weeks_players wp_e WHERE wp_e.week_id = w.id AND wp_e.evicted = 1) AS evicted_count
           FROM {$wpdb->prefix}bbj_weeks w
          WHERE w.season_id = %d
          ORDER BY w.week_num ASC",
        $season_post_id
    ), ARRAY_A) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}
```

- [ ] **Step 4: Implement `bbj_v2_active_players_for_week`**

```php
function bbj_v2_active_players_for_week(int $season_post_id, int $week_id): array
{
    global $wpdb;

    // First, check if rows already exist for this week — if so, use those.
    $existing = $wpdb->get_results($wpdb->prepare(
        "SELECT wp.id AS row_id, wp.player_id, p.post_title AS player_name,
                wp.nom, wp.evicted, wp.veto_played, wp.voted_for, wp.vote_to_win,
                wp.saved_by_player_id, wp.active
           FROM {$wpdb->prefix}bbj_weeks_players wp
           INNER JOIN {$wpdb->posts} p ON p.ID = wp.player_id
          WHERE wp.week_id = %d
          ORDER BY p.post_title ASC",
        $week_id
    ), ARRAY_A) ?: [];

    if (!empty($existing)) {
        return bbj_v2_attach_week_comps($existing, $week_id);
    }

    // No rows yet — derive cast from wp_bbj_v2_player_season minus anyone
    // evicted in a prior week of this season.
    $derived = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT j.bbj_player AS player_id, p.post_title AS player_name,
                0 AS row_id, 0 AS nom, 0 AS evicted, 0 AS veto_played,
                0 AS voted_for, 0 AS vote_to_win, NULL AS saved_by_player_id, 1 AS active
           FROM {$wpdb->prefix}bbj_v2_player_season j
           INNER JOIN {$wpdb->posts} p ON p.ID = j.bbj_player
          WHERE j.bbj_season = %d
            AND j.bbj_player NOT IN (
                SELECT wp_prior.player_id
                  FROM {$wpdb->prefix}bbj_weeks_players wp_prior
                  INNER JOIN {$wpdb->prefix}bbj_weeks w_prior ON w_prior.id = wp_prior.week_id
                 WHERE w_prior.season_id = %d
                   AND wp_prior.evicted = 1
            )
            AND p.post_status = 'publish'
          ORDER BY p.post_title ASC",
        $season_post_id,
        $season_post_id
    ), ARRAY_A) ?: [];

    return bbj_v2_attach_week_comps($derived, $week_id);
}

/**
 * Attach a `comps` array (rows from wp_bbj_week_comps joined to wp_bbj_comp_types)
 * to each player row.
 */
function bbj_v2_attach_week_comps(array $rows, int $week_id): array
{
    if (empty($rows) || $week_id <= 0) {
        foreach ($rows as &$r) { $r['comps'] = []; }
        return $rows;
    }

    global $wpdb;
    $player_ids = array_column($rows, 'player_id');
    $placeholders = implode(',', array_fill(0, count($player_ids), '%d'));

    $comp_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT wc.id, wc.week_id, wc.player_id, wc.comp_type_id, wc.opponents_count, wc.notes,
                ct.slug, ct.name
           FROM {$wpdb->prefix}bbj_week_comps wc
           INNER JOIN {$wpdb->prefix}bbj_comp_types ct ON ct.id = wc.comp_type_id
          WHERE wc.week_id = %d AND wc.player_id IN ($placeholders)",
        array_merge([$week_id], $player_ids)
    ), ARRAY_A) ?: [];

    $by_player = [];
    foreach ($comp_rows as $cr) {
        $by_player[(int) $cr['player_id']][] = $cr;
    }
    foreach ($rows as &$r) {
        $r['comps'] = $by_player[(int) $r['player_id']] ?? [];
    }
    return $rows;
}
```

- [ ] **Step 5: Verify with a temporary script**

Create `wp-content/themes/bbj-v2-theme/_verify-week-helpers.php`:

```php
<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;
$bb25_id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='bigbrother-seasons' AND post_title='Big Brother 25' LIMIT 1");

echo "BB25 post_id: $bb25_id\n";
$weeks = bbj_v2_season_weeks($bb25_id);
echo "Weeks: " . count($weeks) . "\n";

if (!empty($weeks)) {
    $first = $weeks[0];
    echo "First week ID: {$first['id']}, comp_count: {$first['comp_count']}, evicted_count: {$first['evicted_count']}\n";
    $players = bbj_v2_active_players_for_week($bb25_id, (int) $first['id']);
    echo "Active players in first week: " . count($players) . "\n";
    if (!empty($players)) {
        echo "First player: " . print_r($players[0], true);
    }
}
```

Run:
```
php "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-week-helpers.php"
```
Expected: weeks count = 17 (BB25), first week's `comp_count` ≈ 2-3, `active players` ~17 (full cast).

- [ ] **Step 6: Delete the verify file**

```
rm "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-week-helpers.php"
```

- [ ] **Step 7: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php"
```

- [ ] **Step 8: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php
git commit -m "feat(weekly-tracker): season_weeks + active_players_for_week helpers"
```

---

### Task 7: Implement upsert helpers + cache busters

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/inc/template-functions.php`

- [ ] **Step 1: Define expected behavior**

`bbj_v2_save_week_comp(int $week_id, int $player_id, int $comp_type_id, ?int $opponents = null, ?string $notes = null): int` — inserts a row in `wp_bbj_week_comps` if no matching `(week, player, type)` exists; otherwise updates `opponents_count` + `notes`. Returns the row id. Fires `do_action('bbj_v2_week_comp_saved', $week_id, $player_id)`.

`bbj_v2_delete_week_comp(int $id): bool` — removes a row by id. Fires the same action.

`bbj_v2_save_week_player(array $row): int` — upserts a `wp_bbj_weeks_players` row keyed by `(week_id, player_id)`. The `$row` array must include `week_id`, `player_id`; other fields optional and default to existing-row values. Returns the row id. Fires `do_action('bbj_v2_week_player_saved', $week_id, $player_id)`.

Cache busters in `inc/template-functions.php`:
- On `bbj_v2_week_comp_saved` → bust `bbj_v2_season_weeks_<season_id>`, `bbj_v2_player_career_totals_<player_id>`, the player's archive aggregate, and any season-profile cache keys for that season.
- On `bbj_v2_week_player_saved` → same set (the row affects week metadata + per-player aggregates).

- [ ] **Step 2: Implement the upsert + delete**

In `inc/weekly-tracker-data.php`, replace the stubs:

```php
function bbj_v2_save_week_comp(int $week_id, int $player_id, int $comp_type_id, ?int $opponents_count = null, ?string $notes = null): int
{
    global $wpdb;
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bbj_week_comps
         WHERE week_id = %d AND player_id = %d AND comp_type_id = %d
         LIMIT 1",
        $week_id, $player_id, $comp_type_id
    ));

    $data = [
        'week_id'         => $week_id,
        'player_id'       => $player_id,
        'comp_type_id'    => $comp_type_id,
        'opponents_count' => $opponents_count,
        'notes'           => $notes,
    ];

    if ($existing > 0) {
        $wpdb->update("{$wpdb->prefix}bbj_week_comps", $data, ['id' => $existing]);
        $id = $existing;
    } else {
        $wpdb->insert("{$wpdb->prefix}bbj_week_comps", $data);
        $id = (int) $wpdb->insert_id;
    }

    do_action('bbj_v2_week_comp_saved', $week_id, $player_id);
    return $id;
}

function bbj_v2_delete_week_comp(int $id): bool
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT week_id, player_id FROM {$wpdb->prefix}bbj_week_comps WHERE id = %d",
        $id
    ), ARRAY_A);
    if (!$row) {
        return false;
    }
    $deleted = $wpdb->delete("{$wpdb->prefix}bbj_week_comps", ['id' => $id]);
    if ($deleted) {
        do_action('bbj_v2_week_comp_saved', (int) $row['week_id'], (int) $row['player_id']);
    }
    return (bool) $deleted;
}

function bbj_v2_save_week_player(array $row): int
{
    global $wpdb;
    $week_id   = (int) ($row['week_id']   ?? 0);
    $player_id = (int) ($row['player_id'] ?? 0);
    if ($week_id <= 0 || $player_id <= 0) {
        return 0;
    }

    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bbj_weeks_players
         WHERE week_id = %d AND player_id = %d
         LIMIT 1",
        $week_id, $player_id
    ));

    $data = [
        'week_id'             => $week_id,
        'player_id'           => $player_id,
        'season_id'           => (int) ($row['season_id'] ?? 0),
        'nom'                 => isset($row['nom'])                 ? (int) $row['nom']                 : 0,
        'evicted'             => isset($row['evicted'])             ? (int) $row['evicted']             : 0,
        'veto_played'         => isset($row['veto_played'])         ? (int) $row['veto_played']         : 0,
        'voted_for'           => isset($row['voted_for'])           ? (int) $row['voted_for']           : 0,
        'vote_to_win'         => isset($row['vote_to_win'])         ? (int) $row['vote_to_win']         : 0,
        'active'              => isset($row['active'])              ? (int) $row['active']              : 1,
        'saved_by_player_id'  => isset($row['saved_by_player_id'])  ? (int) $row['saved_by_player_id'] ?: null : null,
    ];

    if ($existing > 0) {
        $wpdb->update("{$wpdb->prefix}bbj_weeks_players", $data, ['id' => $existing]);
        $id = $existing;
    } else {
        $wpdb->insert("{$wpdb->prefix}bbj_weeks_players", $data);
        $id = (int) $wpdb->insert_id;
    }

    do_action('bbj_v2_week_player_saved', $week_id, $player_id);
    return $id;
}
```

- [ ] **Step 3: Add cache busters in template-functions.php**

Append to `inc/template-functions.php`:

```php
add_action('bbj_v2_week_comp_saved',   'bbj_v2_bust_weekly_caches', 10, 2);
add_action('bbj_v2_week_player_saved', 'bbj_v2_bust_weekly_caches', 10, 2);
function bbj_v2_bust_weekly_caches(int $week_id, int $player_id): void
{
    global $wpdb;
    $season_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT season_id FROM {$wpdb->prefix}bbj_weeks WHERE id = %d",
        $week_id
    ));

    wp_cache_delete('bbj_v2_season_weeks_' . $season_id, 'bbj_v2');
    wp_cache_delete('bbj_v2_player_career_totals_' . $player_id, 'bbj_v2');
    wp_cache_delete('bbj_v2_player_weeks_' . $player_id . '_' . $season_id, 'bbj_v2');
    wp_cache_delete('bbj_v2_archive_all_players', 'bbj_v2'); // junction-aware aggregate
    wp_cache_delete('season_profile_data_' . $season_id, 'bbj_v2');
}
```

- [ ] **Step 4: Verify with temporary script**

Create `wp-content/themes/bbj-v2-theme/_verify-upserts.php`:

```php
<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;
$bb25_id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='bigbrother-seasons' AND post_title='Big Brother 25' LIMIT 1");
$first_week = $wpdb->get_row($wpdb->prepare(
    "SELECT id, week_num FROM {$wpdb->prefix}bbj_weeks WHERE season_id = %d ORDER BY week_num ASC LIMIT 1",
    $bb25_id
), ARRAY_A);

$first_player = $wpdb->get_row($wpdb->prepare(
    "SELECT j.bbj_player AS player_id FROM {$wpdb->prefix}bbj_v2_player_season j WHERE j.bbj_season = %d LIMIT 1",
    $bb25_id
), ARRAY_A);

$misc_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}bbj_comp_types WHERE slug = 'misc'");

echo "Inserting test misc-comp row...\n";
$id = bbj_v2_save_week_comp((int) $first_week['id'], (int) $first_player['player_id'], $misc_id, 8, 'TEST verify');
echo "Inserted id: $id\n";

echo "Re-saving (should update, not duplicate)...\n";
$id2 = bbj_v2_save_week_comp((int) $first_week['id'], (int) $first_player['player_id'], $misc_id, 9, 'TEST updated');
echo "Returned id: $id2 (should equal $id)\n";

echo "Deleting...\n";
$deleted = bbj_v2_delete_week_comp($id);
echo "Deleted: " . ($deleted ? 'yes' : 'no') . "\n";
```

Run:
```
php "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-upserts.php"
```
Expected: insert returns positive id, re-save returns the same id (proves upsert), delete returns yes.

- [ ] **Step 5: Delete the verify file**

```
rm "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-upserts.php"
```

- [ ] **Step 6: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/template-functions.php"
```

- [ ] **Step 7: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php \
        wp-content/themes/bbj-v2-theme/inc/template-functions.php
git commit -m "feat(weekly-tracker): save_week_comp / save_week_player upserts + cache busters"
```

---

### Task 8: Build the Weeks tab + week-edit form

**Files:**
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-weeks.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/week-edit-form.php`
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php`
- Modify: `wp-content/themes/bbj-v2-theme/inc/template-functions.php`

- [ ] **Step 1: Define expected behavior**

Visiting `/admin/?tab=seasons&edit=<season_id>#weeks` adds a "Weeks" tab to the existing tab nav (currently Spoiler Bar / Info / Photos). Selecting it shows the week picker (pills for each existing week + "Add Week" button) + the per-week form for the selected week.

The form posts to `admin-post.php?action=bbj_v2_save_week`. The handler iterates the submitted player rows, calling `bbj_v2_save_week_player` and `bbj_v2_save_week_comp` for each, plus updates the parent `wp_bbj_weeks` row's date / summary fields. Redirects back with a "Saved" notice.

The "Add Week" button posts to `admin-post.php?action=bbj_v2_add_week`. Handler creates a new `wp_bbj_weeks` row for `season_id` with `week_num = MAX(week_num)+1` (or 1 if none exist), copies the previous week's `evicted=0` / `active=1` players into `wp_bbj_weeks_players` rows for the new week (or full cast for week 1).

- [ ] **Step 2: Read the existing seasons-edit pane to understand its tab nav**

```
cat "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php" | head -80
```
Expected: shows the existing tab structure (Spoiler / Info / Photos with `#spoiler` / `#info` / `#photos` anchors).

- [ ] **Step 3: Add the Weeks tab to pane-seasons-edit.php**

Find the existing tab list. After the last `<a>` (likely `#photos`), add a new tab anchor:

```php
<a href="#weeks" class="<?php echo $active_tab === 'weeks' ? 'active' : ''; ?>"
   data-tab="weeks">Weeks</a>
```

Find the existing tab-content div for `#photos`. After its closing tag, add:

```php
<div class="tab-content" id="weeks-tab" data-tab-pane="weeks">
    <?php get_template_part('template-parts/admin/pane-seasons-weeks', null, [
        'season_post_id' => $season_post_id,
        'season_row'     => $season_row ?? null,
    ]); ?>
</div>
```

(Adjust variable names — `$season_post_id`, `$season_row` — to match what the existing pane-seasons-edit.php uses; this plan assumes those are in scope based on the existing Spoiler Bar tab.)

- [ ] **Step 4: Create the Weeks tab pane**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-weeks.php`:

```php
<?php
/**
 * Weeks tab for the season editor.
 *
 * Pulls all weeks for the season, lets the user pick one (or add a new one),
 * and renders the per-week edit form.
 *
 * Expected args (from get_template_part):
 *   - season_post_id (int)
 *   - season_row     (?array)  — optional season metadata
 */

if (!defined('ABSPATH')) {
    exit;
}

bbj_v2_require_admin();

$args = $args ?? [];
$season_post_id = (int) ($args['season_post_id'] ?? 0);
if ($season_post_id <= 0) {
    echo '<p class="text-stone-500 italic">Missing season ID.</p>';
    return;
}

$weeks = bbj_v2_season_weeks($season_post_id);
$selected_week_id = isset($_GET['week']) ? (int) $_GET['week'] : ((int) ($weeks[0]['id'] ?? 0));
$current_week = null;
foreach ($weeks as $w) {
    if ((int) $w['id'] === $selected_week_id) { $current_week = $w; break; }
}

$msg = isset($_GET['bbj_msg']) ? sanitize_key($_GET['bbj_msg']) : '';
?>

<div class="space-y-4">

  <?php if ($msg === 'week_saved'): ?>
    <div class="rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-900">Week saved.</div>
  <?php elseif ($msg === 'week_added'): ?>
    <div class="rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-900">New week added.</div>
  <?php endif; ?>

  <!-- Week picker -->
  <div class="flex flex-wrap items-center gap-2 border-b border-stone-200 pb-3">
    <?php if (empty($weeks)): ?>
      <span class="text-stone-500 italic text-sm">No weeks yet.</span>
    <?php else: foreach ($weeks as $w):
        $url = add_query_arg(['week' => $w['id']]);
        $is_active = ((int) $w['id'] === $selected_week_id);
    ?>
      <a href="<?php echo esc_url($url); ?>#weeks"
         class="px-3 py-1 text-xs font-osw uppercase tracking-wider rounded <?php echo $is_active ? 'bg-secondary-500 text-primary-500' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'; ?>">
        Week <?php echo (int) $w['week_num']; ?>
        <?php if ((int) $w['evicted_count'] > 0): ?><span class="ml-1 text-[10px] opacity-70">·E</span><?php endif; ?>
      </a>
    <?php endforeach; endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ml-auto">
      <?php wp_nonce_field('bbj_v2_add_week'); ?>
      <input type="hidden" name="action" value="bbj_v2_add_week">
      <input type="hidden" name="season_post_id" value="<?php echo (int) $season_post_id; ?>">
      <button type="submit" class="px-3 py-1 bg-primary-500 text-white text-xs font-osw uppercase tracking-wider rounded">+ Add Week</button>
    </form>
  </div>

  <!-- Selected week form -->
  <?php if ($current_week): ?>
    <?php get_template_part('template-parts/admin/partials/week-edit-form', null, [
        'season_post_id' => $season_post_id,
        'week'           => $current_week,
    ]); ?>
  <?php elseif (!empty($weeks)): ?>
    <p class="text-stone-500 italic text-sm">Select a week above to edit.</p>
  <?php endif; ?>
</div>
```

- [ ] **Step 5: Create the week-edit-form partial**

Create `wp-content/themes/bbj-v2-theme/template-parts/admin/partials/week-edit-form.php`:

```php
<?php
/**
 * Per-week edit grid form. Submits to admin-post.php?action=bbj_v2_save_week.
 *
 * Expected args:
 *   - season_post_id (int)
 *   - week (array — wp_bbj_weeks row + comp_count + evicted_count)
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = $args ?? [];
$season_post_id = (int) ($args['season_post_id'] ?? 0);
$week = $args['week'] ?? null;
if ($season_post_id <= 0 || !is_array($week)) {
    return;
}

$week_id    = (int) $week['id'];
$players    = bbj_v2_active_players_for_week($season_post_id, $week_id);
$comp_types = bbj_v2_comp_types_active();
?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="space-y-4">
  <?php wp_nonce_field('bbj_v2_save_week'); ?>
  <input type="hidden" name="action" value="bbj_v2_save_week">
  <input type="hidden" name="season_post_id" value="<?php echo (int) $season_post_id; ?>">
  <input type="hidden" name="week_id" value="<?php echo (int) $week_id; ?>">

  <!-- Week meta -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <label class="text-sm">
      <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Week #</span>
      <input type="number" name="week_num" value="<?php echo (int) $week['week_num']; ?>" class="w-full px-2 py-1 border border-stone-300 rounded text-sm">
    </label>
    <label class="text-sm">
      <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Start date</span>
      <input type="date" name="start_date" value="<?php echo esc_attr($week['start_date']); ?>" class="w-full px-2 py-1 border border-stone-300 rounded text-sm">
    </label>
    <label class="text-sm">
      <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">End date</span>
      <input type="date" name="end_date" value="<?php echo esc_attr($week['end_date']); ?>" class="w-full px-2 py-1 border border-stone-300 rounded text-sm">
    </label>
  </div>

  <label class="text-sm block">
    <span class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-500 block mb-1">Week summary (editorial recap)</span>
    <textarea name="summary" rows="3" class="w-full px-2 py-1 border border-stone-300 rounded text-sm"><?php echo esc_textarea($week['summary'] ?? ''); ?></textarea>
  </label>

  <!-- Players grid -->
  <div class="rounded-md bg-white border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-stone-50 border-b border-stone-200">
        <tr class="font-mono text-[10px] uppercase tracking-[0.08em] text-stone-600">
          <th class="text-left px-3 py-2">Player</th>
          <th class="text-left px-3 py-2">Comps won</th>
          <th class="text-left px-3 py-2 w-[60px]">Nom</th>
          <th class="text-left px-3 py-2 w-[200px]">Saved by</th>
          <th class="text-left px-3 py-2 w-[60px]">Evict</th>
          <th class="text-left px-3 py-2 w-[200px]">Voted to evict</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($players as $i => $p):
          $pid = (int) $p['player_id'];
          $existing_comp_ids = array_map('intval', array_column($p['comps'], 'comp_type_id'));
        ?>
          <tr class="border-t border-stone-100 align-top">
            <input type="hidden" name="rows[<?php echo $i; ?>][player_id]" value="<?php echo $pid; ?>">
            <td class="px-3 py-2 text-stone-900"><?php echo esc_html($p['player_name']); ?></td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-1">
                <?php foreach ($comp_types as $ct):
                    $checked = in_array((int) $ct['id'], $existing_comp_ids, true);
                ?>
                  <label class="inline-flex items-center gap-1 text-[12px] px-2 py-0.5 border border-stone-300 rounded cursor-pointer <?php echo $checked ? 'bg-amber-100 border-amber-300' : 'bg-white'; ?>">
                    <input type="checkbox" name="rows[<?php echo $i; ?>][comps][]" value="<?php echo (int) $ct['id']; ?>" <?php checked($checked); ?> class="hidden">
                    <span><?php echo esc_html($ct['name']); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </td>
            <td class="px-3 py-2"><input type="checkbox" name="rows[<?php echo $i; ?>][nom]" value="1" <?php checked((int) $p['nom']); ?>></td>
            <td class="px-3 py-2">
              <select name="rows[<?php echo $i; ?>][saved_by_player_id]" class="w-full px-2 py-1 border border-stone-300 rounded text-sm">
                <option value="">— not saved —</option>
                <option value="<?php echo $pid; ?>" <?php selected((int) ($p['saved_by_player_id'] ?? 0), $pid); ?>>Self / Twist</option>
                <?php foreach ($players as $other):
                    $oid = (int) $other['player_id'];
                    if ($oid === $pid) continue;
                ?>
                  <option value="<?php echo $oid; ?>" <?php selected((int) ($p['saved_by_player_id'] ?? 0), $oid); ?>>
                    <?php echo esc_html($other['player_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="px-3 py-2"><input type="checkbox" name="rows[<?php echo $i; ?>][evicted]" value="1" <?php checked((int) $p['evicted']); ?>></td>
            <td class="px-3 py-2">
              <select name="rows[<?php echo $i; ?>][voted_for]" class="w-full px-2 py-1 border border-stone-300 rounded text-sm">
                <option value="0">—</option>
                <?php foreach ($players as $target):
                    $tid = (int) $target['player_id'];
                    if ($tid === $pid) continue;
                ?>
                  <option value="<?php echo $tid; ?>" <?php selected((int) $p['voted_for'], $tid); ?>>
                    <?php echo esc_html($target['player_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="flex justify-end">
    <button type="submit" class="px-5 py-2 bg-primary-500 text-white text-sm font-osw uppercase tracking-wider rounded">Save Week</button>
  </div>
</form>
```

- [ ] **Step 6: Add the save-week + add-week handlers**

Append to `inc/template-functions.php`:

```php
add_action('admin_post_bbj_v2_add_week', 'bbj_v2_handle_add_week');
function bbj_v2_handle_add_week(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_add_week');

    global $wpdb;
    $season_post_id = isset($_POST['season_post_id']) ? absint($_POST['season_post_id']) : 0;
    if ($season_post_id <= 0) {
        wp_safe_redirect(home_url('/admin/?tab=seasons'));
        exit;
    }

    // Find the highest existing week_num for this season
    $next_num = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(MAX(week_num), 0) + 1 FROM {$wpdb->prefix}bbj_weeks WHERE season_id = %d",
        $season_post_id
    ));

    // Today as default
    $today = current_time('Y-m-d');
    $wpdb->insert("{$wpdb->prefix}bbj_weeks", [
        'season_id'  => $season_post_id,
        'week_num'   => $next_num,
        'start_date' => $today,
        'end_date'   => $today,
    ]);
    $new_week_id = (int) $wpdb->insert_id;

    // Seed wp_bbj_weeks_players from previous week's active+non-evicted players,
    // or from full cast if week 1.
    if ($next_num === 1) {
        // Week 1: full cast
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}bbj_weeks_players (week_id, season_id, player_id, active)
             SELECT %d, %d, j.bbj_player, 1
               FROM {$wpdb->prefix}bbj_v2_player_season j
              WHERE j.bbj_season = %d",
            $new_week_id, $season_post_id, $season_post_id
        ));
    } else {
        // Copy previous week's players where evicted=0
        $prev_week_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bbj_weeks
              WHERE season_id = %d AND week_num = %d
              LIMIT 1",
            $season_post_id, $next_num - 1
        ));
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}bbj_weeks_players (week_id, season_id, player_id, active)
             SELECT %d, %d, prev.player_id, 1
               FROM {$wpdb->prefix}bbj_weeks_players prev
              WHERE prev.week_id = %d AND prev.evicted = 0",
            $new_week_id, $season_post_id, $prev_week_id
        ));
    }

    wp_cache_delete('bbj_v2_season_weeks_' . $season_post_id, 'bbj_v2');

    $redirect = add_query_arg([
        'tab'     => 'seasons',
        'edit'    => $season_post_id,
        'week'    => $new_week_id,
        'bbj_msg' => 'week_added',
    ], home_url('/admin/'));
    wp_safe_redirect($redirect . '#weeks');
    exit;
}

add_action('admin_post_bbj_v2_save_week', 'bbj_v2_handle_save_week');
function bbj_v2_handle_save_week(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Forbidden', ['response' => 403]);
    }
    check_admin_referer('bbj_v2_save_week');

    global $wpdb;
    $season_post_id = isset($_POST['season_post_id']) ? absint($_POST['season_post_id']) : 0;
    $week_id        = isset($_POST['week_id'])        ? absint($_POST['week_id'])        : 0;
    if ($season_post_id <= 0 || $week_id <= 0) {
        wp_safe_redirect(home_url('/admin/?tab=seasons'));
        exit;
    }

    // Update wp_bbj_weeks meta
    $wpdb->update(
        "{$wpdb->prefix}bbj_weeks",
        [
            'week_num'   => isset($_POST['week_num']) ? (int) $_POST['week_num'] : 1,
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'   => sanitize_text_field($_POST['end_date'] ?? ''),
            'summary'    => wp_kses_post($_POST['summary'] ?? ''),
        ],
        ['id' => $week_id]
    );

    // Iterate submitted player rows
    $rows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : [];
    foreach ($rows as $row) {
        $player_id = isset($row['player_id']) ? (int) $row['player_id'] : 0;
        if ($player_id <= 0) continue;

        $saved_by = isset($row['saved_by_player_id']) && $row['saved_by_player_id'] !== ''
            ? (int) $row['saved_by_player_id']
            : null;

        bbj_v2_save_week_player([
            'week_id'            => $week_id,
            'player_id'          => $player_id,
            'season_id'          => $season_post_id,
            'nom'                => isset($row['nom'])     ? 1 : 0,
            'evicted'            => isset($row['evicted']) ? 1 : 0,
            'voted_for'          => isset($row['voted_for']) ? (int) $row['voted_for'] : 0,
            'saved_by_player_id' => $saved_by,
            'active'             => isset($row['evicted']) ? 0 : 1,
        ]);

        // Sync comp wins: insert all checked, delete any existing-but-not-checked
        $checked = isset($row['comps']) && is_array($row['comps'])
            ? array_map('intval', $row['comps'])
            : [];
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT id, comp_type_id FROM {$wpdb->prefix}bbj_week_comps
              WHERE week_id = %d AND player_id = %d",
            $week_id, $player_id
        ), ARRAY_A) ?: [];
        $existing_ids = array_column($existing, 'comp_type_id');
        $existing_ids = array_map('intval', $existing_ids);

        foreach ($checked as $type_id) {
            if (!in_array($type_id, $existing_ids, true)) {
                bbj_v2_save_week_comp($week_id, $player_id, $type_id);
            }
        }
        foreach ($existing as $row_e) {
            if (!in_array((int) $row_e['comp_type_id'], $checked, true)) {
                bbj_v2_delete_week_comp((int) $row_e['id']);
            }
        }
    }

    $redirect = add_query_arg([
        'tab'     => 'seasons',
        'edit'    => $season_post_id,
        'week'    => $week_id,
        'bbj_msg' => 'week_saved',
    ], home_url('/admin/'));
    wp_safe_redirect($redirect . '#weeks');
    exit;
}
```

- [ ] **Step 7: Verify Add Week works**

Browser-load `/admin/?tab=seasons&edit=<BB27_post_id>` (BB27 has no weeks yet). Click Weeks tab. Click "+ Add Week". Expected: redirect with "New week added" notice; Week 1 pill appears; the form below shows the full BB27 cast with empty checkboxes.

- [ ] **Step 8: Verify Save Week persists**

In the Week 1 form: check HoH on one player, check Nom on two others, set saved_by on one nom to be the HoH winner, check Evict on the other nom, set Voted columns. Click Save Week. Expected: redirect with "Week saved" notice; reload — values persist.

- [ ] **Step 9: Verify checkbox styling**

Look at the comps-won column. Expected: clicking a comp pill toggles the amber background (the underlying checkbox is hidden — visual state lives on the wrapping label).

(NOTE: the `<input type="checkbox" class="hidden">` pattern needs CSS to actually toggle the label background. If the styling doesn't work as expected, add this minimal JS at the bottom of `pane-seasons-weeks.php`:

```html
<script>
document.querySelectorAll('label.cursor-pointer input[type="checkbox"]').forEach(function(cb) {
  cb.addEventListener('change', function() {
    var label = cb.closest('label');
    if (cb.checked) {
      label.classList.add('bg-amber-100', 'border-amber-300');
      label.classList.remove('bg-white');
    } else {
      label.classList.remove('bg-amber-100', 'border-amber-300');
      label.classList.add('bg-white');
    }
  });
});
</script>
```
)

- [ ] **Step 10: PHP lint all touched files**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-weeks.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/partials/week-edit-form.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/template-functions.php"
```

- [ ] **Step 11: Commit**

```
git add wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-weeks.php \
        wp-content/themes/bbj-v2-theme/template-parts/admin/partials/week-edit-form.php \
        wp-content/themes/bbj-v2-theme/template-parts/admin/pane-seasons-edit.php \
        wp-content/themes/bbj-v2-theme/inc/template-functions.php
git commit -m "feat(admin): Weeks tab w/ per-week edit grid + add-week handler"
```

---

## Phase 4 — Display layer

### Task 9: Implement junction-aware career totals helper

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`

- [ ] **Step 1: Define expected behavior**

`bbj_v2_player_career_totals(int $player_post_id): array` — returns an associative array `['hoh' => N, 'pov' => N, 'misc' => N, 'noms' => N, 'votes_received' => N, 'season_count' => N]`.

For each season the player participated in (rows in `wp_bbj_v2_player_season`):
- If any `wp_bbj_week_comps` rows exist for `(player, season)` → use junction COUNT for that season
- Otherwise → fall back to `wp_bbj_v2_player_season.bbj_total_*` for that season

Sum across seasons. Cache 1h under `bbj_v2_player_career_totals_<player_post_id>`.

- [ ] **Step 2: Implement**

Replace stub in `inc/weekly-tracker-data.php`:

```php
function bbj_v2_player_career_totals(int $player_post_id): array
{
    $cache_key = 'bbj_v2_player_career_totals_' . $player_post_id;
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $totals = [
        'hoh' => 0, 'pov' => 0, 'misc' => 0,
        'noms' => 0, 'votes_received' => 0, 'season_count' => 0,
    ];

    // Per-season aggregate, junction-first.
    $seasons = $wpdb->get_results($wpdb->prepare(
        "SELECT j.bbj_season AS season_id,
                j.bbj_total_hoh, j.bbj_total_pov, j.bbj_total_misc,
                j.bbj_total_nom, j.bbj_votes_received,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_week_comps wc
                  INNER JOIN {$wpdb->prefix}bbj_weeks w ON w.id = wc.week_id
                 WHERE wc.player_id = j.bbj_player AND w.season_id = j.bbj_season) AS junction_total,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_week_comps wc
                  INNER JOIN {$wpdb->prefix}bbj_weeks w ON w.id = wc.week_id
                  INNER JOIN {$wpdb->prefix}bbj_comp_types ct ON ct.id = wc.comp_type_id
                 WHERE wc.player_id = j.bbj_player AND w.season_id = j.bbj_season AND ct.slug = 'hoh') AS junction_hoh,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_week_comps wc
                  INNER JOIN {$wpdb->prefix}bbj_weeks w ON w.id = wc.week_id
                  INNER JOIN {$wpdb->prefix}bbj_comp_types ct ON ct.id = wc.comp_type_id
                 WHERE wc.player_id = j.bbj_player AND w.season_id = j.bbj_season AND ct.slug = 'pov') AS junction_pov,
                (SELECT COUNT(*) FROM {$wpdb->prefix}bbj_week_comps wc
                  INNER JOIN {$wpdb->prefix}bbj_weeks w ON w.id = wc.week_id
                  INNER JOIN {$wpdb->prefix}bbj_comp_types ct ON ct.id = wc.comp_type_id
                 WHERE wc.player_id = j.bbj_player AND w.season_id = j.bbj_season AND ct.slug = 'misc') AS junction_misc
           FROM {$wpdb->prefix}bbj_v2_player_season j
          WHERE j.bbj_player = %d",
        $player_post_id
    ), ARRAY_A) ?: [];

    foreach ($seasons as $s) {
        $totals['season_count']++;
        $has_junction = ((int) $s['junction_total']) > 0;

        $totals['hoh']  += $has_junction ? (int) $s['junction_hoh']  : (int) $s['bbj_total_hoh'];
        $totals['pov']  += $has_junction ? (int) $s['junction_pov']  : (int) $s['bbj_total_pov'];
        $totals['misc'] += $has_junction ? (int) $s['junction_misc'] : (int) $s['bbj_total_misc'];
        $totals['noms']           += (int) $s['bbj_total_nom'];
        $totals['votes_received'] += (int) $s['bbj_votes_received'];
    }

    wp_cache_set($cache_key, $totals, 'bbj_v2', HOUR_IN_SECONDS);
    return $totals;
}
```

- [ ] **Step 3: Verify**

Create `wp-content/themes/bbj-v2-theme/_verify-career-totals.php`:

```php
<?php
require_once dirname(__DIR__, 3) . '/wp-load.php';

global $wpdb;
// Pick a known BB25 player (e.g. Jag Bains, the winner)
$jag_id = (int) $wpdb->get_var("SELECT post_id FROM {$wpdb->prefix}bbj_players WHERE first_name='Jag' AND last_name='Bains' LIMIT 1");
if (!$jag_id) {
    $jag_id = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}bbj_players WHERE first_name='Jag' AND last_name='Bains' LIMIT 1");
}
echo "Jag post_id: $jag_id\n";

$totals = bbj_v2_player_career_totals($jag_id);
print_r($totals);
```

Run:
```
php "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-career-totals.php"
```
Expected: `season_count=1`, hoh + pov counts > 0 (Jag won several comps in BB25).

- [ ] **Step 4: Delete the verify file**

```
rm "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/_verify-career-totals.php"
```

- [ ] **Step 5: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php"
```

- [ ] **Step 6: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php
git commit -m "feat(weekly-tracker): junction-aware bbj_v2_player_career_totals helper"
```

---

### Task 10: Wire career totals into player archive cards

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/archives-data.php`

- [ ] **Step 1: Define expected behavior**

`bbj_v2_archive_all_players()` currently does `SUM(j.bbj_total_hoh)` etc. across `wp_bbj_v2_player_season`. Change it to call `bbj_v2_player_career_totals($post_id)` per row instead. The function-level cache already exists (1h TTL) so the per-row helper call only fires on cache miss.

- [ ] **Step 2: Read the existing function**

```
grep -n "bbj_v2_archive_all_players" wp-content/themes/bbj-v2-theme/inc/archives-data.php
```
Expected: shows the function around line 18.

- [ ] **Step 3: Refactor the function**

Replace the existing function body. The query should drop the SUM aggregations (those columns become per-player loops):

In `inc/archives-data.php`, find:

```php
            COALESCE(SUM(j.bbj_total_hoh), 0)                 AS total_hoh,
            COALESCE(SUM(j.bbj_total_pov), 0)                 AS total_pov,
            COALESCE(SUM(j.bbj_total_nom), 0)                 AS total_nom,
            COALESCE(SUM(j.bbj_votes_received), 0)            AS total_votes,
```

Remove those four lines. After the `foreach ($rows as &$row)` loop, in the same loop body, append:

```php
        $totals = bbj_v2_player_career_totals((int) $row['post_id']);
        $row['total_hoh']   = $totals['hoh'];
        $row['total_pov']   = $totals['pov'];
        $row['total_nom']   = $totals['noms'];
        $row['total_votes'] = $totals['votes_received'];
```

Also remove `j.bbj_total_*` references from the `LEFT JOIN` since we no longer SUM them.

- [ ] **Step 4: Verify the player directory page still renders**

Browser-load `/bigbrother-players/`. Expected: cards render with the same stat counts they had before (junction-first reads should match the old SUM-based reads for BB23-25 players, fall back to legacy columns for others).

- [ ] **Step 5: Spot-check a known player**

Pick Jag Bains' card on the directory. Expected: HoH and POV counts match the values from the Task 9 verify script.

- [ ] **Step 6: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/archives-data.php"
```

- [ ] **Step 7: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/archives-data.php
git commit -m "refactor(weekly-tracker): player archive uses junction-aware career totals"
```

---

### Task 11: Implement player-weeks helper + Week-by-Week display block

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php`
- Create: `wp-content/themes/bbj-v2-theme/template-parts/player/week-by-week.php`
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`

- [ ] **Step 1: Define expected behavior**

`bbj_v2_player_weeks(int $player_post_id, int $season_post_id): array` — returns the per-week timeline for a player in one season. Each entry has: `week_num`, `start_date`, `end_date`, `summary`, `comps` (array of comp type names won), `was_nom`, `was_evicted`, `was_saved_by` (resolved player name or 'self/twist'), `voted_for_name`. Cached.

The display partial `template-parts/player/week-by-week.php` renders the timeline as cards, one per week, matching the design mockup pattern (week number + date, summary text, comp/state badges).

The single player template includes the partial after the existing season participation block.

- [ ] **Step 2: Implement the helper**

Replace stub in `inc/weekly-tracker-data.php`:

```php
function bbj_v2_player_weeks(int $player_post_id, int $season_post_id): array
{
    $cache_key = 'bbj_v2_player_weeks_' . $player_post_id . '_' . $season_post_id;
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT w.id AS week_id, w.week_num, w.start_date, w.end_date, w.summary,
                wp.nom, wp.evicted, wp.veto_played, wp.saved_by_player_id, wp.voted_for,
                p_saver.post_title  AS saved_by_name,
                p_voted.post_title  AS voted_for_name
           FROM {$wpdb->prefix}bbj_weeks w
           LEFT JOIN {$wpdb->prefix}bbj_weeks_players wp
                  ON wp.week_id = w.id AND wp.player_id = %d
           LEFT JOIN {$wpdb->posts} p_saver  ON p_saver.ID  = wp.saved_by_player_id
           LEFT JOIN {$wpdb->posts} p_voted  ON p_voted.ID  = wp.voted_for
          WHERE w.season_id = %d
          ORDER BY w.week_num ASC",
        $player_post_id, $season_post_id
    ), ARRAY_A) ?: [];

    if (empty($rows)) {
        wp_cache_set($cache_key, [], 'bbj_v2', HOUR_IN_SECONDS);
        return [];
    }

    // Pull per-week comps for this player
    $week_ids = array_column($rows, 'week_id');
    $placeholders = implode(',', array_fill(0, count($week_ids), '%d'));
    $comp_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT wc.week_id, ct.name, ct.slug
           FROM {$wpdb->prefix}bbj_week_comps wc
           INNER JOIN {$wpdb->prefix}bbj_comp_types ct ON ct.id = wc.comp_type_id
          WHERE wc.player_id = %d AND wc.week_id IN ($placeholders)",
        array_merge([$player_post_id], $week_ids)
    ), ARRAY_A) ?: [];

    $comps_by_week = [];
    foreach ($comp_rows as $cr) {
        $comps_by_week[(int) $cr['week_id']][] = ['name' => $cr['name'], 'slug' => $cr['slug']];
    }

    foreach ($rows as &$r) {
        $r['comps'] = $comps_by_week[(int) $r['week_id']] ?? [];

        // Resolve saved_by: 'self/twist' if it's the player themselves, name otherwise
        if ((int) ($r['saved_by_player_id'] ?? 0) === $player_post_id) {
            $r['saved_by_label'] = 'self / twist';
        } elseif ((int) ($r['saved_by_player_id'] ?? 0) > 0) {
            $r['saved_by_label'] = $r['saved_by_name'] ?: '—';
        } else {
            $r['saved_by_label'] = '';
        }
    }

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}
```

- [ ] **Step 3: Create the display partial**

Create `wp-content/themes/bbj-v2-theme/template-parts/player/week-by-week.php`:

```php
<?php
/**
 * Player profile · Week by Week display block.
 *
 * Expected args:
 *   - player_post_id (int)
 *   - season_post_id (int)
 *   - season_label   (string)  — e.g. "Big Brother 26"
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = $args ?? [];
$player_post_id = (int) ($args['player_post_id'] ?? 0);
$season_post_id = (int) ($args['season_post_id'] ?? 0);
$season_label   = (string) ($args['season_label'] ?? '');

if ($player_post_id <= 0 || $season_post_id <= 0) {
    return;
}

$weeks = bbj_v2_player_weeks($player_post_id, $season_post_id);
if (empty($weeks)) {
    return;
}
?>

<section class="player-week-by-week">
  <h3 class="font-osw text-xl text-primary-500 mb-3">
    Week by Week<?php echo $season_label ? ' — ' . esc_html($season_label) : ''; ?>
  </h3>
  <div class="space-y-3">
    <?php foreach ($weeks as $w): ?>
      <article class="week-card border border-stone-200 bg-white p-4 rounded">
        <header class="flex items-baseline justify-between gap-3 mb-1">
          <h4 class="font-osw text-base text-stone-900">Week <?php echo (int) $w['week_num']; ?></h4>
          <span class="font-mono text-[11px] text-stone-500"><?php echo esc_html($w['start_date']); ?> – <?php echo esc_html($w['end_date']); ?></span>
        </header>

        <div class="flex flex-wrap gap-1 mb-2">
          <?php foreach ($w['comps'] as $c): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-amber-100 text-amber-900">
              <?php echo esc_html($c['name']); ?>
            </span>
          <?php endforeach; ?>
          <?php if ((int) $w['nom']): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-rose-100 text-rose-900">Nom</span>
          <?php endif; ?>
          <?php if (!empty($w['saved_by_label'])): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-green-100 text-green-900">
              Saved by <?php echo esc_html($w['saved_by_label']); ?>
            </span>
          <?php endif; ?>
          <?php if ((int) $w['evicted']): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-osw uppercase tracking-wider rounded bg-stone-700 text-white">Evicted</span>
          <?php endif; ?>
          <?php if (!empty($w['voted_for_name']) && !((int) $w['evicted'])): ?>
            <span class="inline-block px-2 py-0.5 text-[11px] font-mono text-stone-500">
              Voted to evict: <?php echo esc_html($w['voted_for_name']); ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if (!empty($w['summary'])): ?>
          <p class="text-sm text-stone-700"><?php echo esc_html($w['summary']); ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
```

- [ ] **Step 4: Wire into single-bigbrother-players.php**

Edit `wp-content/themes/bbj-v2-theme/single-bigbrother-players.php`. Find where the existing "season participation" block is rendered. After it (or wherever appropriate per the existing layout), add:

```php
<?php
foreach ($seasons as $season) {
    get_template_part('template-parts/player/week-by-week', null, [
        'player_post_id' => $post_id,
        'season_post_id' => (int) ($season['post_id'] ?? $season['season_id'] ?? 0),
        'season_label'   => $season['name'] ?? $season['post_title'] ?? '',
    ]);
}
?>
```

(The `$seasons` variable name and structure depends on what `bbj_v2_player_profile_seasons()` returns; consult that function if the keys differ.)

- [ ] **Step 5: Verify on a BB25 player profile**

Browser-load `/bigbrother-players/jag-bains/` (or whatever Jag's slug is). Expected: a "Week by Week — Big Brother 25" section appears, listing each of the 17 weeks with comp badges where Jag won.

- [ ] **Step 6: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/template-parts/player/week-by-week.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/single-bigbrother-players.php"
```

- [ ] **Step 7: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/weekly-tracker-data.php \
        wp-content/themes/bbj-v2-theme/template-parts/player/week-by-week.php \
        wp-content/themes/bbj-v2-theme/single-bigbrother-players.php
git commit -m "feat(weekly-tracker): player profile Week-by-Week display block"
```

---

### Task 12: Wire week summary into season profile

**Files:**
- Modify: `wp-content/themes/bbj-v2-theme/inc/season-profile-data.php`
- Modify: `wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php`

- [ ] **Step 1: Define expected behavior**

The season profile gets a new "Week-by-week" rail that lists each week's `summary` text from `wp_bbj_weeks` (when populated). Existing evictions / comps tables on the season profile are unchanged.

- [ ] **Step 2: Read the existing season profile data helper**

```
grep -n "function bbj_v2_season_profile" wp-content/themes/bbj-v2-theme/inc/season-profile-data.php
```
Note the existing function names so we know where to append.

- [ ] **Step 3: Add a new helper that joins week summary**

In `inc/season-profile-data.php`, append:

```php
/**
 * Returns weeks for the season with editor-written summary text.
 * Filters to weeks that have a non-empty summary.
 */
function bbj_v2_season_profile_week_summaries(int $season_post_id): array
{
    $cache_key = 'season_profile_week_summaries_' . $season_post_id;
    $cached = wp_cache_get($cache_key, 'bbj_v2');
    if (is_array($cached)) {
        return $cached;
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, week_num, start_date, end_date, summary
           FROM {$wpdb->prefix}bbj_weeks
          WHERE season_id = %d
            AND summary IS NOT NULL AND summary <> ''
          ORDER BY week_num ASC",
        $season_post_id
    ), ARRAY_A) ?: [];

    wp_cache_set($cache_key, $rows, 'bbj_v2', HOUR_IN_SECONDS);
    return $rows;
}
```

- [ ] **Step 4: Render in single-bigbrother-seasons.php**

Edit `single-bigbrother-seasons.php`. Find a sensible spot (likely near the existing evictions table). Add:

```php
<?php $week_summaries = bbj_v2_season_profile_week_summaries((int) $post_id); ?>
<?php if (!empty($week_summaries)): ?>
<section class="season-week-recap">
  <h2 class="font-osw text-2xl text-primary-500 mb-3">Week-by-week recap</h2>
  <div class="space-y-3">
    <?php foreach ($week_summaries as $w): ?>
      <article class="border border-stone-200 bg-white p-4 rounded">
        <header class="flex items-baseline justify-between gap-3 mb-1">
          <h3 class="font-osw text-base text-stone-900">Week <?php echo (int) $w['week_num']; ?></h3>
          <span class="font-mono text-[11px] text-stone-500"><?php echo esc_html($w['start_date']); ?> – <?php echo esc_html($w['end_date']); ?></span>
        </header>
        <p class="text-sm text-stone-700"><?php echo esc_html($w['summary']); ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
```

- [ ] **Step 5: Add cache buster for the new key**

Edit `inc/template-functions.php`. Find `bbj_v2_bust_weekly_caches`. Add this line inside the function:

```php
    wp_cache_delete('season_profile_week_summaries_' . $season_id, 'bbj_v2');
```

- [ ] **Step 6: Verify**

Browser-load `/bigbrother-seasons/big-brother-25/` (or any season). If no weeks have summaries yet, no section renders (expected). Add a summary on a Week 1 of any season via the admin Weeks tab → reload the season profile → the recap section should appear.

- [ ] **Step 7: PHP lint**

```
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/season-profile-data.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php"
php -l "C:/xampp/htdocs/bbj/wp-content/themes/bbj-v2-theme/inc/template-functions.php"
```

- [ ] **Step 8: Commit**

```
git add wp-content/themes/bbj-v2-theme/inc/season-profile-data.php \
        wp-content/themes/bbj-v2-theme/single-bigbrother-seasons.php \
        wp-content/themes/bbj-v2-theme/inc/template-functions.php
git commit -m "feat(weekly-tracker): season profile week-by-week recap rail"
```

---

## Phase 5 — Roadmap update + housekeeping

### Task 13: Update roadmap + admin pane to reflect new sprint

**Files:**
- Modify: `.claude/project/roadmap.md`
- Modify: `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-roadmap.php`

- [ ] **Step 1: Define expected behavior**

The roadmap doc and admin pane gain a new entry: "Sprint R — Weekly Tracker (shipped 2026-XX-XX)" listing the new admin tabs + display layer. The Comp Types pane is added to the admin tabs table; the Weeks tab is added to the seasons admin entry.

- [ ] **Step 2: Update roadmap.md**

Edit `.claude/project/roadmap.md`. In the "What's shipped" section, add an entry for the weekly tracker. In the page coverage audit's Admin tabs table, add a `comp-types` row. Update the seasons row to note the new Weeks sub-tab.

(Detailed roadmap text is pure prose — write a 1-paragraph entry mirroring how Sprint A / Sprint D entries are structured. Today's date for the shipped date.)

- [ ] **Step 3: Update pane-roadmap.php**

Edit `wp-content/themes/bbj-v2-theme/template-parts/admin/pane-roadmap.php`. In the `Admin tabs` section's rows array, add:

```php
['?tab=comp-types',       'Comp Types',              'shipped',     null, ''],
```

In the `Sprint coverage` `$sprints` array, add:

```php
['key' => 'R', 'name' => 'Sprint R',
 'subtitle' => 'Weekly Tracker — junction tables + admin form + Week-by-Week display',
 'pages' => [[$adm,'?tab=comp-types']]],
```

Update the seasons row to note the new Weeks sub-tab in its notes column.

- [ ] **Step 4: Verify**

Browser-load `/admin/?tab=roadmap`. Expected: new Sprint R card appears in Sprint Coverage at the bottom. Comp Types row appears in the Admin tabs table.

- [ ] **Step 5: Commit**

```
git add .claude/project/roadmap.md \
        wp-content/themes/bbj-v2-theme/template-parts/admin/pane-roadmap.php
git commit -m "docs(roadmap): Sprint R — Weekly Tracker shipped"
```

---

### Task 14: Update memory — new state

**Files:**
- Create: `C:/Users/sbeli/.claude/projects/C--xampp-htdocs-bbj/memory/project_weekly_tracker_state.md`
- Modify: `C:/Users/sbeli/.claude/projects/C--xampp-htdocs-bbj/memory/project_bbj_v2_theme_state.md`
- Modify: `C:/Users/sbeli/.claude/projects/C--xampp-htdocs-bbj/memory/MEMORY.md`

- [ ] **Step 1: Define expected behavior**

A new project memory captures the weekly tracker's shipped state — schema additions, where the admin lives, where the display layer lives, what's deferred. The `bbj_v2_theme_state` memory is updated to mention Sprint R. The MEMORY.md index gets the new entry.

- [ ] **Step 2: Write the new memory**

Create the file with the standard frontmatter:

```markdown
---
name: Weekly Tracker State
description: Shipped 2026-04-25. Junction-based per-player-per-week tracker drives Week-by-Week on player profiles + recap rail on season profiles. Admin under /admin?tab=seasons&edit=ID#weeks + /admin?tab=comp-types.
type: project
---

# Weekly Tracker (shipped 2026-04-25)

Spec: `docs/superpowers/specs/2026-04-25-weekly-tracker-design.md`
Plan: `docs/superpowers/plans/2026-04-25-weekly-tracker.md`

## Tables
- `wp_bbj_comp_types` — admin-managed comp categories (slug, name, sort, archive flag)
- `wp_bbj_week_comps` — junction (week_id, player_id, comp_type_id, opponents_count NULL, notes NULL)
- `wp_bbj_weeks_players` (modified) — added `saved_by_player_id BIGINT NULL`. Booleans `hoh / pov / misc_comp / saved` deprecated; not written by new code.
- `wp_bbj_weeks` (modified) — added `summary TEXT NULL`.

## Admin
- `/admin?tab=comp-types` — CRUD pane for comp categories.
- `/admin?tab=seasons&edit=<id>#weeks` — Weeks tab w/ week picker pills, Add Week button, per-week edit grid (rows = active players, columns = comps won / nom / saved by / evict / voted to evict). Save button is write-through; partial saves expected.

## Display
- Player profile (`single-bigbrother-players.php`) — Week-by-Week section per season the player participated in, via `template-parts/player/week-by-week.php`. Career totals helper `bbj_v2_player_career_totals()` reads junction-first with legacy column fallback.
- Season profile (`single-bigbrother-seasons.php`) — Week-by-week recap rail driven by `wp_bbj_weeks.summary`.
- Player archive cards — already use the new career-totals helper.

## Deferred
- Drop deprecated columns (boolean comp flags + bbj_total_*) once BB1-21 backfill catches up.
- "Freeze spoiler bar → snapshot week" shortcut button.
- Weighted ranking formula (separate spec when ready).
- Public REST endpoints for the new tables.

## Why this matters
Owner can now log BB27 weekly data going forward + incrementally backfill BB1-21 in their own time. The reader logic gracefully handles partial coverage.
```

- [ ] **Step 3: Append to project_bbj_v2_theme_state.md**

Edit `project_bbj_v2_theme_state.md`. In the "What's shipped" or analogous section, add:

```markdown
- **Sprint R — Weekly Tracker** (2026-04-25): junction-based tracker for who-won-which-comp + who-saved-whom, admin form on the season editor, comp-types CRUD pane, Week-by-Week block on player profiles, week-recap rail on season profiles. See `project_weekly_tracker_state.md`.
```

- [ ] **Step 4: Update MEMORY.md index**

Append to `MEMORY.md`:

```markdown
- [Weekly Tracker State](project_weekly_tracker_state.md) — Shipped 2026-04-25. Junction tables drive per-week comp/save tracking + admin grid editor.
```

- [ ] **Step 5: Commit (memory is gitignored — no push, just save)**

Memory is in the user's `~/.claude` dir, not committed to the repo. No git command needed; the files are saved.

---

## Self-review notes

**Spec coverage check:**
- Schema additions (sections in spec) → Task 1 ✓
- Admin Comp Types pane → Tasks 4–5 ✓
- Admin Weeks tab → Tasks 6–8 ✓
- Display: career totals helper → Task 9 ✓
- Display: archive integration → Task 10 ✓
- Display: player Week-by-Week → Task 11 ✓
- Display: season recap rail → Task 12 ✓
- Migration / backfill SQL → Task 1 ✓
- Cache strategy → Task 7 ✓
- Roadmap + memory update → Tasks 13–14 ✓
- BB1-21 fallback path → covered by Task 9's junction-or-legacy logic ✓
- Deferred items (drop columns, freeze button, ranking formula) → spec lists, plan acknowledges ✓

**Type / signature consistency:**
- `bbj_v2_player_career_totals` returns `['hoh','pov','misc','noms','votes_received','season_count']` — used identically in Tasks 9, 10
- `bbj_v2_save_week_comp` signature `(week_id, player_id, comp_type_id, ?opponents, ?notes)` — used in Tasks 7, 8 ✓
- `bbj_v2_save_week_player` array shape `{week_id, player_id, season_id, nom, evicted, voted_for, saved_by_player_id, active}` — used identically in Tasks 7, 8 ✓
- Cache keys all start with `bbj_v2_` prefix and group `bbj_v2` ✓

**Placeholder scan:**
- No "TBD" / "TODO" / "fill in later" outside the explicit "deferred" list (which IS the spec's stated scope cap)
- Each step that touches code shows the actual code
- Each verification step shows the actual command and the expected result

---

## Plan handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-25-weekly-tracker.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
