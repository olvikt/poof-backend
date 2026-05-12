# Scheduled Courier Interest / Soft Reservation

## Scope
- persistence schema `courier_order_interests`;
- courier API endpoints to express and withdraw interest;
- final scheduled matching command with idempotent lock;
- personal offer TTL payload for courier available offers.

## Semantics
- "Готовий виконати" stores courier soft-interest only.
- Soft-interest does **not** hard-assign order.
- Final assignment happens only after courier accepts offer.

## Final matching (PR2)
- Starts `N` minutes before scheduled window (`courier_runtime.scheduled_matching.lead_minutes`, default `30`).
- Command: `php artisan courier:finalize-scheduled-order-matching`.
- Scheduler runs command every minute.
- Selection priority:
  1. interested couriers (`courier_order_interests.status=interested`), sorted by `distance_meters` then `expressed_at`;
  2. fallback to regular `OfferDispatcher` candidate pool when no eligible interested courier is found.
- If interested courier is offline/not eligible at final matching moment, courier is skipped.
- Structured logs emitted:
  - `scheduled_matching_started`
  - `scheduled_matching_order_locked`
  - `scheduled_matching_offer_created`
  - `scheduled_matching_no_eligible_interested_courier`
  - `scheduled_matching_fallback_used`
  - `scheduled_matching_skipped_existing_offer`

## Offer TTL
- Personal offer TTL is `45` seconds by default (`courier_runtime.scheduled_matching.offer_ttl_seconds`).
- Courier offers payload includes:
  - `offer_expires_at`
  - `seconds_remaining` (`max(0, expires_at - now)` on server side).

## Repeated matching behavior after expired offers
- Current behavior is **intentional retry**.
- If pending offer expires and order is still `searching` with no assigned courier, next scheduler tick may create a new offer for same interested courier.
- No explicit cooldown or max-attempts is applied in PR2 for scheduled matching; retry uses existing search constraints and offer-alive guard.

## Scheduler overlap safety
- Scheduler command is configured with `withoutOverlapping(2)` and per-order cache lock `scheduled-final-matching:{order_id}`.
- Matching flow also uses DB transaction with `lockForUpdate()` on order row before duplicate checks and offer creation.
- Duplicate-protection gates are re-checked under lock (`accepted` offer exists OR alive `pending` offer exists) before any new offer is inserted.
- Result: overlap can delay processing, but should not create duplicate alive offers for same order.

## Endpoints
- `POST /api/courier/orders/{order}/interest`
- `DELETE /api/courier/orders/{order}/interest`
- `GET /api/orders/available` (now returns offer TTL metadata)
- `POST /api/orders/offers/{offer}/accept` (canonical hard-assignment endpoint)

Both interest endpoints require authenticated courier.

## Offer accept race handling (PR3)
- Hard assignment is performed **only** inside offer-accept transaction (`POST /api/orders/offers/{offer}/accept`).
- Accept flow runs under one DB transaction and uses `lockForUpdate()` for:
  - courier row;
  - offer row;
  - order row.
- Under lock, accept validates:
  - `order.status=searching`;
  - `order.courier_id IS NULL`;
  - offer belongs to authenticated courier;
  - offer status is `pending`;
  - offer TTL is alive (`expires_at > now`), so `expires_at == now` is treated as expired.
- Assignment write uses conditional update (`status=searching` and `courier_id is null`) to guarantee single winner.
- On successful accept:
  - order moves to `accepted` and receives `courier_id`;
  - selected offer becomes `accepted`;
  - competing pending offers on same order become `expired`;
  - other couriers' interests become `rejected` with `rejected_reason=selected_elsewhere`;
  - selected courier interest becomes `selected` with `selected_at`.
- On race (second courier or stale/expired offer), API returns controlled business JSON (`409`) without fatal exception.
- UX outcome for non-selected couriers: order disappears from available offers and corresponding interest state is `rejected:selected_elsewhere`.
- If same courier retries accept for an already accepted order/offer pair (e.g. client timeout retry), API returns success with `idempotent=true`.

### Accept sequence (transactional)
1. Offer is `pending`.
2. `order_offer_accept_started` log emitted.
3. Transaction starts, lock order: courier row → offer row → order row (deterministic order).
4. Validate ownership/status/TTL/order availability.
5. Assign order (`searching + courier_id is null` conditional write).
6. Mark selected offer as `accepted`.
7. Cleanup same-order competing offers/interests.
8. Commit transaction.
9. Emit terminal log (`order_offer_accept_succeeded` / `order_offer_accept_rejected` / `order_offer_accept_race_lost`).
