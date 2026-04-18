# Merge Feature to Staging + Deploy

Merge the current feature branch into staging, then push to origin AND deploy
the theme + plugin to the staging server.

## Git steps

1. Check current branch — if on `staging` or `master`, stop and warn.
2. `git status` to check for uncommitted changes. If any exist, ask if I want to commit them first.
3. Save the current feature branch name.
4. Switch to `staging` branch.
5. Pull latest `staging` from origin.
6. Merge the feature branch into `staging`.
7. Push `staging` to origin.

If there are merge conflicts, stop and let me know so I can resolve them.

## Staging deploy (runs after git push succeeds)

Both the theme and the `bigbrotherjunkies-data` plugin live in this (bbj)
repo now — no more bbj-app hop. Run:

8. **Plugin deploy:**
   ```bash
   bash .claude/scripts/deploy-plugin.sh --staging
   ```
9. **Theme deploy:**
   ```bash
   bash .claude/scripts/deploy-theme.sh --staging
   ```
   (Builds Tailwind before transferring; excludes `node_modules`, `src/css`,
   `.git`, and Tailwind config files.)

Both scripts push via SSH (`bbj-staging` alias) to Cloudways at
`/home/1358704.cloudwaysapps.com/ftgtnduhbt/public_html/wp-content/`.

## After deploy

10. Remind me that staging is live at **https://stg-wp.bigbrotherjunkies.com/**
    and I may need to clear Breeze/Varnish/Redis cache via the Cloudways
    dashboard or WP admin.
11. Ask if I want to delete the feature branch (local and remote).

## When NOT to deploy

If the feature branch only touched docs, tests, or files that don't affect
runtime (e.g. `docs/`, `.github/`, `*.md`), skip steps 8-10 and just confirm
the git push. Ask me if unsure.
