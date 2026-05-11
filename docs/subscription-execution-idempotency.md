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

## Production migration preflight (pending guard index)

Before creating partial unique pending index (`orders_one_pending_subscription_execution_idx`) the migration now runs a preflight duplicate scan and fails fast with a clear `RuntimeException` if duplicates exist.

### Diagnostic SQL: find duplicate pending subscription execution orders

```sql
SELECT
  subscription_id,
  COUNT(*) AS pending_count,
  GROUP_CONCAT(id ORDER BY id) AS order_ids
FROM orders
WHERE origin = 'subscription'
  AND payment_status = 'pending'
  AND subscription_id IS NOT NULL
GROUP BY subscription_id
HAVING COUNT(*) > 1
ORDER BY subscription_id;
```

### Safe cleanup policy

1. For each problematic `subscription_id`, keep exactly one canonical unresolved pending order (typically the oldest by `id`/`created_at`).
2. For extra duplicates, resolve deterministically (never hard-delete blindly):
   - either move to non-pending terminal state according to your incident policy, or
   - if confirmed ghost rows, archive/export and then delete via approved runbook.
3. Re-run the diagnostic query and ensure zero rows returned.
4. Retry migration.

This prevents opaque DB-level index-creation failures and gives operators concrete IDs to repair before deploy proceeds.
