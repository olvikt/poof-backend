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
- `scripts/show-release.sh` shows:
  - `Current release` (`release_ref`, `commit`, `deployed_at_utc`, `deployment_type`);
  - `Previous known-good release`;
  - `Recent release transitions` (deploy vs rollback trail);
  - `Merged state gap (informational)` with `Merged PRs ahead of confirmed production`.

## Operator proof: merged history vs deployed history

Use this sequence on the production host (or on a host with production release-state files mounted):

1. `bash scripts/show-release.sh`  
   Confirms exact current production ref/commit and whether merged history is ahead.
2. `MERGED_HEAD_REF=origin/main bash scripts/show-release.sh`  
   Repeats the same check with explicit merged baseline.
3. `bash scripts/check-server.sh`  
   Confirms runtime evidence file has events for the exact deploy window (`deployed_at_utc` + `commit`).

Interpretation:
- If `Ahead commits: 0`, confirmed production state equals merged head baseline.
- If `Ahead commits: N (>0)`, commits/PRs in `Merged PRs ahead of confirmed production` are merged but **not proven deployed** yet.
- If `deployment_type=rollback`, treat current state as rollback transition even if commit overlaps prior deploy trail.

## Anti-patterns (forbidden)

- Tagging `main` directly for production without release branch stabilization.
- Deploying production candidate without explicit ref.
- Closing release without post-deploy smoke evidence.
