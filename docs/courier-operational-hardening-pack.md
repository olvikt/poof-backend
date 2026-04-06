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
