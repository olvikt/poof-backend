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

## Phase 3 UX hardening: camera-first proof capture
### Why file upload was rejected as primary UX
Visible `input[type=file] + upload` flow allowed selecting old gallery images first, which weakened customer trust in “captured now” intent for door handover completion.

### New courier proof UX contract
For proof-aware orders in courier live flow:
1. Courier taps **“Фото у двері”**.
2. App opens camera-first capture sheet (`getUserMedia`, prefers `environment` camera).
3. Courier captures frame and uploads immediately.
4. Card shows thumbnail + success check.
5. Same flow for **“Фото у контейнера”**.
6. Only after both proofs a completion confirmation modal appears:
   - Title: **Ви завершили замовлення**
   - Body: **Гроші зарахуються як тільки клієнт підтвердить виконання**
   - CTA: **Завершити замовлення**

### Fallback behavior
- If camera API is unsupported/denied/unavailable, UI switches to hidden picker fallback (`accept="image/*"`, `capture="environment"`).
- Fallback remains available for platform resilience (especially iOS/Safari/browser permission edge cases), but is not the default visible interaction.

### Trust/audit metadata
Proof upload now records lightweight metadata for investigation:
- `captured_via` (`camera` or `file_fallback`)
- optional `client_device_clock_at`
- `checksum_sha256` (when storage driver can resolve local file path)
- replacement event log when retake overwrites same `proof_type`

This preserves existing proof validation and idempotent upsert semantics while improving auditability.

## Phase 4 UX hardening: proof auto-reveal + completed earnings accordion
### Why this was changed
Manual courier runs on phones showed that after tapping **“Почати виконання”** the proof block stayed below the fold. Couriers missed the next required action and completion time increased.

### New My Orders behavior
- For proof-aware orders, successful `start` emits a UI event and frontend scrolls directly to the active proof anchor (`data-proof-section-for-order="<id>"`).
- The proof block gets a short pulse highlight so the next action is visually obvious.
- The block now has explicit guidance:
  - Heading: **Зробіть 2 фото для завершення**
  - Helper (amber while incomplete): **Завершення стане доступним після 2 фото**
  - Helper (success when both photos exist): **Фото додано. Тепер можна завершити замовлення.**

### New completed orders stats section
- Courier “Мої замовлення” now includes a daily accordion for completed orders earnings.
- Data is grouped by `completed_at` day with:
  - day label (`Сьогодні` / date),
  - daily total earnings,
  - expandable rows with address, completion time, and per-order amount.
- Initial read model is bounded by configurable recent days window (`COURIER_COMPLETED_STATS_DAYS`, default 14) to avoid scanning full history on hot render path.
