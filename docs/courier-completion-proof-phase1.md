# ADR: Courier completion proof flow (Phase 1)

## Context
Legacy completion path finalized order and settled courier earnings immediately on courier action. For `handover_type=door`, this is insufficient: completion now requires (1) door photo, (2) container photo, (3) client confirmation.

## Decision
We introduced a dedicated bounded context for completion proof with isolated tables and actions. `orders` hot path remains lean; proof media metadata and confirmation lifecycle are stored outside `orders`.

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

## Lifecycle
1. `StartOrderCompletionProofAction` creates (or returns) request idempotently.
2. `UploadOrderCompletionProofAction` upserts proof by `proof_type` (deterministic retry-safe behavior).
3. `SubmitOrderCompletionByCourierAction` validates required proofs and moves request to `awaiting_client_confirmation`.
4. `ConfirmOrderCompletionByClientAction` marks `client_confirmed` and calls final order finalization.
5. `FinalizeCompletedOrderAction` performs `order -> done`, courier runtime release, and earnings settlement.

## Invariants
- Cannot submit without required proofs.
- No duplicate active completion request per order.
- Duplicate submit/confirm are guard-railed and side-effect safe.
- Only assigned courier can upload/submit.
- Settlement is executed once at finalization boundary.

## Why isolated from `orders`
- avoids nullable media/status column bloat in hot table
- keeps courier dispatch/read model unaffected
- clean rollback by dropping two bounded tables and switching orchestration back

## Settlement timing implications
For door proof policy, settlement no longer happens at courier submit stage; it happens only after client confirmation finalization.

## Rollback plan
1. Switch orchestration to legacy-only finalization.
2. Keep new tables read-only for audit, or drop via rollback migration.
3. Existing order lifecycle remains intact because `orders` schema is unchanged.

## Phase 2 follow-up
- SLA auto-confirm job using `auto_confirmation_due_at`
- dispute entry points + resolution workflow
- admin review tools
- media storage hardening (virus scan, signed URLs, retention)
- client confirmation UI and API endpoints
