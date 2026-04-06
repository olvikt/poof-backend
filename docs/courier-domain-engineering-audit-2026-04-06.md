# Courier-domain engineering audit (production-oriented)

Date: 2026-04-06  
Repo: `olvikt/poof-backend`

## 1. Executive summary

Courier-flow is **operationally improved** (alive pending offers for AvailableOrders, active-only MyOrders, bounded dispatch backoff, deterministic queue ordering, key dispatch/render markers), but there are still high-probability production risks from **runtime state duplication**, **high-frequency polling load**, and **non-canonical API entry points**.

### Main verdict
- Dispatch core has good protective mechanics (transaction lock, pending-offer guard, bounded backoff).
- Runtime model still has 5 sources of state (`couriers.status`, active `orders`, `users.is_online`, `users.is_busy`, `users.session_state`) and relies on frequent self-heal writes.
- Read/load pattern is still polling-heavy and will grow DB QPS superlinearly with active couriers.
- There is at least one stale API path (`/api/orders/available`) that bypasses canonical offer-based read semantics.

### P0 production risks
1. **State drift + repair-write amplification** from duplicated runtime flags.
2. **Polling storm** (`offer-card` 2s + `my-orders` 5s + `available-orders` 10s per courier session).
3. **Dispatch trigger fan-out** (scheduler + location-triggered dispatch loops touching same queue).
4. **Potential slow queue scan / sort** due to `COALESCE(next_dispatch_at, created_at)` ordering shape.
5. **Operator blind spots**: missing reason-coded diagnostics for candidate exclusion/dispatch misses.

---

## 2. Current architecture map

### 2.1 Online/offline lifecycle
- UI: `OnlineToggle` calls `CourierPresenceService::toggleOnline()`.
- Service reads snapshot, blocks offline transition if active order exists, then calls `User::goOnline/goOffline`.
- `User::transitionCourierState()` enforces order-aware status and synchronizes mirrored fields (`users.is_online/is_busy/session_state`).

### 2.2 Location update flow
- Browser geolocation watcher emits `courier-location` into Livewire.
- `LocationTracker::updateLocation()` validates coordinates + accuracy, conditionally triggers `dispatchSearchingOrders()` when moved enough and cooldown passed, updates user coordinates and `couriers.last_location_at`.

### 2.3 Dispatch trigger flow
- Triggers:
  1) `OrderCreated` listener → `OfferDispatcher::dispatchForOrder()`.
  2) Scheduler every 5s → `dispatchSearchingOrders(20)`.
  3) Courier location update (if online + moved + cooldown) → `dispatchSearchingOrders()`.
  4) Order completion in `MyOrders::complete()` → `dispatchSearchingOrders()`.
- Queue eligibility is backoff-aware (`next_dispatch_at` gate).

### 2.4 Offer lifecycle
- Create pending offer in dispatch transaction (`OrderOffer::createPrimaryPending`).
- Expire dead pending offers before new attempt.
- Accept via canonical order accept action (`OrderOffer::acceptBy` → `Order::acceptBy` action).
- Decline path marks offer declined.

### 2.5 Available orders read model
- Livewire `AvailableOrders` reads from `orders JOIN order_offers` restricted to alive pending offers for authenticated courier + validity checks.

### 2.6 My orders read model
- Livewire `MyOrders` reads only `orders` where `courier_id=<self>` and status in (`accepted`, `in_progress`) + client phone eager loading.

### 2.7 Order accept/start/complete lifecycle
- Accept: transaction with `courier lock -> order lock`, busy guard, assign order, mark courier busy, expire other courier pending offers.
- Start: set `in_progress`, mark courier delivering.
- Complete: set `done`, mark courier free, stamp `last_completed_at`, trigger earnings settlement.

### 2.8 busy / online / session transitions
- Canonical projection synthesized by `CourierRuntimeSnapshot::fromUser()`, but underlying state is still physically stored in both `couriers` and `users` and repaired opportunistically.

---

## 3. Canonical vs duplicated state map

### 3.1 Recommended canonical model (current practical interpretation)
- **Assignment/busy truth:** active order existence (`orders.courier_id + status in accepted/in_progress`).
- **Presence truth:** `couriers.status` (+ `couriers.last_location_at` freshness).
- **Mirrors for compatibility:** `users.is_online`, `users.is_busy`, `users.session_state`.

### 3.2 Actual current writes
- Domain actions and transitions write both `couriers.status` and `users.*` mirrors.
- Multiple code paths call `repairCourierRuntimeState()` (read-time self-heal with writes).

