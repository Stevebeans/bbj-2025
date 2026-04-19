# Push Staging + Deploy

Commit any pending work on `staging`, push to origin, then rsync the theme
and `bigbrotherjunkies-data` plugin to the staging server at Cloudways.

I work directly on `staging` (no feature branches, no worktrees — see the
`feedback_no_worktrees` memory). This command assumes that.

## Pre-flight

1. **Branch check.** Run `git branch --show-current`. If it's not `staging`,
   STOP and ask me what I want to do (maybe I accidentally wandered off
   branch, or I want to cherry-pick something before pushing).

2. **Uncommitted changes check.** Run `git status --short`.
   - If the only modified / untracked entries are noise I've been carrying
     for a while (e.g. `.claude/settings.local.json`, stray backup files,
     design bundles under `.claude/claude-design/`, etc.), IGNORE them —
     do not stage or commit them.
   - If there are new or modified files that look like this sprint's work
     (anything under `wp-content/themes/bbj-v2-theme/`,
     `wp-content/plugins/bigbrotherjunkies-data/`, `docs/superpowers/`,
     etc.), PAUSE and ask me:
     - Which files to include in a commit
     - A commit message (or suggest one based on the diff)
     Never run `git add -A` or `git add .` — it would pick up the pre-existing
     untracked noise. Stage files by name only.
   - If I say "nothing to commit, just push," skip the commit step.

## Push to origin

3. **Fast-forward pull.** `git pull --ff-only origin staging`. If it fails
   because origin is ahead in a non-ff way, STOP and tell me — I'll
   resolve manually.
4. **Push.** `git push origin staging`. Report the commit range that was
   published (e.g. "pushed 14 commits, X..Y").

## Deploy to staging server

Both the theme and the `bigbrotherjunkies-data` plugin live in this
(bbj) repo. Run each script sequentially; stop on failure of either.

5. **Plugin deploy:**
   ```bash
   bash .claude/scripts/deploy-plugin.sh --staging
   ```
6. **Theme deploy:**
   ```bash
   bash .claude/scripts/deploy-theme.sh --staging
   ```
   (Runs `npm run build` in the theme dir first so the server gets the
   compiled Tailwind output — no node toolchain on the server.)

Both scripts push via SSH alias `bbj-staging` to Cloudways at
`/home/1358704.cloudwaysapps.com/ftgtnduhbt/public_html/wp-content/`.

## After deploy

7. Report that staging is live at **https://stg-wp.bigbrotherjunkies.com/**
   and remind me I may need to clear Breeze / Varnish / Redis cache via
   the Cloudways dashboard or WP admin if changes don't show immediately.

## When to skip the deploys

If this push is docs-only (e.g. only `docs/`, `*.md`, or `.github/`
changed since the last origin push), skip steps 5-6 and just confirm the
git push. If unsure, list what's changed and ask me.
