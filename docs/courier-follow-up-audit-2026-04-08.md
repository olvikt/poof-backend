# Courier module follow-up audit (2026-04-08)

## Scope
- Audit focus: runtime truth boundaries, courier-facing read paths, dispatch trigger fan-out, hot DB paths.
- Baseline: post-hardening state after unified `CourierPresenceService`, canonical runtime snapshot path, alive pending offers API/read model, adaptive polling and two-phase dispatch queue selection.

---

## 1) Current state (audit)

### A. Runtime state model

#### Canonical source of truth
1. `couriers.status` (`offline|online|assigned|delivering`) is the canonical runtime status.
2. Active order state (`orders.status in accepted|in_progress` for `courier_id`) is canonical busy/assignment truth.
3. Canonical snapshot (`User::courierRuntimeSnapshot()` via `CourierRuntimeSnapshot::fromUser`) derives `online|busy|session_state|has_active_order|active_order_status` from (1)+(2), with repair-on-drift behavior.

#### Derived compatibility mirrors
- `users.is_online`, `users.is_busy`, `users.session_state` are projections maintained by `User::syncRuntimeFlagsFromCourierState()`.
- Mirrors are kept for compatibility/debug/legacy consumers but should not be business truth.

#### Legacy dependency residue (audit result)
- App/runtime business decisions in courier module are **not** gated by raw `users.is_online / is_busy / session_state` reads.
- Residual raw mirror reads are limited to diagnostics payload (`CourierPresenceService::snapshotState`) and projection sync internals in `User` model.

#### UI-only optimistic state
- `AvailableOrders::$online` optimistic hint is bounded by TTL and overwritten by canonical snapshot on read.
- Cross-tab/browser runtime sync remains non-authoritative.

### B. Read-path inventory

| Read path | Canonical contract | SQL footprint (steady-state) | Reread/repair behavior | Pressure / risk | Simplification status |
|---|---|---:|---|---|---|
| `AvailableOrders::render()` | `presence->snapshot()` + alive pending offers scope | ~2 hot queries (runtime active-order lookup + offers list) | Canonical snapshot may do repair write when drift detected | High polling pressure when online | Kept canonical, reduced duplicate auth/snapshot path calls via presence-level caching |
| `MyOrders::render()` | canonical online from snapshot; active list by courier/status | ~2-3 queries (+ stats aggregate) | snapshot repair possible; no redundant runtime rereads | Medium polling pressure | Courier resolve path unified through presence service |
| `OfferCard` (load/render) | canonical online + active-order bit from snapshot | 1 snapshot + 1 pending-offer fetch | no extra repair writes beyond snapshot | Fast poll interval path | unchanged semantics; benefits from presence cache |
| `LocationTracker::mount/render` | snapshot-driven runtime-sync event | 1 snapshot + location writes per heartbeat | snapshot repair possible | heartbeat-driven | render now uses same canonical authenticated courier resolver |
| `GET /api/orders/available` | **now canonical runtime has_active_order bit from snapshot** | 1 snapshot + offers read | no custom ad-hoc runtime check | API poll/refresh pressure | removed separate active-order read shape (single canonical runtime gate) |
| `GET /api/courier/runtime` | direct canonical runtime snapshot | 1 snapshot | canonical repair | explicit runtime polling endpoint | unchanged |

Notes:
- N+1 in core courier runtime read paths is not observed in current implementation.
- Completed stats / earnings in `MyOrders` still add aggregate cost; this remains a non-runtime bottleneck.

### C. Dispatch / trigger flow

Trigger sources reviewed:
- order created
- scheduler
- location update
- order completed

Findings:
- Trigger coalescing exists in `DispatchTriggerPolicy` for scheduler/location/order-completed.
- Skip reasons are explicit (`scheduler_queue_hot`, `location_update_cooldown`, `movement_below_threshold`, `courier_offline`, etc.).
- Residual blind spot before this PR: queue-level noop ratio not emitted explicitly (selected queue items vs offers created).

### D. Hot DB paths

Checked query shapes:
1. Active-order lookup by courier/status.
2. Available orders pending offers read (`order_offers` + `orders` validity filters).
3. My orders active list by courier/status.
4. Dispatch queue selection (`searching`, `next_dispatch_at` windows).
5. Candidate selection (`users + couriers` + busy exclusion + bbox).
6. Live pending offer existence check.
7. TTL/cleanup sweeps (pending offer sweeper path).

New query-shape drift after follow-up patch:
- No new heavy SQL shape introduced.
- One read-path simplification: `/api/orders/available` now consumes canonical snapshot `has_active_order` instead of custom ad-hoc active-order existence query branch.

---

## 2) Implemented simplifications (justified)

