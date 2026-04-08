# Courier wallet page — Phase 1 bounded finance surface

## Why wallet is separate from profile
- `/courier/profile` remains identity/verification/settings surface.
- `/courier/wallet` is a dedicated finance surface with its own read/write boundaries.
- This separation avoids coupling finance mutations to profile edit flows and courier runtime hot paths.

## Canonical source of truth
- Earnings source of truth is `courier_earnings` ledger entries settled by order completion flow.
- Wallet summary reuses `CourierBalanceSummaryService` canonical contract:
  - `completed_orders_count`
  - `gross_earnings_total`
  - `platform_commission_total`
  - `courier_net_balance`
- Wallet history uses bounded recent query (`CourierCompletedOrdersDailyStatsQuery`) instead of full-history template recompute.

## Wallet page Phase 1 contract
Route:
- `GET /courier/wallet`

Sections:
1. Balance summary card
   - current balance
   - available to withdraw
   - held amount
   - pending amount
   - minimum withdrawal amount
   - `can_request_withdrawal`
   - `withdrawal_block_reason`
2. Withdrawal request form + recent requests list.
3. Payout requisites block.
4. Completed orders / earnings stats + bounded recent history.

## Persisted vs computed
Persisted:
- `courier_withdrawal_requests`
- `courier_payout_requisites`
  - `card_holder_name`
  - encrypted card number (`card_number_encrypted`)
  - masked card number (`masked_card_number`)
  - optional `bank_name`, `notes`

Computed:
- earnings totals / balance projection from ledger
- payout policy eligibility (`CourierPayoutPolicyService`)
- recent earnings day buckets

## Withdrawal lifecycle (Phase 1)
Statuses:
- `requested`
- `approved`
- `rejected`
- `paid`

Create request guardrails:
- amount must be >= backend-configured minimum (`COURIER_MIN_WITHDRAWAL_AMOUNT`)
- amount must be <= available to withdraw
- request blocked if pending request already exists (`requested|approved`)

## Payout requisites boundary
- No external bank integration in Phase 1.
- Raw card is normalized and persisted encrypted.
- UI and read models expose only masked representation.

## Phase 1 provisional constraints
- Held/pending accounting is derived from pending withdrawals, not full payout settlement accounting.
- No payout batches, no bank transfer execution, no reconciliation engine.
- No KYC/compliance subsystem in this scope.

## Rollback notes
1. Remove wallet routes/controller/view and related tests.
2. Drop `courier_payout_requisites` table.
3. Keep `courier_earnings` and `courier_withdrawal_requests` if profile payout flow still depends on them, or archive/drop based on release decision.
