# release-20260328-ops-hotfix-22

- Harden ops release checks and degraded fallback behavior after rollback/manual state recovery
- Record rollback runtime evidence and enforce non-empty tag release summaries
- Prefer Redis for cache/queue defaults and reduce SQLite lock contention
