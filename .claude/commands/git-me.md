# Git Commit and Push

Perform a full git commit and push workflow:

1. Run `git status` to see all changes (staged, unstaged, untracked)
2. Run `git diff` to understand what changed
3. Stage the files for this commit by explicit path — NOT `git add -A`. The `.gitignore` blocks the obvious sensitive stuff (DB dumps, separate projects, backup archives), but explicit-path staging is the safety belt. If something untracked looks unfamiliar (a `.sql`, a stray PDF, a folder that wasn't there yesterday), pause and ask before staging it.
4. Generate a clear, concise commit message based on the actual changes
5. Commit with the generated message
6. Push to origin
7. Confirm success with final `git status`

If there are no changes to commit, let me know and skip the process.
