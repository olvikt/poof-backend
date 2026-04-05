# Order Promise Layer

## Goal
Separate **business validity of an order** from offer TTL in courier dispatch.

- Offer TTL (`order_offers.expires_at`) controls how long one courier can accept one offer.
- Order promise validity (`orders.valid_until_at`) controls how long the client order stays актуальним in search.

## Model

`orders` now includes:

- `service_mode`: `asap` | `preferred_window`
- `window_from_at` / `window_to_at`
- `valid_until_at`
- `expired_at`, `expired_reason`
- `client_wait_preference`: `auto_cancel_if_not_found` | `allow_late_fulfillment`
- `promise_policy_version`

Terminal state kept as `cancelled` for compatibility. Auto-expire is differentiated by `expired_at != null` and `expired_reason`.

## Semantics

- ASAP:
  - `valid_until_at = created_at(now) + asap_validity_hours`
- Preferred window:
  - `valid_until_at = window_to_at + preferred_window_grace_hours`
  - if wait preference `allow_late_fulfillment`, add `allow_late_extra_hours`
- Auto-expire transitions searching+paid+unassigned orders to `cancelled` once validity passed.

## Transitions

1. Create order → promise fields are derived by `OrderPromiseResolver`.
2. Payment to searching (`MarkOrderAsPaidAction`) ensures promise fields exist for legacy paths.
3. Dispatch query ignores invalid/expired orders.
4. `OrderAutoExpireService` finalizes stale searching orders and expires alive pending offers.

## Scheduler / Ops

- Scheduler hook: `poof-order-auto-expire-loop` every minute.
- Manual command: `php artisan orders:auto-expire --limit=200`.

Structured logs:

- `order_expired`
- `order_expire_skipped`

Recommended markers:

- `expired_reason`
- `order_age_seconds`
- `had_live_pending_offer`
- `active_offer_count`

## Operator checklist

- Verify scheduler heartbeat and `poof-order-auto-expire-loop` runs.
- Track spikes of `expired_reason=client_auto_cancel_policy` (possible courier scarcity).
- Track ratio: expired searching orders / total paid searching orders.
- Verify no pending offers remain for cancelled+expired orders.

## Production watchpoints

- Keep `auto_expire_enabled=true` unless incident mitigation requires temporary pause.
- Tune:
  - `asap_validity_hours`
  - `preferred_window_grace_hours`
  - `allow_late_extra_hours`
- Validate courier urgency threshold UX (`courier_urgency_warning_minutes`) after rollout.
