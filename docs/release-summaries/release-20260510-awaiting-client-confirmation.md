# Release 2026-05-10: Awaiting Client Confirmation Lifecycle

## Lifecycle changes
- Courier proof submit now transitions completion request into `awaiting_client_confirmation` and records proof/deadline metadata.
- Client confirmation finalizes completion with explicit resolution metadata.
- Client dispute transitions completion request to disputed with explicit resolution metadata.
- Auto-confirm command finalizes overdue awaiting confirmations and marks system auto-completion metadata.

## Timeout behavior
- Added configurable timeout: `ORDER_COMPLETION_CONFIRMATION_TIMEOUT_MINUTES` (default `120`).
- Deadline is written into `completion_confirmation_deadline_at` and mirrored by current `auto_confirmation_due_at`.

## Earnings unlock behavior
- Earnings remain unlocked only on final completion transition (client-confirmed or auto-confirmed) through existing finalize action.
- Duplicate confirms/auto-confirms are guarded by completion status checks to avoid double settlement.

## Manual QA
1. Courier accepts and starts an order.
2. Courier uploads two proof photos and submits completion.
3. Confirm client receives awaiting confirmation state (not in-progress).
4. Client confirms -> completion finalizes and courier settlement is applied once.
5. Repeat and do not confirm; run scheduler after deadline -> auto-complete + single settlement.
6. Repeat and open dispute -> status disputed and not auto-completed.
7. Re-run confirm/job calls and verify no duplicate crediting.
8. Verify admin/support can inspect proof artifacts and lifecycle timestamps.

## Rollback notes
- Revert this release commit and run `php artisan migrate:rollback` for the lifecycle metadata migration.
- Restore scheduler command signature to prior `orders:completion-proof:auto-confirm` if external automation depends on legacy naming.
