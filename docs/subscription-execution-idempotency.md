# Subscription execution idempotency hardening (2026-05-11)

## What was changed

- Subscription execution generation is now wrapped in a DB transaction with `lockForUpdate()` on each `client_subscriptions` row before guards/checks and order creation.
- Duplicate-slot detection now uses deterministic `orders.subscription_run_slot` (`YYYY-MM-DD HH:MM:00`) instead of minute-range matching by `scheduled_time_from`.
- Inside the lock, the command re-checks:
  - existing unresolved pending order (`origin=subscription`, `payment_status=pending`)
  - existing order in the same slot (`subscription_id + subscription_run_slot`).
- Added DB-level uniqueness for slot idempotency via `UNIQUE (subscription_id, subscription_run_slot)`.
- Added DB-level unresolved pending uniqueness where partial indexes are supported (PostgreSQL/SQLite):
  - unique index on `subscription_id` filtered by `origin=subscription AND payment_status=pending`.
- Duplicate slot branch is now explicit short-circuit (`skipped_duplicate_slot`) and does not create a new order.

## Why

This closes race windows for overlapping workers/scheduler runs: application guards are now executed under row lock, and DB constraints enforce at-most-once semantics even if two workers pass code-level checks concurrently.

## Notes

- For MySQL, partial unique index is not created by this migration; slot-level unique index still provides DB-backed idempotency for run slot generation.
- Pending-order uniqueness remains guarded in application logic for MySQL deployments.
