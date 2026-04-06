# Courier earnings module — Phase 1 ADR

## Status
Accepted (Phase 1, April 6, 2026).

## Context
Courier cabinet header needs a real balance source of truth. The online/offline runtime flow must stay canonical via `courierRuntimeSnapshot()` and remain isolated from earnings computation.

## Decision
### Domain model
- `courier_earning_settings` — admin-configurable global platform commission rate (%).
- `courier_earnings` — immutable-like ledger entries for courier settlements (one row per settled order in Phase 1).

### Settlement timing
Settlement is triggered in `CompleteOrderByCourierAction` after order transition to:
- `status = done`
- `payment_status = paid`
- `completed_at` exists
- `courier_id` exists

### Formula
For each order:
- `order_gross_amount = orders.price`
- `platform_commission_rate_percent = courier_earning_settings.global_commission_rate_percent`
- `platform_commission_amount = round(order_gross_amount * rate / 100, 2)`
- `courier_net_earning = order_gross_amount - platform_commission_amount`
- `bonuses_amount = 0`
- `penalties_amount = 0`
- `adjustments_amount = 0`
- `courier_final_earning = courier_net_earning + bonuses + adjustments - penalties`

> Implementation note: order price storage is integer minor units in current codebase; rounding is applied in integer space for Phase 1.

### Invariants
- Exactly one earning entry per order (`UNIQUE(order_id)`).
- Repeated completion callbacks/jobs cannot double-credit.
- Each ledger entry stores applied commission rate at settlement time; commission changes only affect future settlements.

### Read model
`CourierBalanceSummaryService` returns a compact summary contract for the courier header:
- `completed_orders_count`
- `gross_earnings_total`
- `platform_commission_total`
- `courier_net_balance`
- `balance_formatted`

No heavy aggregation in Blade templates.

### Admin model
A dedicated Filament resource (`CourierEarningSettingResource`) edits global commission:
- numeric
- min `0`
- max `100`
- descriptive helper text about platform deduction from courier earnings
- app-level validation in Filament form (Phase 1 compatibility-first)

## Consequences
### Positive
- Header balance is real and deterministic.
- Settlement and runtime toggle stay decoupled.
- Financial recalculation remains audit-friendly (ledger + stored rate).

### Risks / caveats
- Phase 1 has no payout lifecycle (`available/pending` split, holds, payout batches).
- No refund reversal flow yet; follow-up should add compensating ledger entries.
- Existing order amount precision model remains mixed historically; Phase 1 aligns with current integer operational model.

## Rollback plan
- Revert migration and remove earnings services/resource.
- Toggle UI can keep cosmetic redesign independently.
- If migration rollback is needed in production, archive `courier_earnings` rows first.

## Follow-up (P1/P2)
- P1: payout entities + payout status machine + available balance view.
- P1: reversal/adjustment entry type for refunds/cancellations after settlement.
- P2: commission overrides (city/tier/promo) with explicit precedence rules.
- P2: discrepancy monitoring dashboard and reconciliation job.
