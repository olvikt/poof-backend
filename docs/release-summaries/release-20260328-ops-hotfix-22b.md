# release-20260328-ops-hotfix-22b

- Harden ops release checks and degraded fallback behavior without reintroducing broken UI changes
- Record rollback runtime evidence and enforce non-empty tag release summaries
- Prefer Redis for cache/queue defaults and reduce SQLite lock contention
