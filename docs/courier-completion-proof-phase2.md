# Courier completion proof flow — Phase 2

## State machine (order_completion_requests)
- `draft` / `ready_for_submit` -> `awaiting_client_confirmation`
- `awaiting_client_confirmation` -> `client_confirmed` (client confirm)
- `awaiting_client_confirmation` -> `auto_confirmed` (SLA job)
- `awaiting_client_confirmation` -> `disputed` (client dispute)
- `disputed` -> `client_confirmed` (admin approve)
- `disputed` -> `cancelled` (admin reject)

Settlement/runtime side-effects stay centralized in `FinalizeCompletedOrderAction`.

## New bounded model
Added `order_completion_disputes` (one dispute per completion request):
- open/under_review/resolved_confirmed/resolved_rejected
- reason, comment, resolver, timestamps
- indexed for queue operations.

## Client flow
- `GET /api/client/orders/{order}/completion-proof`
- `POST /api/client/orders/{order}/completion-proof/confirm`
- `POST /api/client/orders/{order}/completion-proof/disputes`

Client can act only on own order. Courier is forbidden for client confirmation endpoints.

## Admin/support flow
- `GET /api/admin/completion-disputes`
- `GET /api/admin/completion-disputes/{dispute}`
- `POST /api/admin/completion-disputes/{dispute}/under-review`
- `POST /api/admin/completion-disputes/{dispute}/resolve-confirmed`
- `POST /api/admin/completion-disputes/{dispute}/resolve-rejected`

## SLA auto-confirm
Command: `php artisan orders:completion-proof:auto-confirm --limit=100`
- scans `awaiting_client_confirmation` and `auto_confirmation_due_at <= now()`
- row lock + recheck + idempotent outcome
- per-item and aggregate structured logs

Scheduler entry added in `routes/console.php` (every minute).

## Media/security hardening
- client/admin payloads resolve proof URL via media resolver (signed URL when driver supports it)
- raw `file_path` is not returned in API payload
- upload metadata validation: mime/extension/size allowlists in `config/order_completion_proof.php`
- extension point kept in `OrderCompletionProofUploadValidator` for future AV pipeline.

## Runtime decision
Phase 2 keeps Phase 1 semantics: courier runtime release still happens only at finalization boundary (client confirm / auto-confirm / dispute resolved-confirmed). Tradeoff: courier can remain busy while client is pending.

## Rollback plan
- disable scheduler line for auto-confirm
- keep client/admin endpoints behind route-level auth
- dispute table can be retained without affecting legacy path
- legacy non-proof completion path remains unchanged.

## Runbook snippets
- Pending confirmations:
  - query `order_completion_requests` by `status=awaiting_client_confirmation`
- Safe re-run auto-confirm:
  - `php artisan orders:completion-proof:auto-confirm --limit=100`
- Dispute queue:
  - `GET /api/admin/completion-disputes`
