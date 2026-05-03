# POOF Backend Architecture Audit — 2026-05-03

## Scope

Reviewed domains:
- Orders lifecycle (create → pay → searching → accepted → in_progress → done/expired/cancelled)
- Subscriptions and recurring execution orders
- Courier dispatch / `order_offers`
- Payments (WayForPay callback + return flow)
- Client UI / Courier UI / Admin UI integration points
- Auth, role separation, middleware coverage

---

## Order FSM contract

### Official statuses
- `new`
- `searching`
- `accepted`
- `in_progress`
- `done`
- `cancelled`
- `expired`

### Allowed transitions (contract)
- Core happy-path: `new → searching → accepted → in_progress → done`
- Cancel-path:
  - `new/searching → cancelled`
  - `accepted/in_progress → cancelled` only via explicit edge-case flow (manual/admin-assisted or policy-based exception flow, not generic client cancellation).
- Expiry-path:
  - `searching → expired` when promise/validity window is exhausted.

### Forbidden transitions (contract)
- Any backward move from terminal states:
  - `done|cancelled|expired → *` (forbidden)
- Direct skip moves:
  - `new → accepted` (forbidden)
  - `new → in_progress` (forbidden)
  - `searching → done` (forbidden)
- Payment/state integrity violation:
  - `paid` lifecycle outcome cannot imply forced rollback to earlier unpaid/order-initial states.

### Current enforcement map
- **Enforced in application actions/services**:
  - Courier accept/start/complete guarded via lifecycle action methods (`canBeAccepted`, `canBeStartedBy`, `canBeCompletedBy`) and transactional locks.
  - Payment success drives transition to dispatchable/searching path via paid mark flow.
  - Auto-expire engine handles stale `searching` orders.
- **Not fully enforced at DB level**:
  - No strict DB-level finite-state transition constraint matrix.
  - Terminal-state immutability is largely app-code convention.
- **Required hardening**:
  - Introduce explicit FSM transition policy table/map used by all write paths.
  - Add negative tests for forbidden transitions and concurrent transition attempts.

---

## Subscription invariants

### Invariant S1
**One subscription cannot have more than one unresolved pending execution order.**
- DB constraint now: **No (not guaranteed).**
- Application code now: **Partially** (checked in generation and checkout flows, but race windows exist without universal DB idempotency key).
- Required tests:
  - Concurrency test with overlapping generation/checkout attempts.
  - Regression test for stale pending order blocking duplicate creation.

### Invariant S2
**One scheduled slot cannot have more than one execution order for the same subscription.**
- DB constraint now: **No (not guaranteed).**
- Application code now: **Partially** (slot duplicate detection exists, but must short-circuit and be race-safe).
- Required tests:
  - Slot-duplicate prevention test (same minute slot).
  - Multi-worker overlap test proving at-most-one order per slot.

### Invariant S3
**One `client_id + address_id` scope cannot have more than one active subscription.**
- DB constraint now: **No strict unique partial index visible for active scope.**
- Application code now: **Partially** (`assertNoOtherActiveSubscriptionInScope`-style guard paths exist but rely on code path discipline).
- Required tests:
  - Creation/update race test for simultaneous activation.
  - Cross-entrypoint regression (admin/client path parity).

---

## Subscription execution dispatch pipeline

### Canonical flow
1. Subscription execution order is generated.
2. Order enters `payment_status=pending` (awaiting payment).
3. Payment callback/flow marks order paid.
4. Order becomes eligible for `searching` dispatch phase.
5. Offer dispatch loop (`OfferDispatcher`) evaluates order.
6. `order_offers` entries are created/rotated.
7. Courier available-orders surface returns alive pending offers.

### Dispatch filters (contract)
Dispatch candidate must satisfy:
- `payment_status = paid`
- `status = searching`
- `courier_id IS NULL`
- `valid_until_at` is not expired
- `next_dispatch_at` / backoff gate allows attempt now
- no alive pending offer exists **or** dispatcher is allowed to create next offer

### Operational assertion to add
- Every paid subscription execution order must either:
  - produce at least one eligible `order_offer`, or
  - emit explicit diagnostics reason why dispatch was skipped/deferred.

---

## Payment invariants

