# Courier Scheduled Order Interest / Soft Reservation Flow

## Lifecycle
CREATED → VISIBLE_FOR_RESERVATION → SOFT_INTERESTED → FINAL_MATCHING → OFFERED → HARD_ASSIGNED → IN_PROGRESS → DONE.

## "Готовий виконати"
Courier expresses **soft interest** only. This does not assign the order and does not guarantee selection.

## Visibility and privacy
For future scheduled orders, courier sees area/approximate location, payout, and planned window. Exact address is revealed only after hard assignment.

## Final matching
`courier:finalize-scheduled-order-matching` runs each minute and selects candidates ~30 minutes before `window_from_at`. Interested couriers are checked first; if no eligible interested courier is found, system falls back to standard `OfferDispatcher`.

## Offer TTL
Personal offer TTL is server-side 45 seconds. API returns `offer_expires_at` and `seconds_remaining`.

## "Чекати довше"
Client can choose flexible matching extension (allow window extension) so order can continue searching in the next window. Without this option, order follows auto-cancel policy.

## Telegram consent
Courier profile supports separate consent flags:
- `telegram_notifications_orders_enabled`
- `telegram_notifications_marketing_enabled`

Telegram is additional/fallback channel, not primary.
