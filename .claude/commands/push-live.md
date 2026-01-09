# Push Staging to Live

Merge staging into master and push to production:

1. Confirm with me: "Are you sure you want to push to LIVE?" - wait for confirmation
2. Switch to staging branch and pull latest
3. Switch to master branch and pull latest
4. Merge staging into master
5. Run `npm run build` in all 4 locations (run these in parallel):
   - `wp-content/themes/BBJ`
   - `wp-content/plugins/bbj-v2`
   - `wp-content/plugins/bbj-tools`
   - `wp-content/plugins/bigbrotherjunkies-data`
6. If any build fails, stop and report the error
7. Commit the build artifacts with message "Build assets for production"
8. Push master to origin
9. Switch back to staging branch

If there are merge conflicts, stop and let me know so I can resolve them.

This pushes to PRODUCTION - always confirm before proceeding.
