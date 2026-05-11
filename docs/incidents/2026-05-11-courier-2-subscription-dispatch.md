# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Environment date requested: `2026-05-11`
- Courier: `2`

## Execution result
The diagnose command is still **not executable** in this container because Laravel bootstrap fails before app start.

Observed sequence:
1. `php artisan --version` fails with missing `vendor/autoload.php`.
2. Runtime restoration via Composer is blocked by outbound proxy/network policy.
3. No prebuilt `vendor/` artifact was found in workspace.

Saved raw execution status:
- `docs/incidents/2026-05-11-courier-2-subscription-dispatch-output.json`

## Runtime bootstrap blocker
Exact blocker evidence (from this environment):
- Command: `php artisan --version`
  - Error: `Failed opening required '/workspace/poof-backend/vendor/autoload.php'`
- Command: `composer diagnose`
  - `curl error 56 while downloading https://getcomposer.org/versions: CONNECT tunnel failed, response 403`
  - `curl error 56 while downloading https://repo.packagist.org/packages.json: CONNECT tunnel failed, response 403`
  - `curl error 56 while downloading https://api.github.com/rate_limit: CONNECT tunnel failed, response 403`
- Composer cache check:
  - cache dir `/root/.cache/composer` exists but size is only `16K`, insufficient for offline install.
- Local artifact check:
  - no `vendor` archive found under `/workspace` search scope.

Blocking dependency sources / hosts:
- `repo.packagist.org`
- `api.github.com`
- `getcomposer.org`

Proxy response observed:
- `CONNECT tunnel failed, response 403`

## Final incident verdict
- **Root cause code:** `blocked_by_runtime_bootstrap`
- **Final verdict:** Incident is **not closed**. Business/root-cause diagnosis for courier visibility on `2026-05-11` cannot be computed in this environment until runtime bootstrap is restored.

## Required counters (active/today, dispatchable, deferred, stuck_without_offers)
Unavailable from this run due to bootstrap failure.

## Why courier_id=2 did not see orders
Not determinable from this run: domain diagnostic JSON (`orders`, `summary`, `queue`, `timezone`) was not produced because artisan command never reached application runtime.

## Fix vs repair vs operational action
- Application-code fix for dispatch logic: **not evidenced** by this run.
- Repair in current environment: **blocked** by dependency/bootstrap constraints.
- Required operational action (infra):
  1. Provide CI/build artifact containing `vendor/` matching current `composer.lock`, **or**
  2. Run diagnose inside Docker/runtime image where dependencies are already installed, **or**
  3. Allow proxy egress to `repo.packagist.org`, `api.github.com`, `getcomposer.org`, **or**
  4. Configure/use approved Composer mirror (Satis/Private Packagist), **or**
  5. Provide `COMPOSER_AUTH` / GitHub token if the policy allows and auth/rate is the limiting factor.

## Next mandatory step to close incident
After infra unblocks bootstrap, rerun:
- `php artisan --version`
- `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`

Do **not** mark incident closed until command returns diagnostics on real data.

## Runtime recovery runbook
- See: `docs/runtime-bootstrap-recovery.md`
- Next required action: restore runtime using one supported path, then re-run diagnose.