### S1. Unified authenticated courier/snapshot resolver with request-local caching
**Change:** `CourierPresenceService` now memoizes authenticated courier, runtime snapshot, and active order per courier id.

**Why this simplifies:**
- Removes repeated resolver/snapshot code paths across components.
- Prevents duplicate canonical snapshot calls inside the same request lifecycle.

**Cost impact:**
- DB: fewer repeated selects in same request.
- CPU/memory: negligible array cache per request.
- Network: none.

### S2. `/api/orders/available` switched to canonical runtime gate
**Change:** active-order gate now uses canonical snapshot `has_active_order` instead of parallel ad-hoc `orders.exists()` logic.

**Why this simplifies:**
- Keeps a single runtime-read contract for courier availability decisions.
- Reduces risk of legacy semantic drift between API and Livewire surfaces.

**Risk reduced:**
- Hidden return to legacy decision semantics.
- Contract divergence under future runtime model evolution.

### S3. Dispatch observability enrichment for queue noop pressure
**Change:** added structured markers:
- `dispatch_queue_batch_processed`
- `dispatch_queue_noop_ratio_observed`

Includes: `selected_orders`, `offers_created`, `noop_attempts`, `noop_ratio`, timing marker, counters.

**Why this simplifies operations:**
- Directly shows fan-out inefficiency and noop pressure without reconstructing from multiple logs.
- Makes scheduler/location trigger tuning and cooldown diagnosis faster.

---

## 3) Expected production impact

### Under load growth
- Better stability of courier runtime semantics through stricter canonical gating reuse.
- Lower redundant per-request read pressure where components invoked duplicate resolver/snapshot flows.
- Faster incident triage for dispatch inefficiency due to explicit noop-ratio telemetry.

### Under errors/drift
- Canonical repair path remains unchanged (same safety behavior).
- If drift appears, existing repair telemetry + new queue markers give quicker root-cause correlation.

### Cost model
- **DB:** slight reduction in duplicate runtime reads in request scope; no extra heavy joins.
- **CPU:** minimal overhead for in-memory memoization and extra log context.
- **Memory:** tiny request-local arrays.
- **Network/logging:** +2 structured dispatch markers per queue run (controlled, low payload).

---

## 4) What remains risky (after this PR)

1. Snapshot repair still executes on read path by design; high polling can still amplify canonical reconcile query load.
2. `MyOrders` still mixes runtime and earnings/stats rendering in one component render cycle.
3. Candidate exclusion diagnostics scan can be expensive under very high courier cardinality (bounded, but still linear over sampled rows).
4. Cross-tab optimistic hints remain non-version-strict payloads (operationally acceptable, but not strongly typed).

---

## 5) Monitoring plan

Track:
- `dispatch_queue_batch_processed` / `dispatch_queue_noop_ratio_observed` ratios.
- Existing trigger allowed/skipped counters by reason/source.
- Runtime drift markers (`courier_runtime_repair_write`, `optimistic_runtime_state_overridden`).
- Render timing markers (`available_orders_render`, `my_orders_render`).

Alert heuristics:
- noop ratio sustained high for scheduler batches.
- increasing `movement_below_threshold` + high location update volume.
- rising runtime repair writes per courier session.

---

## 6) Rollback notes

Rollback simplicity:
- No schema changes.
- Revert service/controller/logging patchset only.

Operational rollback steps:
1. Revert to previous app revision.
2. Clear optimized caches (`php artisan optimize:clear`).
3. Restart queue workers.
4. Re-check `/api/orders/available`, `courier/runtime`, and dispatch scheduler logs.

---

## 7) Release/operator checklist delta

Before merge/release:
1. Run courier API + dispatch feature tests.
2. Verify EXPLAIN on hot paths from `docs/courier-runtime-release-checklist.md`.
3. Confirm presence of new dispatch markers in staging logs.
4. Compare baseline p95 for available/my-orders render endpoints before/after.

Post-release watch window (first 1–2 hours):
- monitor noop ratio and trigger skip reasons,
- monitor runtime repair write rate,
- monitor dispatch created offers per scheduler batch.

---

## 8) Final DoD summary

### What was simplified
- Canonical runtime gate reuse in available-orders API.
- Consolidated request-level courier runtime resolver/cache.
- Explicit queue noop observability markers.

### What remains risky
- Read-time repair pressure under extreme polling.
- Combined runtime+earnings render load in `MyOrders`.
- Candidate diagnostics scan cost at higher scale.

### What to do next before scaling courier traffic
1. Split `MyOrders` runtime pane from earnings/stats pane into separate read-model calls.
2. Add per-endpoint runtime snapshot call counters and p95 timers at middleware level.
3. Consider bounded stale-safe cache for non-critical cabinet widgets (profile/rating/balance) fully isolated from runtime truth path.
