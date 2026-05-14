# release-20260514-scheduled-visibility-telegram-fix

## Summary
- Fixed courier pre-window visibility for scheduled orders in `/api/orders/available` when no final offer exists yet.
- Added planned reservation payload with `express_interest` CTA before final matching.
- Enabled scheduled matching command to trigger Telegram notification fan-out for Telegram-linked couriers (deduplicated).
- Hardened scheduled matching window query to include same-day legacy scheduled date+time path.

## Impact
- One-time scheduled and subscription execution orders now share the same visibility semantics.
- Couriers can express interest before final matching starts.
- Final matching still prioritizes interested couriers and uses fallback dispatch if needed.

## Risk
- Medium: broader scheduled reservation list in available API for couriers.
- Medium: additional Telegram send attempts at T-30 (still deduplicated).

## Rollback
- Revert this release commit if unexpected courier feed volume or notification load appears.
