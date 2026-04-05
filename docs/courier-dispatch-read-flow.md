# Courier dispatch/read flow (hot path)

## Scope

This document describes the canonical hot path after dispatch/read simplification:

- dispatch write path (`OfferDispatcher`)
- courier available offers read path (`AvailableOrders`)
- courier active orders read path (`MyOrders`)

## Canonical sources

- **Available offers:** alive `order_offers` rows (`status=pending`, `expires_at > now`) joined to `orders`.
- **Active courier orders:** `orders` where `courier_id = courier` and `status in (accepted, in_progress)`.

## Dispatch selection stages

1. `dispatch_started` marker
2. lock order row (`FOR UPDATE`) and validate dispatchable contract (`searching`, no courier)
3. expire dead pending offers for the order
4. fetch candidate couriers via compact query projection (no full Eloquent graph)
5. pick courier in PHP scoring stage (distance -> idle -> rotation)
6. create pending offer (`OrderOffer::createPrimaryPending`)
7. update winner `last_offer_at`
8. emit `dispatch_offer_created` marker

### What stays in SQL vs what stays in PHP

**SQL (intentional):**
- candidate eligibility filters (online, active map location, not busy, no alive pending duplicate)
- bounding-box coarse geo filter
- searching orders queue (`searching`, `paid`, no courier)

**PHP (intentional):**
- final distance math (haversine)
- fairness/rotation tie-break (idle/last_offer)

This keeps domain-specific scoring deterministic while reducing database + Eloquent overhead.

## Hot queries and boundaries

### Dispatcher hot queries

1. lock + validate order
2. expire stale pending for current order
3. candidate projection query (users + couriers + `NOT EXISTS` orders/offers)
4. create pending offer
5. update `users.last_offer_at`

### Courier cabinet reads

- `AvailableOrders`: join `orders` + `order_offers` by courier with alive pending filter.
- `MyOrders`: active orders by courier, plus eager `client:id,phone` to avoid per-card client lookup.

## Performance notes and measurable markers

Structured markers:

- `dispatch_started`
- `dispatch_no_candidates`
- `dispatch_no_pick`
- `dispatch_offer_created`
- `available_orders_render`
- `my_orders_render`

Common fields:
- `order_id`
- `candidate_count`
- `picked_courier_id`
- `elapsed_ms`
- `pending_offer_count` / `active_order_count`

## Indexes for hot flow

### Existing/confirmed

- `order_offers(courier_id, status, expires_at)`
- `order_offers(order_id, status, expires_at)`
- `orders(status, courier_id, payment_status)`
- `orders(courier_id, status, accepted_at)`
- `couriers(status)`
- `couriers(last_location_at)`

### Explain focus

For production MySQL/PostgreSQL, run `EXPLAIN` for:

1. candidate query (`users + couriers + NOT EXISTS`)
2. available offers query (`orders JOIN order_offers` with alive pending filter)
3. my-orders query (`orders by courier + active status`)

Expected behavior: no full scan on `order_offers` for alive-pending probes and predictable index range scans on `orders`/`couriers` status filters.

## Operator checklist

Watch in production:

- rise in `dispatch_no_candidates` with normal online courier pool
- p95/99 `elapsed_ms` for `dispatch_offer_created`
- mismatch between `available_orders_render.pending_offer_count` and offer table reality
- spikes of `my_orders_render.elapsed_ms` (possible overfetch/N+1 regressions)
