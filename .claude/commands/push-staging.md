# Merge Feature to Staging

Merge current feature branch into staging and push:

1. Check current branch - if on `staging` or `master`, stop and warn me
2. Run `git status` to check for uncommitted changes - if any exist, ask if I want to commit them first
3. Get the current feature branch name (save it for later)
4. Switch to staging branch
5. Pull latest staging from origin
6. Merge the feature branch into staging
7. Push staging to origin
8. Ask if I want to delete the feature branch (local and remote)

If there are merge conflicts, stop and let me know so I can resolve them.
