-- =============================================================================
-- Repair: rename BB27 post slug from `bb27` to `big-brother-27`
-- Date: 2026-04-25
--
-- Why:
--   BB1-BB21 use abbreviation slugs (`bb1`-`bb21`) — legacy import.
--   BB22-BB26 use full slugs (`big-brother-22`-`big-brother-26`) — modern admin.
--   BB27 was created with the abbreviation slug, breaking the modern pattern.
--   Renaming so /bigbrother-seasons/big-brother-27/ matches the rest.
--
-- WP's wp_old_slug_redirect will 301 /bigbrother-seasons/bb27/ to the new URL
-- automatically — no manual redirect entry needed.
--
-- How to run:
--   - Local:    mysql -u root bbj_db < docs/repairs/2026-04-25-bb27-slug-rename.sql
--   - Staging:  same with staging credentials + DB name (ftgtnduhbt)
--   - Prod:     same with prod credentials + DB name (duesaptjae)
--
-- The post ID is local-only (67594). Staging/prod will have different IDs —
-- the WHERE filters by post_type + post_name to be safe across environments.
-- =============================================================================

UPDATE wp_posts
   SET post_name = 'big-brother-27'
 WHERE post_type = 'bigbrother-seasons'
   AND post_name = 'bb27'
   AND post_title = 'Big Brother 27'
   AND post_status = 'publish';

-- Verify
SELECT ID, post_title, post_name
  FROM wp_posts
 WHERE post_type = 'bigbrother-seasons'
   AND post_title = 'Big Brother 27';
