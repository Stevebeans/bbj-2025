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

-- ----- BEFORE snapshot (run is idempotent — counts already at AFTER state on re-run) -----
SELECT 'BEFORE' AS stage,
       (SELECT COUNT(*) FROM wp_bbj_comp_types)        AS comp_types_count,
       (SELECT COUNT(*) FROM wp_bbj_week_comps)        AS week_comps_count,
       (SELECT COUNT(*) FROM wp_bbj_weeks_players
         WHERE saved_by_player_id IS NOT NULL)         AS saved_by_filled;

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
