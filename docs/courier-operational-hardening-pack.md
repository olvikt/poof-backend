# Courier operational hardening pack (post-P0)

## Scheduler additions

- `poof-pending-offer-ttl-sweeper` (every minute): expires `order_offers.status=pending` when `expires_at <= now` in bounded batches.

## New operator commands

- `php artisan courier:sweep-pending-offers --limit=200`
- `php artisan courier:diagnose-searching-orders --limit=100`
- `php artisan courier:why-order-not-dispatched {orderId}`
- `php artisan courier:why-courier-not-candidate {orderId} {courierId}`

## Structured markers/contracts

- `pending_offers_expired_batch`
  - `expired_count`
  - `batch_limit`
- `searching_order_stuck_detected`
  - anomaly context + thresholded classification
- `courier_runtime_repair_write`
  - `user_id`
  - `courier_id`
  - `field_changes`
  - `had_active_order`
  - `courier_status`
  - `source_context`
  - counter contract: `courier_runtime_repair_writes_total`

## Notes

- Opportunistic pending-expire inside dispatch remains in place as correctness guard.
- This pack adds cleanup/diagnostics/observability surfaces without runtime architecture rewrite.

## Dispatch diagnostic outcomes for paid subscription execution orders

- Unified dispatch diagnostic reason taxonomy for operator logs and diagnostics:
  - `dispatch_deferred_until`
  - `waiting_live_offer`
  - `no_candidates`
  - `no_pick`
  - `order_promise_expired`
  - `missing_coordinates`
  - `courier_busy`
  - `courier_offline_or_stale`
  - `payment_not_paid`
  - `invalid_status`
- Every paid/searching subscription execution order now has observable dispatch outcome in logs:
  - `offer_created`
  - `offer_not_created` + `reason`
  - `dispatch_skipped` + `reason`
- New operator command:
  - `php artisan poof:diagnose-dispatch {order_id}`
  - returns order state, dispatch gating fields (`next_dispatch_at`, `dispatch_available_at`, payment/status), latest offer snapshot, and candidate exclusion breakdown.
