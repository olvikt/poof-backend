# Courier Runtime / Read / Dispatch Release Checklist

## 1) Hot query inventory (manual verification list)

> In this environment EXPLAIN was not run against production-like data. Use this checklist on server before release.

### Q1. Canonical runtime reconcile read
- **Query shape:** active order check by courier (`orders` with `courier_id` + status accepted/in_progress).
- **Called from:** `User::repairCourierRuntimeState()` → snapshot + status transitions.
- **Potential frequency:** high (every runtime read path).
- **Why hot:** repeated per render/API snapshot.
- **Expected index:** `orders_courier_status_accepted_idx`.
- **Server check:** `EXPLAIN` on active-order lookup; verify index usage and rows examined.

### Q2. Available orders pending offers read
- **Query shape:** `orders JOIN order_offers` filtered by courier + pending + non-expired.
- **Called from:** `AvailableOrders::render()`, `GET /api/orders/available` (after canonical runtime gate).
- **Potential frequency:** high under polling/navigation.
- **Why hot:** UI render path for active couriers.
- **Expected index:** `order_offers_courier_status_expires_idx` (+ PK lookup to orders).
- **Server check:** verify join order, filtered rows, temporary/sort usage.

### Q3. My orders active list
- **Query shape:** `orders` by courier + active statuses.
- **Called from:** `MyOrders::render()`.
- **Potential frequency:** medium/high.
- **Why hot:** user-visible page refreshes.
- **Expected index:** `orders_courier_status_accepted_idx`.
- **Server check:** ensure no full scan for active courier workloads.

### Q4. Dispatch queue scan
- **Query shape:** searching unpaid filtered queue with `next_dispatch_at` gate.
- **Called from:** `OfferDispatcher::dispatchSearchingOrders()`.
- **Potential frequency:** scheduler + location-triggered dispatch.
- **Why hot:** central dispatch loop.
- **Expected indexes:** `orders_dispatch_queue_idx`, `orders_dispatch_validity_idx`.
- **Server check:** verify range pruning on `next_dispatch_at` and low rows scanned per batch.

### Q5. Candidate courier selection
- **Query shape:** `users JOIN couriers` online+active+fresh location, excluding couriers with active orders.
- **Called from:** `OfferDispatcher::fetchCandidates()`.
- **Potential frequency:** every dispatch attempt.
- **Why hot:** high cardinality under courier growth.
- **Expected indexes:** `users_role_active_idx`, `couriers_status_last_location_idx`, `orders_courier_status_accepted_idx` (for NOT EXISTS).
- **Server check:** EXPLAIN correlated NOT EXISTS cost; verify bounded rows before PHP scoring.

### Q6. Live pending offer existence check
- **Query shape:** `order_offers` by order + pending + expires_at > now.
- **Called from:** `OfferDispatcher::hasLivePendingOffer()`.
- **Potential frequency:** every dispatch attempt.
- **Why hot:** dispatch dedupe/invariant guard.
- **Expected index:** `order_offers_order_status_expires_idx`.
- **Server check:** index-only / low-row existence check.

## 2) Required logs and metrics markers

### Logs that should exist on hot path
- `dispatch_started`
- `dispatch_no_candidates`
- `dispatch_no_pick`
- `dispatch_offer_created`
- `dispatch_deferred`
- `dispatch_waiting_live_offer`
- `dispatch_queue_batch_processed`
- `dispatch_queue_noop_ratio_observed`
- `available_orders_render`
- `my_orders_render`
- `courier_dispatch_triggered_from_location_update`
- `optimistic_runtime_state_overridden`

### Metrics/SLO candidates to monitor
- Dispatch latency (p50/p95/p99) from `dispatch_started` → `dispatch_offer_created|dispatch_no_*`.
- No-candidate rate (`dispatch_no_candidates` / dispatch attempts).
- Offer expiry rate (expired pending offers / created offers).
- Stale location rate (courier filtered by stale `last_location_at`).
- Render/read latency for `available_orders_render` and `my_orders_render`.
- Runtime drift signal rate (`optimistic_runtime_state_overridden`, cross-tab repair markers).

## 3) Release gates

### Must-pass before production
1. Courier feature test suite (runtime consistency + dispatch semantics).
2. Manual EXPLAIN checklist for Q1–Q6 on prod-like data.
3. Smoke checks for cross-component consistency:
   - `OnlineToggle`
   - `AvailableOrders`
   - `MyOrders`
4. Verify location updates still trigger dispatch progression under load simulation.

### Rollback strategy
1. Rollback app deploy to previous build.
2. No schema rollback required for this PR (no migration changes).
3. Validate post-rollback:
   - toggle online/offline works;
   - pending offers visible/accept flow works;
   - dispatch loop continues creating offers.

## 4) Manual server checks (mandatory)

### SQL checks
- Run EXPLAIN ANALYZE (or engine equivalent) for Q1–Q6.
- Capture: chosen index, rows examined, filtered %, actual time.
- Confirm no unexpected filesort/temp table on core read paths.

### Runtime checks
- Multi-tab courier session: optimistic hint cannot persist against backend canonical state.
- Active-order conflict: courier cannot become false offline/free while active order exists.
- Offer lifecycle: pending → accepted/declined/expired invariants remain stable.

### Load checks
- Burst 100+ location updates from test couriers and inspect dispatch throughput.
- Validate no-candidate and deferred rates stay within expected bounds.
- Confirm render latency on courier pages remains stable under concurrent sessions.
