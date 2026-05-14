# Courier scheduled order interest flow

## Pre-window visibility
- `/api/orders/available` now returns scheduled reservations even when there is no pending `order_offer` yet.
- Reservation payload is independent from final offer lifecycle:
  - `offer_id = null`
  - `reservation_stage = visible_for_reservation` (or `interested`)
  - `primary_cta = express_interest` (or `withdraw_interest`)
- This behavior is identical for one-time scheduled orders and subscription execution orders.

## T-30 scheduled finalization
At `window_from_at - lead_minutes` (default 30 minutes):
1. Command `courier:finalize-scheduled-order-matching` selects paid/searching/unassigned scheduled orders.
2. Telegram notification `scheduled_order_visible` is sent to Telegram-linked courier accounts with order notifications enabled.
3. Interested couriers are prioritized if still eligible (online, free, reliable).
4. Personal pending offer is created with configured TTL (default 45 seconds).
5. If no interested courier is eligible, fallback dispatch pipeline is used.

## Idempotency & dedup
- Telegram notifications are deduplicated by cache key:
  `scheduled_notification:{event}:{order_id}:{courier_id}`.
- Duplicate pending offers for the same order are prevented by lock + blocking-offer checks before creating a new pending offer.

## Diagnostics
Use:
- `php artisan courier:diagnose-searching-orders`
- `php artisan poof:diagnose-dispatch {order_id}`
- `php artisan courier:why-courier-not-candidate {orderId} {courierId}`

These commands should be used to explain skipped visibility, missing interest CTA, skipped Telegram delivery, and deferred/skipped final matching reasons.