### 3.3 Places still dependent on duplicated state or mixed semantics
1. `users.is_online` used implicitly by older flows/tests/seed data as runtime signal.
2. `users.is_busy` asserted and used as compatibility state while canonical busy is active order + courier status.
3. `users.session_state` transported in snapshots though comment says “not used in business logic”, but is still continuously synchronized.
4. API `/api/orders/available` uses `Order::availableForCourier()` (global searching orders) and ignores offer-based read model.

---

## 4. Top architectural risks

### Risk A1 — Runtime state duplication as drift generator
- **Load growth:** write amplification (every transition/snapshot-repair may update multiple columns/tables).
- **Error/race/retry:** stale worker or concurrent transitions can re-overwrite mirrors; read-time repair masks root causes.
- **Monitoring:** currently lacks explicit drift counters (how often repair changed persisted state).
- **Rollback:** easy to rollback if mirror writes are kept as compatibility only.
- **Cost:** extra DB writes + lock contention on `users`/`couriers` under high activity.
- **Simplification:** make `couriers.status + active orders` sole write-source; keep `users.*` as async/compat projection.

### Risk A2 — Mixed read contracts across surfaces
- **Load growth:** API consumers may fetch global searching orders (wrong semantics + larger payload).
- **Failure mode:** courier sees orders not actually offered.
- **Monitoring:** no marker for API contract divergence.
- **Rollback:** fast (switch API endpoint to offer-based query).
- **Cost:** higher read volume, correctness defects.
- **Simplification:** enforce one read contract per use-case (offer-based availability).

### Risk A3 — Dispatch trigger multiplicity
- **Load growth:** scheduler + location dispatch can repeatedly scan queue.
- **Failure/race:** repeated job delivery is mostly safe (locks/backoff) but DB churn increases.
- **Monitoring:** missing metric “dispatch trigger source distribution” and “noop dispatch ratio”.
- **Rollback:** disable location-trigger dispatch via feature flag quickly.
- **Cost:** extra CPU/DB per trigger.
- **Simplification:** centralize trigger policy and coalesce by order id / time window.

---

## 5. Top performance / DB risks

## 5.1 Hot queries (5–10)

1. **Dispatch queue fetch**
   - Source: `OfferDispatcher::dispatchSearchingOrders()`.
   - Frequency: scheduler every 5s + ad hoc triggers.
   - Shape: filter searching+paid+unassigned+valid+backoff eligible, then `ORDER BY COALESCE(next_dispatch_at, created_at), id LIMIT N`.
   - Risk: expression sort may reduce index usage, temp/filesort under large queue.
   - Existing indexes: `orders_dispatch_queue_idx`, `orders_dispatch_validity_idx`.
   - Missing/possible: generated sortable column or dual-order strategy avoiding `COALESCE` expression.

2. **Offer dead-pending expiration (per order dispatch)**
   - Source: `dispatchPrimaryOffer()`.
   - Frequency: each dispatch attempt.
   - Shape: `UPDATE order_offers SET status=expired WHERE order_id=? AND status=pending AND (expires_at IS NULL OR <= now)`.
   - Risk: update hot rows repeatedly under contention.
   - Existing indexes: `order_offers_order_status_expires_idx`.
   - Missing/possible: keep as-is; add low-cost guard metric `expired_rows_per_attempt`.

3. **Live pending existence check**
   - Source: `hasLivePendingOffer()`.
   - Frequency: each dispatch attempt.
   - Risk: cheap with index, but called very often.
   - Existing indexes: `order_offers_order_status_expires_idx`.

4. **Candidate selection query**
   - Source: `fetchCandidates()`.
   - Frequency: each eligible dispatch attempt.
   - Shape: `users JOIN couriers`, role+active filters, location freshness, `NOT EXISTS active orders`, bounding box filters.
   - Risks: missing geo/covering indexes on `users(last_lat,last_lng)` and potentially on `orders(courier_id,status)` (present), high CPU in PHP scoring at scale.
   - Existing indexes: `couriers_status_last_location_idx`, `users_role_active_idx`, `orders_courier_status_accepted_idx`.
   - Missing/possible: composite index on users for candidate projection (`role,is_active,last_lat,last_lng,id`) depending on MySQL planner.

