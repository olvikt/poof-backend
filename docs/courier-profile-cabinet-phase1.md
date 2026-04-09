# Courier profile/cabinet page — Phase 1 architecture

## Scope (P0)
- Dedicated courier page at `/courier/profile`.
- Isolated compact read models for profile, rating, and balance/payout eligibility.
- Modal actions for profile update, avatar update, rating explainability.
- Withdrawal flow moved to dedicated `/courier/wallet` surface in wallet Phase 1.

## Route/controller/service boundaries
- Route layer is thin and delegates to `CourierProfileController` for all profile endpoints.
- Controller delegates writes to actions:
  - `PersistCourierProfileAction`
  - `PersistCourierAvatarAction`
- Read orchestration is in `CourierProfileReadModelService`.

## Read models
- `profile_identity`, `profile_contact`, `profile_address`, `profile_media`, `profile_verification`
- `rating_summary` via `CourierRatingSummaryService` (Phase 1 provisional contract)
- `balance_summary` uses `CourierBalanceSummaryService` (ledger truth), then payout policy overlay from `CourierPayoutPolicyService`.

## Rating contract (Phase 1 provisional)
Factors (explainable, bounded queries):
1. customer score
2. successful order rate
3. accepted order rate
4. line reliability (expired offer pressure)
5. tenure (small weight)

Phase 1 disclaimer: this is an explainable provisional score, not a full KYC/ML rating pipeline.

## Persisted vs computed
Persisted:
- courier residence address + verification status on `users`
- withdrawal requests in `courier_withdrawal_requests`

Computed:
- rating summary
- payout eligibility
- balance summary projection (ledger aggregate)

## Runtime isolation
Profile page does not pull/poll available/my orders hot paths.
Runtime online toggle remains in courier layout header and keeps canonical source contract intact.

## Bounded stale-safe cache (non-critical widgets only)
- Cache scope is limited to profile cabinet read widgets:
  - `profile_identity`, `profile_contact`, `profile_address`, `profile_media`
  - `rating_summary`
  - `balance_summary` (including payout policy overlay)
- `profile_verification` is intentionally excluded from cache and always read from source-of-truth verification request lifecycle to avoid stale badge/CTA/status states.
- Key namespace: `courier:{id}:profile:{widget}`.
- TTLs are configured via `config/courier_profile_cache.php` and env variables:
  - identity/contact/address/media: `300s`
  - rating: `120s`
  - balance/payout eligibility: `60s`
- Invalidation rules:
  - `PersistCourierProfileAction` invalidates identity/contact/address keys.
  - `PersistCourierAvatarAction` invalidates media key.
  - `CreateCourierWithdrawalRequestAction` invalidates balance key.
- Safety:
  - cache read/write failures never break rendering; code falls back to direct read-model computation.
  - cacheability is intentionally bounded-stale and non-authoritative.
- Explicitly out of scope (must stay uncached): `courierRuntimeSnapshot`, `CourierPresenceService` canonical runtime methods, `AvailableOrders`, `MyOrders` hot runtime pane, `LocationTracker`, dispatch/candidate logic.

## Rollback notes
- Drop `courier_withdrawal_requests` table.
- Remove added `users` fields (`residence_address`, `courier_verification_status`).
- Remove profile routes/controller/actions/services.

## Avatar upload transport and production limits (PR-1)

- Courier avatar UX now follows the same Livewire sheet/form contract as client profile avatar flow (preview + explicit save action).
- Application validation remains `image|max:2048` (2 MB).
- To prevent server-level `413 Request Entity Too Large` pages in production, infrastructure limits must stay above app validation threshold:
  - `nginx client_max_body_size` >= `3m`;
  - `php upload_max_filesize` >= `3M`;
  - `php post_max_size` >= `3M`.
- If infra limits are below these values, users can still hit gateway-level 413 before Laravel validation executes.
