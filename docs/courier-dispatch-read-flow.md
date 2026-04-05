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

1. queue pick from eligible searching orders:
   - `status=searching`
   - `payment_status=paid`
   - `courier_id is null`
   - `next_dispatch_at is null OR next_dispatch_at <= now`
   - ordered by `COALESCE(next_dispatch_at, created_at), id` (deterministic)
2. `dispatch_started` marker
3. lock order row (`FOR UPDATE`) and validate dispatchable contract (`searching`, no courier)
4. persist dispatch attempt state (`last_dispatch_attempt_at`, `dispatch_attempts`)
5. expire dead pending offers for the order
6. fetch candidate couriers via compact query projection (no full Eloquent graph)
7. pick courier in PHP scoring stage (distance -> idle -> rotation)
8. if no candidates/no pick: defer with bounded exponential backoff to `next_dispatch_at`
9. create pending offer (`OrderOffer::createPrimaryPending`) when winner exists
10. update winner `last_offer_at` and clear `next_dispatch_at`
11. emit `dispatch_offer_created` marker

### What stays in SQL vs what stays in PHP

**SQL (intentional):**
- candidate eligibility filters (online, active map location, not busy, no alive pending duplicate)
- bounding-box coarse geo filter
- searching orders queue (`searching`, `paid`, no courier, backoff-aware eligibility)

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
- `dispatch_deferred`
- `dispatch_no_candidates`
- `dispatch_no_pick`
- `dispatch_offer_created`
- `available_orders_render`
- `my_orders_render`

Common fields:
- `order_id`
- `candidate_count`
- `picked_courier_id`
- `dispatch_attempted`
- `dispatch_deferred`
- `dispatch_backoff_until`
- `attempt_count`
- `order_age_seconds`
- `elapsed_ms`
- `pending_offer_count` / `active_order_count`

## Retry/backoff semantics and anti-starvation guarantees

- Every valid dispatch try increments `dispatch_attempts` and updates `last_dispatch_attempt_at`.
- If an order has no candidates (or no winner), it is deferred by bounded exponential backoff to `next_dispatch_at`.
- Deferred orders are temporarily skipped by queue selection, so they cannot monopolize the next batch.
- Backoff is bounded (base 15s, max 180s), so undeliverable orders are retried and do not get lost.
- Queue ordering remains deterministic and cheap (indexed range + stable order), without full random scan.
- New dispatchable orders can pass deferred undeliverable ones in the next scheduler ticks.

## Indexes for hot flow

### Existing/confirmed

- `order_offers(courier_id, status, expires_at)`
- `order_offers(order_id, status, expires_at)`
- `orders(status, courier_id, payment_status)`
- `orders(status, courier_id, payment_status, next_dispatch_at, id)`
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
- rising `attempt_count` + long `order_age_seconds` with repeating `dispatch_deferred` markers
- `dispatch_backoff_until` consistently far in the future for large fraction of searching queue
- p95/99 `elapsed_ms` for `dispatch_offer_created`
- mismatch between `available_orders_render.pending_offer_count` and offer table reality
- spikes of `my_orders_render.elapsed_ms` (possible overfetch/N+1 regressions)