5. **Available orders render query**
   - Source: `AvailableOrders::render()`.
   - Frequency: poll every 10s per active courier page.
   - Shape: `orders JOIN order_offers` by courier pending alive + validity + distinct/order.
   - Risks: repeated polling overhead, potential filesort on `order_offers.created_at`.
   - Existing indexes: `order_offers_courier_status_expires_idx`.
   - Missing/possible: add `(courier_id,status,expires_at,created_at,order_id)` or reorder query to use `order_offers` first and join orders by PK.

6. **My orders render query**
   - Source: `MyOrders::render()`.
   - Frequency: poll every 5s per active courier page.
   - Shape: courier active orders + eager load client phone.
   - Risks: high poll QPS, not query complexity.
   - Existing indexes: `orders_courier_status_accepted_idx`.

7. **Offer card polling query**
   - Source: `OfferCard::loadOffer()`.
   - Frequency: poll every 2s per active courier layout.
   - Shape: pending offer for courier + order validity.
   - Risks: this is likely the hottest read path; heavy overhead at scale.
   - Existing indexes: courier/status/expires index helps.
   - Missing/possible: replace with push/event or adaptive poll intervals.

8. **Active-order conflict subquery for candidates**
   - Source: `NOT EXISTS orders where courier_id=users.id and status in active`.
   - Frequency: each candidate fetch.
   - Risk: correlated subquery pressure at high concurrency.
   - Existing indexes: `orders_courier_status_accepted_idx`.

9. **Stale courier sweep**
   - Source: `MarkInactiveCouriers` every minute.
   - Frequency: periodic batch.
   - Risk: chunk scan + per-row repair writes.
   - Existing indexes: courier status/location indexes.

10. **Order auto-expire batch**
   - Source: `OrderAutoExpireService::run()` every minute.
   - Risk: predictable, index-backed but can spike if large backlog.

## 5.2 Query shape simplifications (safe)
- Rewrite available-orders query as `order_offers -> join orders` (driven by courier pending alive offers), drop `distinct` by ensuring uniqueness at source.
- Replace `COALESCE(next_dispatch_at, created_at)` ordering with two-phase selection:
  1) `next_dispatch_at IS NOT NULL ORDER BY next_dispatch_at,id`
  2) fallback `next_dispatch_at IS NULL ORDER BY created_at,id`
  This improves index friendliness.
- Keep candidate scoring in PHP for now; SQL geospatial scoring is premature unless candidate set per order > O(100).

## 5.3 Need projection/read-model for dispatchable couriers?
- **Current answer:** Not yet mandatory.
- Reason: existing compact candidate query + bounded scan (`maxCouriersToScan=80`) is acceptable short-term.
- Trigger for introducing projection: p95 dispatch attempt latency > target or candidate query CPU becomes dominant in APM/slow-log.

---

## 6. Correctness / concurrency risks

### Race C1 — duplicate dispatch attempts
- Current guard: order row lock + live pending check + `next_dispatch_at`.
- Gap: multiple trigger sources still generate high noop/lock contention.
- Minimal hardening: source-aware coalescing (`DispatchTriggerPolicy`) + noop counters.

### Race C2 — stale pending offers
- Current guard: dead pending auto-expire inside dispatch + offer TTL checks in reads.
- Gap: expiry is opportunistic; old pending may live until next attempt.
- Hardening: cheap periodic `order_offers` TTL sweep (batched update) with metric.

### Race C3 — repeat job delivery / worker restart
- Current guard: transaction + idempotent eligibility checks.
- Gap: no explicit idempotency key metric to detect pathological repeats.
- Hardening: emit attempt UUID/trigger source in logs; alert on excessive retries per order.

### Race C4 — double accept
- Current guard: transactional accept with courier and order locks; tested concurrent cases.
- Gap: lock order consistency exists in accept action, but other flows should avoid inverse lock ordering in future.
- Hardening: architecture test enforcing lock order across lifecycle actions.

### Race C5 — users.* vs couriers.status drift
- Current guard: repair calls in many read/write paths.
- Gap: repair on read hides defects, increases writes.
- Hardening: drift detector metric + gradual migration to single write-source.

### Race C6 — location update vs active-order transition
- Current guard: `updateLocation` checks `isCourierOnline`; `updateLocation()` self-repairs before writing.
- Gap: frequent state flips can still create inconsistent UX windows.
- Hardening: resolve runtime snapshot once per request and avoid multiple recomputations.

