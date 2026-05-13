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
- Scheduled matching applies anti-loop controls:
  - per-courier re-offer cooldown (`courier_runtime.scheduled_matching.courier_reoffer_cooldown_seconds`, default 120s);
  - per-courier max attempts per order (`courier_runtime.scheduled_matching.max_attempts_per_courier`, default 2).
If guard blocks all interested couriers, fallback dispatch is used.

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

## PR4 UX lifecycle additions (2026-05-12)

- Available orders payload now carries reservation UX semantics for scheduled orders: `is_scheduled`, `is_future_visible`, `reservation_stage`, `reservation_stage_label`, scheduled window fields, dispatch timing fields, interest flags, countdown fields and helper copy.
- `primary_cta` and `primary_cta_label` are derived server-side (`express_interest`, `withdraw_interest`, `accept_offer`, `view_assigned_order`).
- Countdown is authoritative on backend: `seconds_remaining`, `countdown_active`, `countdown_started_at`, `countdown_expires_at`.
- Before assignment, exact pickup address is hidden for scheduled reservations in API payload.
- Reservation semantics include user-facing outcomes: `selected_elsewhere` and `expired`.
- Notification foundation introduced as `ScheduledCourierNotificationService` with event-ready methods:
  - `notifyScheduledOrderVisible()`
  - `notifyFinalOffer()`
  - `notifyOfferExpiringSoon()`
  - `notifyReservationLost()`
- Operational notification preferences are separated from marketing and include `push_notifications_orders_enabled` (nullable, default `true`).
- UX-level observability events added:
  - `scheduled_order_interest_expressed`
  - `scheduled_order_interest_withdrawn`
  - `scheduled_offer_viewed`
  - `scheduled_offer_countdown_started`
  - `scheduled_offer_countdown_expired`

### Why order can be visible before assignment

Scheduled orders are surfaced early to collect courier intent and improve matching quality near dispatch time. Interest is **not** assignment guarantee.

### Selected elsewhere explanation

If another courier accepts the final TTL offer first, non-selected couriers should receive `selected_elsewhere` semantics and explanatory helper copy.

## Payload backward compatibility guarantees

- Existing top-level response shape of `GET /api/orders/available` is preserved (`orders`, `pagination`).
- Existing canonical fields are preserved and unchanged in meaning (`offer_id`, `order_public_id`, `pickup`, `delivery`, `price`, `offer_status`, `offer_expires_at`, `seconds_remaining`, `service`).
- Newly introduced fields are additive and optional for clients to consume.
- Unknown fields can be safely ignored by legacy courier clients without behavior regression.
- No new sensitive fields are exposed in this contract (no exact coordinates, no client phone, no internal numeric order id in payload).


## Reliability guard
- Interested courier eligibility includes reliability fallback guard based on courier rating (`couriers.rating >= courier_runtime.scheduled_matching.min_reliable_rating`, default `4.0`).
- TODO: replace rating fallback with dedicated runtime reliability KPI once domain metric is persisted for dispatch-grade filtering.
