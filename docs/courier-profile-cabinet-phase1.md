# Courier profile/cabinet page — Phase 1 architecture

## Scope (P0)
- Dedicated courier page at `/courier/profile`.
- Isolated compact read models for profile, rating, and balance/payout eligibility.
- Modal actions for profile update, avatar update, rating explainability, withdrawal request.

## Route/controller/service boundaries
- Route layer is thin and delegates to `CourierProfileController` for all profile endpoints.
- Controller delegates writes to actions:
  - `PersistCourierProfileAction`
  - `PersistCourierAvatarAction`
  - `CreateCourierWithdrawalRequestAction`
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

## Withdrawal contract (Phase 1 foundation)
- New entity: `courier_withdrawal_requests`
  - `amount`
  - `status`: `requested|approved|rejected|paid`
  - `notes`, `admin_comment`, timestamps
- Policy contract:
  - `can_request_withdrawal`
  - `min_withdrawal_amount` (admin-configurable via `COURIER_MIN_WITHDRAWAL_AMOUNT`)
  - `withdrawal_block_reason`

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

## Rollback notes
- Drop `courier_withdrawal_requests` table.
- Remove added `users` fields (`residence_address`, `courier_verification_status`).
- Remove profile routes/controller/actions/services.