### Race C7 — backoff anomalies (`next_dispatch_at` stuck)
- Current guard: next dispatch nullified on offer creation, set on defer.
- Gap: if logic bug sets far-future timestamp, order may starve.
- Hardening: operator alert on searching orders with `next_dispatch_at` older/newer than sane envelope.

### Retry/backoff masking systemic issues
- Current risk: no-candidate due to stale location or offline drift may be repeatedly deferred, appearing “healthy” while customer waits.
- Required: reason taxonomy per defer (`no_candidates:{offline,freshness,busy,geo}`) and SLO alerts.

---

## 7. Simplification opportunities

### S1 — Single write-source for runtime
- Problem solved: drift and self-heal write storms.
- Proposal: write only `couriers.status` + order lifecycle; derive `users.is_online/is_busy/session_state` asynchronously/synchronously as compatibility projection.
- Why simpler: fewer transactional writes and fewer cross-table invariants.

### S2 — Introduce lightweight policy services (not enterprise over-abstraction)
1. `DispatchEligibilityPolicy`
   - Encodes order eligibility + no-live-pending + backoff due checks.
2. `CourierRuntimeStateResolver`
   - Pure resolver for runtime snapshot from canonical sources.
3. `DispatchTriggerPolicy`
   - Coalesces trigger sources, enforces minimal cadence.
4. `LocationIngestService`
   - Single entrypoint for validation + movement threshold + dispatch-trigger signal.

Each should be thin and focused to reduce duplication already spread across Livewire/Model/Dispatcher.

### S3 — Unify read contracts
- Replace stale API available endpoint with offer-based semantics or deprecate.
- Ensure every courier-facing surface uses same “alive pending offers” contract.

### S4 — Poll reduction before big architecture changes
- Adaptive polling (2s only when pending offer expected, otherwise 8–15s).
- Conditional polling stop when offline or active order state makes path irrelevant.
- This is simpler and cheaper than introducing event buses immediately.

---

## 8. Recommended backlog

### P0-1
- **priority:** P0  
- **problem:** Runtime drift from duplicated state + read-time repairs.  
- **proposed change:** Implement `CourierRuntimeStateResolver` as canonical pure read, and narrow writes to canonical sources; keep `users.*` as compatibility projection with explicit sync boundary.  
- **expected production benefit:** fewer drift incidents, lower write amplification, clearer invariants.  
- **risk if postponed:** hidden corruption and intermittent courier availability bugs under load.  
- **implementation complexity:** M  
- **required tests/benchmarks/observability:** drift regression tests, snapshot consistency tests, counter `courier_runtime_drift_repairs_total`.

### P0-2
- **priority:** P0  
- **problem:** Polling QPS explosion (`2s/5s/10s`) and DB read pressure.  
- **proposed change:** adaptive poll intervals + stop polling when offline/no-offer path inactive.  
- **expected production benefit:** immediate DB/CPU relief, better mobile battery/network behavior.  
- **risk if postponed:** read saturation at peak courier concurrency.  
- **implementation complexity:** S  
- **required tests/benchmarks/observability:** request-rate before/after, p95 render time, client session network sample.

### P0-3
- **priority:** P0  
- **problem:** Mixed available-orders contract in API endpoint.  
- **proposed change:** switch `/api/orders/available` to offer-based alive pending semantics or sunset endpoint.  
- **expected production benefit:** consistency across surfaces, fewer wrong offers.  
- **risk if postponed:** correctness regressions for API consumers.  
- **implementation complexity:** S  
- **required tests/benchmarks/observability:** API parity tests with Livewire contract.

### P1-1
- **priority:** P1  
- **problem:** Dispatch queue ordering may degrade due to `COALESCE` sort expression.  
- **proposed change:** two-phase index-friendly selection strategy.  
- **expected production benefit:** better planner usage and stable latency with larger searching queue.  
- **risk if postponed:** filesort/temp overhead under growth.  
- **implementation complexity:** M  
- **required tests/benchmarks/observability:** EXPLAIN before/after, dispatch p95 latency.

### P1-2
- **priority:** P1  
- **problem:** Insufficient diagnostics for why no courier candidates were found.  
- **proposed change:** add exclusion reason counters/log fields (offline, stale location, busy active order, outside bbox, inactive user).  
- **expected production benefit:** fast incident triage and targeted fixes.  
- **risk if postponed:** long MTTR and blind backoff loops.  
- **implementation complexity:** S  
- **required tests/benchmarks/observability:** log contract tests + alert thresholds.

