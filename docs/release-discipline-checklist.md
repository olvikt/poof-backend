# Release discipline checklist (no direct prod tags from unstable main)

## Branch policy

- `main` — интеграционная ветка, не source of production tags.
- `release/*` — единственный source для production candidate tags.
- Production tag (`release-YYYYMMDD-HHMM`) ставится **только** на commit из `release/*`.

## Canonical flow

1. Cut release branch from green `main`:
   - `git checkout -b release/YYYYMMDD-HHMM origin/main`
2. Run critical regression suite.
3. Freeze branch: только hotfix commits.
4. Create annotated release tag from `release/*`.
5. Deploy explicit tag (`scripts/deploy.sh <tag>`).
6. Validate with `scripts/show-release.sh` + `scripts/check-server.sh`.
7. Rollback only to recorded `previous_release_ref`.

## Required evidence

- `docs/release-summaries/<tag>.md` exists.
- `storage/app/current-release.json` confirms `selection_mode=explicit` and `fallback_used=false`.
- `storage/app/release-history.jsonl` contains successful transition.

## Anti-patterns (forbidden)

- Tagging `main` directly for production without release branch stabilization.
- Deploying production candidate without explicit ref.
- Closing release without post-deploy smoke evidence.