### P1. Single state-advance effect
One successful payment callback must advance order/subscription payment state to paid at most once.

### P2. Callback idempotency
Repeated callback with same transaction must be safely idempotent (no double side-effects).

### P3. No paid rollback
`paid` cannot be rolled back to unpaid/pending by callback noise or retries.

### P4. No duplicate entities after paid
Paid order must not cause duplicate offers/orders because of repeated callback or race.

### P5. Repeated callback safety
Second+ callback must not break lifecycle state or fail with destructive side-effects.

### Current posture
- Signature verification and duplicate-paid no-op behavior exists.
- Missing strict invariants validation for amount/currency/merchant-account parity and complete external transaction idempotency envelope.

---

## Confirmed issues / known risk areas

- Stale pending subscription execution orders can block expected renew flow.
- Duplicate active subscriptions risk in concurrent or cross-entrypoint operations.
- Duplicate scheduled execution orders risk under overlapping scheduler workers.
- `auto_renew=false` regression risk during lifecycle mutations/backfills.
- Dispatch invisibility risk for paid subscription execution orders when eligibility/filter diagnostics are incomplete.

---

## Prioritized backlog

### P0
- Enforce order FSM transitions via centralized contract used by all lifecycle writers.
- Enforce subscription execution order uniqueness (pending + slot-level) with DB-backed idempotency.
- Verify paid subscription execution orders always reach courier offers or produce deterministic diagnostics.

### P1
- Add payment idempotency and economic-invariant tests.
- Add client-visible subscription execution statistics (created/pending/paid/dispatched outcomes).
- Close admin middleware/policy coverage gaps for API surfaces.

### P2
- N+1/query optimization on dispatch/courier read paths.
- Scheduler/queue observability (heartbeat, lag, stuck order SLOs).
- FSM fuzz/concurrency transition tests.

---

## Critical

_No critical issues confirmed with deterministic local reproduction in this audit pass._

## High

### H0. Overlapping scheduler runs can create duplicate subscription execution orders
- **File/class**: `app/Console/Commands/GenerateSubscriptionExecutionOrdersCommand.php`
- **Scenario**: command selects due active subscriptions, then for each does `exists()` checks and `createFromLegacyWebContract()` without transaction-level row lock on subscription, and without unique DB constraint covering a generation slot. If two workers/scheduler invocations overlap (common in horizontally scaled cron/queue setups), both can pass `exists()` and both create pending orders.
- **Impact**: duplicate charge intents, duplicate pending orders per subscription, client confusion, and broken recurring contract semantics.
- **Evidence**:
  - No `lockForUpdate()` around subscription row.
  - Duplicate-slot flag increments but does not block creation path.
- **Proposed fix (minimal)**:
  1. Wrap per-subscription generation in `DB::transaction`.
  2. Re-read subscription row with `lockForUpdate()`.
  3. Re-check pending order existence inside lock and `continue` early.
  4. Add DB unique protection (e.g., partial uniqueness strategy via deterministic `subscription_run_slot` column, or app-level idempotency key).
- **Tests**:
  - Add integration race test simulating two near-concurrent command executions to assert single pending order created.
  - Add regression test asserting `skipped_duplicate_slot` branch does not proceed to create order.

### H1. WayForPay callback does not validate merchant account/currency/amount against local order
- **File/class**: `app/Http/Controllers/Api/Payments/WayForPayCallbackController.php`
- **Scenario**: callback verifies signature and finds order by `orderReference`, then marks order paid on success status. There is no strict equality check that callback `merchantAccount`, `currency`, and `amount` match the expected order checkout data.
- **Impact**: if a validly signed callback payload can reference an internal `orderReference` but mismatched economics, order may be marked paid with inconsistent accounting data (integrity risk and reconciliation gaps).
- **Proposed fix**:
  - Validate `merchantAccount` against configured account.
  - Validate `currency` and exact amount (normalized) against order expected payable amount.
  - Store external transaction id and add idempotency protection keyed by provider transaction reference.
- **Tests**:
  - Feature test: valid signature but wrong amount → 422 and order remains pending.
  - Feature test: wrong merchantAccount/currency → rejected.