### P1-3
- **priority:** P1  
- **problem:** Opportunistic stale offer expiration may lag.  
- **proposed change:** add minute-level batched pending-offer TTL sweeper.  
- **expected production benefit:** cleaner state and more predictable availability reads.  
- **risk if postponed:** zombie pending artifacts during low dispatch activity.  
- **implementation complexity:** S  
- **required tests/benchmarks/observability:** sweeper idempotency tests, expired-row metrics.

### P2-1
- **priority:** P2  
- **problem:** Candidate query may need extra index coverage at scale.  
- **proposed change:** benchmark and, if needed, add composite index for users candidate projection.  
- **expected production benefit:** lower candidate scan CPU/IO.  
- **risk if postponed:** gradual latency drift with user table growth.  
- **implementation complexity:** M  
- **required tests/benchmarks/observability:** EXPLAIN on prod-like dataset, index hit ratios.

### P2-2
- **priority:** P2  
- **problem:** Trigger source fan-out unclear.  
- **proposed change:** `DispatchTriggerPolicy` + source-level metrics.  
- **expected production benefit:** controlled dispatch cadence, lower noop work.  
- **risk if postponed:** continued hidden overhead.  
- **implementation complexity:** M  
- **required tests/benchmarks/observability:** source counters, noop ratio KPI.

---

## Tasks for immediate implementation

1. Replace `/api/orders/available` query with offer-based alive pending contract + add regression tests.  
2. Add `dispatch_no_candidates` structured reason breakdown (`offline_count`, `stale_location_count`, `busy_count`, `bbox_filtered_count`).  
3. Implement adaptive polling in courier UI: offer-card 2s only when online+no active order; otherwise 8–15s.  
4. Add alert for searching orders stuck with `next_dispatch_at` outside expected envelope (e.g., >10 min in future).  
5. Add minute cron `order_offers` pending TTL sweeper.  
6. Add metric `courier_runtime_repair_writes_total` to quantify drift pressure.  
7. Refactor dispatch queue selection to avoid `COALESCE` sort expression (two-phase strategy).  
8. Add operator endpoint/command: “why order not dispatched” returning last N dispatch attempts and candidate exclusion stats.  
9. Add operator endpoint/command: “why courier not candidate” by courier id + order id with rule-by-rule verdict.

---

## Do not do

1. Do **not** introduce full CQRS/event-sourcing rewrite now.  
2. Do **not** move dispatch scoring into complex SQL geospatial ranking before evidence of bottleneck.  
3. Do **not** add Kafka/Rabbit stream pipeline for runtime sync at this stage.  
4. Do **not** replace Livewire with websocket-only architecture as first step.  
5. Do **not** introduce heavy repository/service layers that only wrap Eloquent without removing real duplication.  
6. Do **not** add more mirrored runtime columns; reduce mirrors instead.

---

## Production observability / release gate checklist

### Must-have metrics
- Dispatch attempts/sec, success ratio, no-candidate ratio, no-pick ratio.
- Dispatch latency p50/p95/p99.
- Candidate set size distribution.
- Deferred orders count and age.
- Searching orders age histogram.
- Offer lifecycle: created/accepted/declined/expired rates.
- Courier runtime drift repairs count.
- Poll-driven request rate per component.

### Existing markers already useful
- `dispatch_started`, `dispatch_deferred`, `dispatch_no_candidates`, `dispatch_no_pick`, `dispatch_offer_created`, `available_orders_render`, `my_orders_render`.

### Missing markers to add now
- Candidate exclusion reason dimensions.
- Dispatch trigger source (`scheduler`, `location_update`, `order_created`, `order_completed`).
- Drift repair action counters (what field corrected, from→to).
- Stuck-searching diagnostics (`next_dispatch_at`, attempts, last reason).

### Operator diagnostics playbooks
- **Order not dispatched:** inspect dispatch attempts timeline, last defer reason, candidate exclusion breakdown.
- **Online courier missing from candidates:** run per-rule eligibility check (role/active/status/freshness/busy/location bbox).
- **Order stuck searching/deferred:** inspect promise validity, `next_dispatch_at`, attempts trend, candidate availability.

### Release-gate before prod
- Slow query log check for top 10 courier queries.
- Load test with realistic poll rates and moving couriers.
- Queue worker health + scheduler heartbeat SLO checks.
- Verify no lock escalation in accept/dispatch critical paths.
- Verify external call retries (payments/geocode) are isolated from dispatch loop.
- Peak scenario: burst of searching orders + sparse online couriers.

