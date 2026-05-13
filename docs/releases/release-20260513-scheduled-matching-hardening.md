# release-20260513-scheduled-matching-hardening

## Scope
- hardening scheduled final matching for one-time and subscription execution orders;
- reliability guard and anti-loop controls for repeated offers;
- TTL synchronization contract clarification for courier offer UI.

## Changes
- `courier:finalize-scheduled-order-matching` now enforces reliability fallback via courier rating (`couriers.rating >= courier_runtime.scheduled_matching.min_reliable_rating`, default `4.0`).
- Added re-offer protections: cooldown and max attempts per courier per order.
- Expired pending offer no longer immediately re-targets same courier on next tick when cooldown/attempt guard blocks it.
- Subscription execution orders are covered by same command path because they stay in canonical `orders` searching pipeline with scheduled window fields.

## Config
- `courier_runtime.scheduled_matching.courier_reoffer_cooldown_seconds` (default `120`)
- `courier_runtime.scheduled_matching.max_attempts_per_courier` (default `2`)
- `courier_runtime.scheduled_matching.min_reliable_rating` (default `4.0`)

## UI/UX dispatch contract
- Frontend countdown must use backend `offer_expires_at` + server timestamp contract, not local-only timer.
- On expiration or competing acceptance, frontend should close/disable CTA based on refreshed backend state (`reservation_stage`, `countdown_active`, `offer_status`).
