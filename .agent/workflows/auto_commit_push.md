---
description: Automatically stage, commit, and push changes to the remote repository.
---
// turbo-all

1. Check the current status.
   `git status`

2. Stage all changes.
   `git add .`

3. Commit the changes. (The agent should generate a descriptive commit message based on the changes. **The commit message MUST be in Japanese.**).
   `git commit -m "<message>"`

4. Push the changes to the remote repository (Replace 'main' with the current branch if different).
   `git push origin main`
