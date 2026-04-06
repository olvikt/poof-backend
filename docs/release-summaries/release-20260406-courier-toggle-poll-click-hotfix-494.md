# release-20260406-courier-toggle-poll-click-hotfix-494

- PR #494
- P0 hotfix: stabilize courier online toggle against poll/click contention
- Prevent toggle flicker/revert by separating poll sync from click path and removing render-time overwrite
