# Commit-level RCA: historical runtime regression after PR #435

## 1) Executive summary

- **Confirmed root cause commit (historical introduction): `fb8f5fa`** (`Harden ops release checks and reduce SQLite lock contention`), specifically the `config/cache.php` change from `env('CACHE_STORE', 'database')` to `env('CACHE_STORE', env('CACHE_DRIVER', 'redis'))`. This reintroduced legacy `CACHE_DRIVER` influence into `cache.default` resolution when `CACHE_STORE` is unset.
- **Confirmed root cause commit on bad deploy line #1 (`84fe3b8`)**: merge parent `d4db6e4` carries the same runtime config diff (identical hunk/content) into the deployed line.
- **Why baseline worked**: before that hunk, runtime semantics did not consult legacy `CACHE_DRIVER` when `CACHE_STORE` was unset (safe Redis-first contract was restored later by `ec67483`).
- **Why bad line broke runtime/UI**: with `CACHE_DRIVER=file` and `CACHE_STORE` unset, cache switched to file/non-Redis path while queue+session remained Redis, creating cross-store mismatch in production bootstrap and amplifying lock/contention behavior under SQLite-sensitive paths.
- **Why PR #435 helped but is not historical proof by itself**: PR #435 (`4061b4e`) adds a production-like regression test and protects current state, but the historical offending diff is still the earlier cache fallback reintroduction (`fb8f5fa` / deployed via `d4db6e4`).

## 2) Commit-level proof

### 2.1 Concrete commits and minimal offending change set

1. `fb8f5fa` introduced the unsafe fallback chain:

```diff
- 'default' => env('CACHE_STORE', 'database'),
+ 'default' => env('CACHE_STORE', env('CACHE_DRIVER', 'redis')),
```

2. `d4db6e4` (parent merged by `84fe3b8`) contains the same config content for:
   - `config/cache.php`
   - `config/queue.php`
   - `config/database.php`

3. `ec67483` removed legacy fallback influence again:

```diff
- 'default' => env('CACHE_STORE', env('CACHE_DRIVER', 'redis')),
+ 'default' => env('CACHE_STORE', 'redis'),
```

4. `4061b4e` (PR #435) added regression protection test that asserts production-like bootstrap resolves to Redis even when `CACHE_DRIVER=file` and `CACHE_STORE` is unset.

### 2.2 Safe vs unsafe runtime resolution path (side-by-side)

- **Unsafe historical path (`fb8f5fa` / `d4db6e4`)**
  - `cache.default = env('CACHE_STORE', env('CACHE_DRIVER', 'redis'))`
  - if `CACHE_STORE` unset and `CACHE_DRIVER=file` -> `cache.default=file`
  - `queue.default` may still be `redis`
  - `session.driver` may still be `redis`
  - result: cache/queue/session semantic split

- **Safe current path (`ec67483` + protected by `4061b4e`)**
  - `cache.default = env('CACHE_STORE', 'redis')`
  - if `CACHE_STORE` unset and `CACHE_DRIVER=file` -> `cache.default=redis`
  - queue/session remain redis-first under same env
  - result: aligned Redis-first runtime semantics

### 2.3 Additional contributing change check (same bad line)

In the same offending commit family (`fb8f5fa` / `d4db6e4`), `config/database.php` also enables SQLite WAL and busy timeout knobs. This is not the trigger for cache semantic drift, but is consistent with a contention-sensitive environment where cross-store mismatch can surface as UI dead/slow behavior under load.

## 3) Why `92924e0` did not fix the app

`92924e0` (PR #426) modifies:
- `scripts/deploy.sh`
- `scripts/rollback.sh`
- `scripts/release-state-lib.sh` (new)
- release-tooling unit tests

Observed scope: atomic writer behavior, release-state and history recording, deploy/rollback script contracts.

No runtime application config files (`config/cache.php`, `config/queue.php`, `config/session.php`, app boot providers) are changed in this merge. Therefore it can fix release bookkeeping/tooling consistency while leaving runtime cache resolution unchanged.

Short form: **tooling fixed, app still broken** because the app regression lived in runtime config semantics, not in deploy state recording.

## 4) Root-cause ranking

### Confirmed root cause
- **Item**: reintroduction of legacy cache fallback semantics (`CACHE_DRIVER` affecting `cache.default` when `CACHE_STORE` unset).
- **Probability**: 0.98
- **Evidence**: direct offending hunk in `fb8f5fa`, same content present in `d4db6e4`, then directly reverted/fixed by `ec67483`, and protected by runtime contract test in `4061b4e`.
- **Follow-up needed**: no mandatory code follow-up for root-cause closure (already fixed + guarded).

### Likely contributing factors
- **Item**: SQLite lock sensitivity under mixed cache/queue/session backends.
- **Probability**: 0.70
- **Evidence**: same commit family introduces SQLite tuning and references lock contention context; mismatch path explains elevated contention symptoms.
- **Follow-up needed**: optional observability issue (lock wait metrics + cache backend invariants in prod checks).

### Guarded but still risky patterns
- **Item**: legacy env variables (`CACHE_DRIVER`) present in deploy envs.
- **Probability**: 0.55 (risk recurrence if guard removed or config cache stale)
- **Evidence**: historical breakage precisely followed legacy env fallback chain.
- **Follow-up needed**: optional lint/gate to fail release if `CACHE_DRIVER` is set without explicit approved mapping.

### Unrelated but dangerous findings
- **Item**: release-state tooling defects addressed by PR #426.
- **Probability as root cause for runtime regression**: 0.02 (effectively not root cause)
- **Evidence**: file-level diffs limited to deploy/rollback scripts + tooling tests.
- **Follow-up needed**: none for runtime RCA; keep existing tooling tests.

## 5) Follow-up recommendation

- **RCA closure status**: can be closed without another runtime code patch.
- Reason: root cause code is already fixed (`ec67483`) and regression-protected (`4061b4e`).
- Optional follow-up issue (non-blocking): **"Add deploy gate for legacy CACHE_DRIVER presence and runtime store invariants"**.

## 6) Verification checklist

### Git/compare steps used
- `git log --all --oneline | rg '84fe3b8|92924e0'`
- `git show --no-patch --pretty=raw 84fe3b8 92924e0`
- `git log --oneline --decorate -- config/cache.php`
- `git show fb8f5fa -- .env.example config/cache.php config/database.php config/queue.php`
- `git diff --name-status 84fe3b8^1 84fe3b8`
- `git diff --name-status 92924e0^1 92924e0`
- `git diff --unified=0 fb8f5fa d4db6e4 -- config/cache.php config/queue.php config/database.php`
- `git show ec67483 -- config/cache.php`
- `git show 4061b4e -- tests/Unit/Release/RuntimeConfigStoreContractTest.php`

### Runtime invariants compared
- production-like env with `CACHE_STORE` unset + `CACHE_DRIVER=file`
- expected/guarded runtime contract:
  - `cache_default=redis`
  - `cache_store_driver=redis`
  - `queue_default=redis`
  - `session_driver=redis`

### Proven conclusions
- Minimal offending config diff identified and linked to bad deploy line.
- `92924e0` proven tooling-layer only, not runtime-layer.
- Current main has both fix and regression test guard.

### Not proven in this repo snapshot
- Exact object `50f2c943...` was not resolvable in local git object set; proof used reachable lineage (`fb8f5fa` -> `d4db6e4` -> `84fe3b8`) and current fix/test commits.
