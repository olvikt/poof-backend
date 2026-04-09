# Release 2026-04-09 — bounded stale-safe cache for courier profile widgets

## What is cached
- Non-critical courier cabinet read blocks only:
  - `profile_identity`, `profile_contact`, `profile_address`, `profile_media`, `profile_verification`
  - `rating_summary`
  - `balance_summary` (ledger summary + payout policy overlay)
- Cache keys use explicit namespace per courier/widget:
  - `courier:{id}:profile:{widget}`

## What is intentionally NOT cached
- Canonical runtime truth and hot runtime paths remain exact/uncached:
  - `courierRuntimeSnapshot`
  - `CourierPresenceService::{snapshot,canonicalOnline,resolveActiveOrder}`
  - `AvailableOrders`
  - `MyOrders` hot runtime pane
  - `LocationTracker`
  - dispatch triggers and candidate selection

## Staleness bounds (TTL)
Configured in `config/courier_profile_cache.php` (override via env):
- `profile_identity/profile_contact/profile_address/profile_media`: 300s
- `profile_verification/rating_summary`: 120s
- `balance_summary`: 60s

## Invalidation rules
- Profile update (`PersistCourierProfileAction`):
  - invalidate identity/contact/address/verification blocks.
- Avatar update (`PersistCourierAvatarAction`):
  - invalidate media block.
- Withdrawal request create (`CreateCourierWithdrawalRequestAction`):
  - invalidate balance/payout eligibility block.
- Rating invalidation on order/offer lifecycle is not added in this phase; short TTL is used instead.

## Failure behavior and degraded safety
- Cache failures are non-fatal.
- On cache get/put error the read model logs fallback marker and computes directly from canonical services.
- Page rendering remains available even if cache store is unavailable.

## Observability
- Added structured cache markers:
  - `courier_profile_cache_hit`
  - `courier_profile_cache_miss`
  - `courier_profile_cache_fallback`
- Marker dimensions:
  - `widget`
  - `courier_id`
  - `cache_key_group` = `courier_profile_widgets`

## Expected effect
- Repeated profile page opens should reduce repeated aggregate reads for rating and ledger-derived balance blocks, lowering DB/CPU pressure on non-critical cabinet traffic.
- Runtime correctness paths are isolated and unchanged.

## Rollback
1. Fast disable: set `COURIER_PROFILE_CACHE_ENABLED=false` and redeploy config/cache.
2. Full rollback: revert this release commit and redeploy.
