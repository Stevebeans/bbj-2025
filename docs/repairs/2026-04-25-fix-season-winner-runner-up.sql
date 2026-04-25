-- =============================================================================
-- Repair: wp_bbj_seasons.season_winner / runner_up
-- Date: 2026-04-25
-- Context: see memory/references/bbj_data_schema.md ("season_winner / runner_up
-- / afp are unreliable").
--
-- Why this exists:
--   wp_bbj_seasons.season_winner and runner_up have never been consistently
--   maintained. Most seasons are NULL; some (BB24) point at the wrong player;
--   some (BB26) point at post-revision IDs ('inherit' status) instead of the
--   real player post.
--   The junction table wp_bbj_v2_player_season.finish_place is the de facto
--   truth — it's maintained by the spoiler bar editor.
--
-- What this script does:
--   Copies the finish_place=1 player into season_winner, finish_place=2 into
--   runner_up. Idempotent (skips rows already correct, repairs both NULL and
--   wrong values). Leaves AFP alone — there's no junction-table source for it.
--
-- How to run:
--   - Local:    mysql -u root bbj_db < docs/repairs/2026-04-25-fix-season-winner-runner-up.sql
--   - Staging:  same, with staging credentials + DB name (ftgtnduhbt)
--   - Prod:     same, with prod credentials + DB name (duesaptjae)
--   Or paste the body into phpMyAdmin and run as-is.
--
-- When to re-run:
--   After every finale (the column goes stale once a new winner gets
--   finish_place=1 in the spoiler bar editor). Until / unless we add a
--   save-hook that updates these columns whenever finish_place changes.
-- =============================================================================

-- Snapshot before
SELECT 'BEFORE' AS stage,
       SUM(CASE WHEN season_winner IS NULL OR season_winner = 0 THEN 1 ELSE 0 END) AS winner_unset,
       SUM(CASE WHEN runner_up     IS NULL OR runner_up     = 0 THEN 1 ELSE 0 END) AS runner_unset
  FROM wp_bbj_seasons;

-- Repair winners (finish_place = 1)
UPDATE wp_bbj_seasons s
INNER JOIN (
  SELECT j.bbj_season AS season_post_id, j.bbj_player AS player_id
    FROM wp_bbj_v2_player_season j
    INNER JOIN wp_posts wp ON wp.ID = j.bbj_player AND wp.post_status = 'publish'
   WHERE j.finish_place = 1
) truth
   ON (s.post_id = truth.season_post_id OR (s.id = truth.season_post_id AND s.post_id = 0))
  SET s.season_winner = truth.player_id
WHERE s.season_winner IS NULL
   OR s.season_winner = 0
   OR s.season_winner != truth.player_id;

-- Repair runners-up (finish_place = 2)
UPDATE wp_bbj_seasons s
INNER JOIN (
  SELECT j.bbj_season AS season_post_id, j.bbj_player AS player_id
    FROM wp_bbj_v2_player_season j
    INNER JOIN wp_posts wp ON wp.ID = j.bbj_player AND wp.post_status = 'publish'
   WHERE j.finish_place = 2
) truth
   ON (s.post_id = truth.season_post_id OR (s.id = truth.season_post_id AND s.post_id = 0))
  SET s.runner_up = truth.player_id
WHERE s.runner_up IS NULL
   OR s.runner_up = 0
   OR s.runner_up != truth.player_id;

-- Snapshot after — winner_unset should equal the number of in-progress seasons
-- (typically 1 = BB27 until the finale). runner_unset will also include
-- seasons whose junction has no finish_place=2 row (BB8, BB10 historically).
SELECT 'AFTER' AS stage,
       SUM(CASE WHEN season_winner IS NULL OR season_winner = 0 THEN 1 ELSE 0 END) AS winner_unset,
       SUM(CASE WHEN runner_up     IS NULL OR runner_up     = 0 THEN 1 ELSE 0 END) AS runner_unset
  FROM wp_bbj_seasons;