### H2. Admin API routes depend on in-controller role aborts, not route middleware
- **File/class**: `routes/api.php`, `OrderCompletionDisputeAdminController`
- **Scenario**: `/api/admin/completion-disputes*` lives only under `auth:sanctum`, while admin enforcement is done manually in each action using `abort_if(!isAdmin)`. This is fragile: future methods/routes may omit check and silently expose admin data to authenticated non-admins.
- **Impact**: authorization regression risk (IDOR/privilege escalation) due to human error in future changes.
- **Proposed fix**:
  - Wrap these routes in explicit admin middleware (e.g., `AdminOnly`) adapted for API guard.
  - Keep controller-level checks as defense-in-depth.
- **Tests**:
  - Route contract test ensuring non-admin sanctum user receives 403 for every `/api/admin/completion-disputes*` endpoint.

---

## Medium

### M1. Duplicate-slot branch in subscription generator does not short-circuit
- **File/class**: `GenerateSubscriptionExecutionOrdersCommand`
- **Scenario**: when `existingPendingForSlot` is true, command increments `skipped_duplicate_slot` but continues execution and can still create order depending on broader pending-state check.
- **Impact**: telemetry says duplicate slot detected, but control flow may still proceed; causes confusing operator diagnostics and weakens idempotency intent.
- **Proposed fix**: add `continue;` after increment, or fold into a single definitive guard.
- **Tests**: command-level test asserting no create occurs when slot duplicate is detected.

### M2. Route model binding by raw order ID increases IDOR blast radius if ownership checks regress
- **File/class**: client payment routes/controllers (`/client/payments/{order}`, `/client/payments/{order}/start`)
- **Scenario**: ownership is correctly checked now (`$order->client_id === auth()->id()`), but raw sequential IDs in URLs make accidental leak easier if check removed in future endpoint.
- **Impact**: latent IDOR risk via predictable identifiers.
- **Proposed fix**: use scoped binding by UUID/public id for external-facing routes, keep ownership checks.
- **Tests**: regression tests for unauthorized cross-user access already partially exist; extend across all order-related client endpoints.

### M3. Dispatch service stub present while jobs/scheduler are active
- **File/class**: `app/Services/Dispatch/DispatchService.php`, `app/Jobs/DispatchOrderJob.php`
- **Scenario**: dispatch job delegates to empty placeholder `DispatchService::dispatch`. If wired accidentally in production workflow, searching orders may not progress.
- **Impact**: orders stuck in `searching`, silent runtime failure path.
- **Proposed fix**: either remove unused job path or implement guard logging/exception to prevent silent no-op.
- **Tests**: integration test asserting dispatch invocation produces observable side-effect or explicit warning.

---

## Low

### L1. Potential heavy payload in courier available-orders API
- **File/class**: `app/Http/Controllers/Api/CourierOrderController.php`
- **Scenario**: returns raw `OrderOffer` collection without explicit field projection/resource transformer.
- **Impact**: response bloat and accidental field exposure risk as model evolves.
- **Proposed fix**: use API resource with explicit allowlist + pagination/limit policy.
- **Tests**: API schema snapshot/contract test for allowed fields only.

### L2. Mixed authorization strategy across web/api layers
- **File/class**: `routes/web.php`, `routes/api.php`, multiple controllers
- **Scenario**: some domains enforce role at middleware, others at controller action body.
- **Impact**: inconsistent security posture and higher maintenance burden.
- **Proposed fix**: standardize: middleware for coarse role gate + policy/action checks for object-level rules.
- **Tests**: centralized authz matrix test by role × endpoint.

---

## Existing test coverage review (high-level)

Strong existing coverage observed in:
- Courier accept race and dispatch-related runtime behavior.
- Payment start/return flow basics.
- Role entrypoint and protected-route auth checks.
- Subscription checkout flow.

Critical missing/insufficient cases:
1. **Recurring order generation concurrency** (no strong race/idempotency test for command overlap).
2. **WayForPay economic invariants** (`amount/currency/merchantAccount` mismatch negative tests).
3. **Admin API middleware contract** (ensuring every admin API endpoint is middleware-protected, not only controller-guarded).
4. **State-machine transition fuzzing** (invalid status transitions under concurrent operations: accept/start/complete/cancel).
5. **Queue/scheduler failure-mode tests** (stuck `searching` order alerting and recovery path).
