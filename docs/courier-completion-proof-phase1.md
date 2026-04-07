# ADR: Courier completion proof flow (Phase 1)

## Context
Legacy completion path finalized order and settled courier earnings immediately on courier action. For door pickup this is insufficient: completion can require (1) door photo, (2) container photo, (3) client confirmation.

A CI regression showed that using `handover_type=door` alone to enable proof flow was too broad because legacy fixtures often default to `door` and still expect immediate completion runtime semantics.

## Decision
We keep proof flow in a dedicated bounded context and make it **explicit opt-in** on orders via `orders.completion_policy`.

- `completion_policy=none` -> legacy immediate completion.
- `completion_policy=door_two_photo_client_confirm` + `handover_type=door` -> proof-aware completion.

This restores legacy runtime contract while keeping proof flow isolated.

## New tables
- `order_completion_requests`
  - single request per order (`unique(order_id)`)
  - policy + status lifecycle fields
  - submit/confirm/auto-confirm timestamps
  - indexes: `(status, auto_confirmation_due_at)`, `(courier_id, status)`
- `order_completion_proofs`
  - normalized proof records by step (`door_photo`, `container_photo`)
  - unique `(completion_request_id, proof_type)`
  - lookup index `(order_id, proof_type)`

## Lifecycle semantics by entrypoint
### Canonical entrypoint: `Order::completeBy($courier)`
- Legacy/simple (`completion_policy=none`):
  - immediate finalization to `done`
  - `completed_at` set
  - courier runtime released (`ONLINE`, `is_busy=false`, `SESSION_READY`)
  - settlement executed
- Proof-aware (`completion_policy=door_two_photo_client_confirm`):
  - goes through proof submission path
  - request moves to `awaiting_client_confirmation`
  - order remains `in_progress` until client confirm
  - no settlement before finalization

### Proof flow actions
1. `StartOrderCompletionProofAction` creates (or returns) request idempotently.
2. `UploadOrderCompletionProofAction` upserts proof by `proof_type` (deterministic retry-safe behavior).
3. `SubmitOrderCompletionByCourierAction` validates required proofs and moves request to `awaiting_client_confirmation`.
4. `ConfirmOrderCompletionByClientAction` marks `client_confirmed` and calls final order finalization.
5. `FinalizeCompletedOrderAction` performs `order -> done`, courier runtime release, and earnings settlement.

## Runtime release timing
- Legacy/simple path: release happens immediately on `completeBy`.
- Proof-aware path: release happens only at finalization after client confirmation.

## Invariants
- Cannot submit without required proofs.
- No duplicate active completion request per order.
- Duplicate submit/confirm are guard-railed and side-effect safe.
- Only assigned courier can upload/submit.
- Settlement is executed once at finalization boundary.

## Why isolated from `orders`
- avoids nullable media/status column bloat in hot table
- keeps courier dispatch/read model unaffected
- clean rollback by dropping bounded proof tables and setting policy back to `none`

## Settlement timing implications
- Legacy/simple: settlement at immediate finalization.
- Proof-aware: settlement only after client confirmation finalization.

## Rollback plan
1. Set order `completion_policy` to `none` for all orders.
2. Keep or drop proof tables depending on audit needs.
3. Canonical lifecycle continues in legacy path without touching dispatch/read hot models.

## Phase 2 follow-up
- SLA auto-confirm job using `auto_confirmation_due_at`
- dispute entry points + resolution workflow
- admin review tools
- media storage hardening (virus scan, signed URLs, retention)
- client confirmation UI and API endpoints
