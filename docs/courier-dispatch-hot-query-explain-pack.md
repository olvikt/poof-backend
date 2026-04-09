# Courier dispatch hot-query EXPLAIN pack (PR #532)

## Purpose

Evidence-first production verification for DB/perf hardening of courier dispatch hot path.  
Scope: Q1–Q6 queries used by runtime/read/dispatch code paths, without changing canonical runtime truth model.

## How to run on server

1. Use prod-like dataset (same index set, similar cardinality/tenancy skew).
2. Run:
   - MySQL 8+: `EXPLAIN ANALYZE ...`
   - or `EXPLAIN FORMAT=JSON ...` when ANALYZE is unavailable.
3. Capture and persist for each query:
   - chosen index / join order,
   - rows examined / filtered,
   - temporary/filesort flags,
   - actual time (when available).
4. Repeat for:
   - busy window (high online courier pool),
   - low-traffic window.

## Q1. Canonical runtime active-order lookup

- **Code source:** `CourierRuntimeStateResolver::resolveForUser()` via `takenOrders()->activeForCourier()->value('status')`.
- **Steady-state frequency:** every canonical runtime snapshot read (`AvailableOrders`, `MyOrders`, runtime API).

```sql
EXPLAIN ANALYZE
SELECT status
FROM orders
WHERE courier_id = :courier_id
  AND status IN ('accepted', 'in_progress')
ORDER BY CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END
LIMIT 1;
```

**Good signal**
- Uses `orders_courier_status_accepted_idx` (or equivalent leading `courier_id,status` index).
- Rows examined bounded by active orders per courier (typically very low).
- No temp table explosion.

## Q2. Available offers read

- **Code source:** `OrderOffer::alivePendingForCourierOrders()` used by `AvailableOrders::render()` and `/api/orders/available`.
- **Steady-state frequency:** high polling path when courier is online.

```sql
EXPLAIN ANALYZE
SELECT DISTINCT o.*
FROM order_offers oo
JOIN orders o ON o.id = oo.order_id
WHERE oo.courier_id = :courier_id
  AND oo.status = 'pending'
  AND oo.expires_at > NOW()
  AND o.expired_at IS NULL
  AND (o.valid_until_at IS NULL OR o.valid_until_at > NOW())
ORDER BY oo.created_at DESC;
```

**Good signal**
- Driver table `order_offers` via `order_offers_courier_status_expires_idx`.
- PK lookup into `orders` by `o.id`.
- No full scan over `orders`.

## Q3. My orders active list

- **Code source:** `MyOrders::render()` active pane.
- **Steady-state frequency:** medium/high polling path.

```sql
EXPLAIN ANALYZE
SELECT o.*
FROM orders o
WHERE o.courier_id = :courier_id
  AND o.status IN ('accepted', 'in_progress')
ORDER BY o.accepted_at ASC;
```

**Good signal**
- Uses `orders_courier_status_accepted_idx`.
- Bounded rows per courier.
- No filesort regressions under expected active-order counts.

## Q4. Dispatch queue scan (two-phase)

- **Code source:** `OfferDispatcher::dispatchQueueSelection()`.
- **Steady-state frequency:** scheduler ticks + trigger-driven queue batches.

```sql
-- Phase A: deferred due now
EXPLAIN ANALYZE
SELECT id
FROM orders
WHERE status = 'searching'
  AND courier_id IS NULL
  AND payment_status = 'paid'
  AND expired_at IS NULL
  AND (valid_until_at IS NULL OR valid_until_at > NOW())
  AND next_dispatch_at IS NOT NULL
  AND next_dispatch_at <= NOW()
ORDER BY next_dispatch_at ASC, id ASC
LIMIT :batch_limit;

-- Phase B: brand-new not deferred
EXPLAIN ANALYZE
SELECT id
FROM orders
WHERE status = 'searching'
  AND courier_id IS NULL
  AND payment_status = 'paid'
  AND expired_at IS NULL
  AND (valid_until_at IS NULL OR valid_until_at > NOW())
  AND next_dispatch_at IS NULL
ORDER BY created_at ASC, id ASC
LIMIT :remaining_limit;
```

**Good signal**
- Uses `orders_dispatch_queue_idx`/`orders_dispatch_validity_idx`.
- Predictable low rows scanned per batch.
- No `COALESCE(next_dispatch_at, created_at)` fallback sort.

## Q5. Candidate courier selection

- **Code source:** `OfferDispatcher::fetchCandidates()`.
- **Steady-state frequency:** every dispatch attempt.

```sql
EXPLAIN ANALYZE
SELECT u.id, u.last_lat, u.last_lng, u.last_completed_at, u.last_offer_at
FROM users u
JOIN couriers c ON c.user_id = u.id
WHERE u.role = 'courier'
  AND u.is_active = 1
  AND u.last_lat IS NOT NULL
  AND u.last_lng IS NOT NULL
  AND c.status = 'online'
  AND c.last_location_at > NOW() - INTERVAL :fresh_seconds SECOND
  AND NOT EXISTS (
    SELECT 1
    FROM orders o
    WHERE o.courier_id = u.id
      AND o.status IN ('accepted', 'in_progress')
  )
  AND u.last_lat BETWEEN :lat_min AND :lat_max
  AND u.last_lng BETWEEN :lng_min AND :lng_max
LIMIT :max_couriers_to_scan;
```

**Good signal**
- Uses `users_role_active_idx`, `couriers_status_last_location_idx`, `orders_courier_status_accepted_idx`.
- Correlated `NOT EXISTS` remains index-backed (no heavy nested-loop blowup).
- Candidate rows before PHP scoring remain bounded by `max_couriers_to_scan`.
- Temporary/filesort acceptable only if rowset is already tightly bounded.

**Bounded-candidate budget (operational target)**
- `candidate_scan_count` should be stable and well below hard cap in normal hours.
- Alert on persistent near-cap scans (suggests need for tighter coarse prefilter/index tuning).

## Q6. Live pending offer existence check

- **Code source:** `OfferDispatcher::hasLivePendingOffer()`.
- **Steady-state frequency:** every dispatch attempt before candidate work.

```sql
EXPLAIN ANALYZE
SELECT 1
FROM order_offers
WHERE order_id = :order_id
  AND status = 'pending'
  AND expires_at > NOW()
LIMIT 1;
```

**Good signal**
- Uses `order_offers_order_status_expires_idx`.
- Existence probe returns in low single-row cost.

## Post-deploy watch window (first 24h)

Track:
- `dispatch_candidates_evaluated`:
  - `candidate_scan_count` p50/p95/p99,
  - `elapsed_ms` p50/p95/p99,
  - split by `trigger_source`.
- Outcome markers with latency:
  - `dispatch_offer_created`,
  - `dispatch_no_candidates`,
  - `dispatch_no_pick`,
  - `dispatch_deferred`,
  - `dispatch_waiting_live_offer`,
  - `dispatch_skipped_deferred_under_lock`.

Rollback trigger examples:
- sustained p95/p99 regression in `elapsed_ms` for dispatch outcomes,
- candidate scans persistently at/near cap with no matching conversion improvement,
- query plan drift to full scans on Q4/Q5.

