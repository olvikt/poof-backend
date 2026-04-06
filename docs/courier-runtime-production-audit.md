# ADR: Courier Runtime Production Audit (runtime/read/dispatch path)

- **Date:** 2026-04-06
- **Status:** Accepted (incremental hardening, no canonical contract break)
- **Scope:** Courier runtime, read paths (`AvailableOrders`, `MyOrders`, `OnlineToggle`, `LocationTracker`), offer dispatch pipeline.

## Context / Current architecture

### Canonical / Derived / Optimistic / Legacy split

1. **Canonical (must stay canonical):**
   - `User::courierRuntimeSnapshot()` contract.
   - `couriers.status` + active order state (`orders.status in accepted/in_progress`).
2. **Derived mirrors (transitional):**
   - `users.is_online`, `users.is_busy`, `users.session_state` synced from canonical courier state.
3. **Optimistic/UI only:**
   - Livewire local online projection (`AvailableOrders::$online` optimistic TTL).
   - Browser map cross-tab runtime hints in `resources/js/poof/map.js`.
4. **Legacy that must keep shrinking:**
   - Any business/read decision directly from raw `users.*` runtime flags.
   - Duplicate per-component canonical state resolution.

### Runtime flow map (dependency map)

1. **Courier status transitions**
   - `User::transitionCourierState()` enforces active-order lock semantics first, then status transition.
2. **Online/offline sync**
   - `OnlineToggle` → `CourierPresenceService::toggleOnline()` → canonical snapshot re-read.
3. **Active order reconciliation**
   - `User::repairCourierRuntimeState()` binds `couriers.status` to active order truth.
4. **Available orders read path**
   - `AvailableOrders` reads canonical runtime and alive pending offers (`order_offers.pending + expires_at > now`).
5. **My orders read path**
   - `MyOrders` reads canonical online state + active courier orders.
6. **Offer/dispatch path**
   - `OfferDispatcher::dispatchPrimaryOffer()` under order row lock, with backoff and pending-offer guards.
7. **Map/location sync path**
   - `LocationTracker` receives geolocation updates and can trigger dispatch loop.

## Hot paths

1. `courierRuntimeSnapshot()` is touched by API + multiple Livewire components.
2. `AvailableOrders::render()` and `MyOrders::render()` are frequent reads under active courier sessions.
3. `LocationTracker::updateLocation()` can be high-frequency under active map usage.
4. `OfferDispatcher::fetchCandidates()` and `searchingOrdersQuery()` are dispatch bottlenecks.

## Bottlenecks found

### Architectural

- Canonical online resolution was duplicated across components (`AvailableOrders`, `MyOrders`, `OfferCard`) instead of one read path.
- `LocationTracker::mount()` had redundant rereads of the same courier model.

### Query/database

- Snapshot previously did extra reread work (`refresh()` + second active-order lookup) after repair.
- Candidate query had redundant correlated `NOT EXISTS` for pending offer-by-order/courier pair (already pre-guarded by order-level live pending check).
- Location update path did an extra `searching orders exists` query before dispatch loop.

### Concurrency/race risks

- Cross-component optimistic online projection can drift shortly; canonical TTL self-heal exists (kept).
- Location-triggered dispatch is high-frequency and must remain throttled; still sensitive to device jitter.

### Observability gaps

- Core logs exist for dispatch, but no single release checklist tying SLO/SLI to gate decisions.

### Release/rollback risks

- Any accidental move back to raw `users.*` for decisions can silently reintroduce drift.
- Dispatch SQL tuning must stay validated per real DB engine with EXPLAIN (not guessed locally).

### Mobile/browser runtime risks

- Cross-tab hints can burst events; must remain “optimistic only” and not override backend canonical state.

## Simplification opportunities implemented in this PR

1. Unified canonical online/runtime reads via `CourierPresenceService` methods used by `AvailableOrders`, `MyOrders`, `OfferCard`, `LocationTracker`.
2. Reduced redundant snapshot rereads by deriving `active_order_status` from canonical `couriers.status` after repair.
3. `AvailableOrders` now conditionally reads active order details only when snapshot says `has_active_order=true`.
4. `LocationTracker` removed duplicated user rereads and removed pre-check query before dispatch loop.
5. `OfferDispatcher` candidate SQL dropped redundant pending-offer correlated subquery.

## Risks under load (remaining)

1. Distance scoring remains in PHP after candidate fetch; with high courier density this can be CPU-heavy.
2. Dispatch loop still scans searching orders in batches; tuning needs prod telemetry.
3. Geo update frequency is still client-driven; noisy devices can pressure dispatch pipeline despite throttling.

## Recommended next steps

- Add server-side metric sampling around candidate cardinality and dispatch elapsed p95/p99.
- Evaluate DB-native geospatial narrowing (or stronger pre-filter window) if candidate pool grows.
- Add dedicated per-route query budget checks for `AvailableOrders`/`MyOrders` in CI once environment stabilizes.

## What must not be changed

1. Canonical source of runtime truth must remain `courierRuntimeSnapshot()`.
2. Domain truth remains `couriers.status` + active order state.
3. Optimistic/cross-tab hints must never become canonical source.
4. Route/controller layers must remain thin and delegate runtime transitions to domain/actions/services.

## Next PR backlog

- **P0:** Production EXPLAIN verification of dispatch hot queries on real dataset (with index usage capture).
- **P1:** Candidate selection optimization strategy (DB geospatial or bounded scoring set).
- **P2:** UI diagnostics stream compaction for long-lived tabs.
