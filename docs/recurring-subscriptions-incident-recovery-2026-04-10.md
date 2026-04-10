# Recurring Subscriptions Incident Recovery (2026-04-10)

## Incident summary

After PR #533 deployment, `subscriptions:generate-execution-orders` was healthy but skipped legacy active subscriptions where `auto_renew=false`.

Observed production example:
- subscriptions `id=4` and `id=5` were active and overdue by `next_run_at`
- both had `auto_renew=false`
- command summary reported `checked=0 created=0`

## Lifecycle semantics (decision)

`auto_renew` controls renewal behavior at period end, not eligibility for recurring execution order generation inside an already paid active period.

A paid active subscription with overdue `next_run_at` must generate its next execution order even when `auto_renew=false`.

## Code fix in this patch

- Removed `auto_renew=true` filter from recurring generator query.
- Updated subscription lifecycle guard so `canGenerateNextOrderAutomatically()` depends on paid+active state (not auto-renew).

## Production recovery runbook

### 1) Deploy patch

Deploy this patch first so scheduler/command behavior is corrected.

### 2) Verify impacted rows

```sql
SELECT id, client_id, status, auto_renew, next_run_at, ends_at
FROM client_subscriptions
WHERE id IN (4,5);
```

### 3) Trigger recurring generation once

```bash
php artisan subscriptions:generate-execution-orders --limit=100
```

Expected outcome: `checked` includes overdue active paid subscriptions and `created` increases accordingly.

### 4) Optional legacy auto-renew backfill (targeted, safe)

Use only if business confirms those subscriptions should continue auto-renewing at period end.

Dry run:

```bash
php artisan subscriptions:backfill-auto-renew --ids=4 --ids=5 --dry-run
```

Apply:

```bash
php artisan subscriptions:backfill-auto-renew --ids=4 --ids=5
```

Safety guarantees:
- updates only explicit `--ids`
- updates only `status=active`
- updates only rows with `auto_renew=false`
- updates only rows that already have at least one paid subscription order

## Rollback plan

If this behavioral change is not accepted:

1. Revert this patch (restore `auto_renew=true` gating in generator/lifecycle guard).
2. Run only targeted backfill for explicitly approved IDs to restore expected generation under old rule:
   - `php artisan subscriptions:backfill-auto-renew --ids=<id>`
3. Re-run generator:
   - `php artisan subscriptions:generate-execution-orders --limit=100`

## Post-deploy checks

- scheduler keeps invoking recurring generator every 5 minutes
- `checked` and `created` are non-zero for due active paid subscriptions
- no duplicate pending subscription orders per subscription
- subscriptions page still reflects user auto-renew preference independently from recurring execution order creation

## Follow-up hotfix guidance (PR #534)

### Updated overdue semantics

- Generator now aligns overdue subscriptions to the **nearest valid current slot** (frequency-aligned, not historical backlog replay).
- `next_run_at` always advances from that computed slot, so it does not stay in the past.
- stale historical pending orders no longer block generation of the current slot forever.

### Remediation for already-created stale orders (`#80`, `#81`)

1. Identify stale pending subscription orders whose scheduled slot is already in the past:

```sql
SELECT id, subscription_id, status, payment_status, scheduled_date, scheduled_time_from
FROM orders
WHERE origin = 'subscription'
  AND payment_status = 'pending'
  AND status IN ('new', 'searching')
  AND TIMESTAMP(scheduled_date, COALESCE(scheduled_time_from, '00:00:00')) < NOW()
ORDER BY subscription_id, scheduled_date, scheduled_time_from;
```

2. For each stale order, choose one remediation policy with product/ops:
   - cancel stale order (recommended for historical slots that should not dispatch), or
   - reschedule to the next approved execution slot if operations requires fulfillment.

3. After remediation, run generation once and verify the subscription is no longer stuck:

```bash
php artisan subscriptions:generate-execution-orders --limit=100
```

4. Validate per subscription:
   - at most one pending order exists for the intended current/future slot,
   - `next_run_at` is in the future and frequency-aligned.
