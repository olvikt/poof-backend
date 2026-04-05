# Courier runtime architecture contract

## Source of truth

1. **Server runtime snapshot** (`User::courierRuntimeSnapshot()`) — canonical source for courier runtime.
2. **Client map runtime state** (`resources/js/poof/map.js`) — projection/observability cache only.
3. **Livewire courier components** (`AvailableOrders`, `MyOrders`, `OnlineToggle`) must re-read snapshot and self-heal to server truth.

### Truth source map

| Runtime source | Category | Why |
|---|---|---|
| `couriers.status` | canonical | Primary server runtime state (`offline/online/assigned/delivering`) enforced by `repairCourierRuntimeState()` + transition guards. |
| `active order status` (`orders.status` in `accepted/in_progress`) | canonical | Domain truth that can override courier flags and block illegal offline/free states. |
| `courierRuntimeSnapshot` payload | canonical read model | Unified server contract consumed by Livewire/API/JS; always built after runtime repair. |
| `users.is_online` | derived (server-persisted mirror) | Synced from canonical status map via `syncRuntimeFlagsFromCourierState()`. |
| `users.is_busy` | derived (server-persisted mirror) | Synced from canonical status and active-order reconciliation. |
| `users.session_state` | derived (server-persisted mirror) | Session projection (`offline/ready/assigned/in_progress`) mapped from canonical runtime. |
| Livewire local `online` (`AvailableOrders`, `MyOrders`, `OnlineToggle`) | optimistic-only/UI projection | Must quickly snap back to canonical snapshot; business logic cannot rely on it. |
| map/browser runtime hints/events (`courier:runtime-sync`, cross-tab payloads) | optimistic-only/UI projection | Transport for cross-tab UX sync and observability only; not authoritative. |
| direct UI interpretation of raw `users.*` flags | legacy / should be removed | Replaced with unified snapshot reads to avoid drift across tabs/components. |

### `courierRuntimeSnapshot` contract (canonical backend truth)

Snapshot payload keys (stable order):

| Key | Meaning | Canonical source |
|---|---|---|
| `online` | courier online/offline runtime bit | backend (`courierProfile.status` + runtime repair) |
| `busy` | courier busy bit | backend (`users.is_busy` after runtime repair) |
| `status` | courier business status (`offline/online/assigned/delivering`) | backend (`courierProfile.status`) |
| `session_state` | courier session projection (`offline/ready/assigned/in_progress`) | backend (`users.session_state`) |
| `active_order_status` | active order status (`accepted/in_progress`) or `null` | backend (`orders` query) |
| `has_active_order` | bool projection from `active_order_status` | backend (derived during snapshot build) |

### Canonical vs derived vs optimistic UI state

- **Canonical backend truth**: all `courierRuntimeSnapshot` fields above.
- **Backend-derived (still canonical)**: `has_active_order` is computed from backend active order lookup.
- **UI projection only**:
  - `AvailableOrders::$online` during optimistic window;
  - `AvailableOrders::$lastUiOnlineSyncAt` (TTL bookkeeping);
  - map runtime cross-tab signals, counters, diagnostics.
- **Conflict priority**: backend snapshot always wins after sync/polling/refresh.

## Online/offline contract

- Переключение online/offline инициируется через Livewire action (`OnlineToggle`).
- После toggle клиент обязан синхронизировать:
  - available orders list;
  - my-orders list;
  - busy indicator.
- При divergence клиент должен выполнить auto-resync polling и восстановить server truth.

## Active order / busy state contract

- `active_order_status != null` => courier busy;
- busy courier не может принимать новый unrelated offer;
- start/complete transitions выполняются только через lifecycle actions и валидируются статусными guards.

## State transition table

| Transition | Trigger | Required invariant after transition |
|---|---|---|
| `online -> offline` | `goOffline()`/toggle when no active order | `status=offline`, `is_online=false`, `is_busy=false`, `session_state=offline` |
| `free -> busy` | order accept | `status=assigned`, `busy=true`, `active_order_status=accepted` |
| `busy -> active order in progress` | order start | `status=delivering`, `busy=true`, `active_order_status=in_progress` |
| `active order -> completed` | order complete | `status=online`, `busy=false`, `active_order_status=null`, `session_state=ready` |
| stale runtime -> recovered | polling / render / runtime API read | snapshot repair restores status/busy/session_state from active order truth |
| stale optimistic UI -> recovered | optimistic TTL expiry or explicit sync | `AvailableOrders.online` snaps back to canonical snapshot |

## Polling + optimistic sync rules

- Polling работает как consistency loop, не как первичный source of truth.
- Optimistic UI допустим только до ближайшего успешного sync;
- Для `AvailableOrders` optimistic окно фиксировано: **3 секунды** (`UI_OPTIMISTIC_SYNC_TTL_SECONDS`).
- В течение TTL локальный `online` может временно отличаться от backend snapshot.
- Optimistic override разрешен только для local toggle event (`courier-online-toggled` with `changed=true`); cross-tab/runtime-sync hints must force canonical reread.
- После TTL любой `render`/polling обязан self-heal в backend canonical `online`.
- при конфликте optimistic vs server — побеждает server snapshot.

### Polling events and anti-drift invariants

- Polling/refresh paths:
  - Livewire component refresh (`$refresh`) in `AvailableOrders` / `MyOrders`;
  - explicit `syncOnlineState()` without payload (re-read snapshot);
  - runtime API polling (`/api/courier/runtime`) for JS diagnostics/recovery.
- Must update:
  - online marker (`online`);
  - active order projection (`active_order_status`, `has_active_order`);
  - busy/session flags.
- Must not drift between tabs/lists:
  - `available orders` UI cannot stay online/offline-diverged after TTL;
  - `my orders` online indicator always re-bound to runtime snapshot in `render()`;
  - busy + active order fields must remain mutually consistent after reconnect/bootstrap.

## Map/runtime integration contract

- Map runtime **reads** courier runtime state via runtime-sync events and optional `/api/courier/runtime` evidence fetch.
- Map runtime is **visual/observability layer**, not business source of truth.
- Business-significant state transitions (online toggle, accept/start/complete) stay in backend actions + Livewire server roundtrips.
- Dual-instance drift prevention:
  - cross-tab runtime sync emits/receives state hints;
  - each tab still heals via canonical backend snapshot, avoiding hidden local-only authoritative state.



## Bootstrap/runtime wiring invariants

### Single valid boot path

- App runtime bootstrap is centralized in `resources/js/app.js` and must remain the only place where Livewire/Alpine start decisions are made.
- Map bootstrap must enter via `mountAny()`/`mount()` in `resources/js/poof/map.js`; duplicate local map boot variants are not allowed.

### Where repeat init is allowed vs forbidden

- Allowed:
  - navigation/reconnect remount through guarded hooks (`livewire:navigated`, Livewire morph hooks) with teardown/reset before remount;
  - idempotent rebinding calls that are explicitly one-time guarded.
- Forbidden:
  - duplicate Livewire/Alpine startup in the same browser runtime;
  - hidden dual map instances bound to one logical runtime state.

### Required duplicate-boot guards

- Global guards: `window.__poofLivewireStarted`, `window.__poofAlpineStarted`.
- Shared component guard: `instance.__poofComponentsRegistered`.
- Map singleton guard: existing instance reuse for same element + teardown on DOM target drift.

### Hooks that must survive navigation/reconnect

- `courier:runtime-sync`, `courier-online-toggled`, `courier:online`, `courier:offline`.
- `livewire:navigated` + Livewire morph hooks for map remount/recover.
- auth-loss teardown (`poof:auth-session-lost`) followed by canonical runtime recovery path.

## Canonical runtime observability markers

High-signal markers that are now treated as canonical for incident triage:

- `ui_runtime_bootstrap_started` / `ui_runtime_bootstrap_livewire_started` / `ui_runtime_bootstrap_alpine_started`;
- `ui_runtime_bootstrap_skipped` (`reason=duplicate_guarded`) for duplicate-boot guard evidence;
- `cross_tab_runtime_sync_repair_applied` (runtime self-heal triggered);
- `optimistic_runtime_state_overridden` (optimistic courier state overridden by canonical snapshot);
- `map_bootstrap_rejected` (`reason=invalid_payload|invalid_or_stale_coords`);
- `reverse_geocode_degraded` (`reason=http_not_ok|empty_result|request_failed`);
- `ui_save_flow_started` / `ui_save_flow_succeeded` / `ui_save_flow_failed` with explicit `boundary=before_persistence|after_persistence`.

### Logging boundaries

- **Bootstrap/runtime layer** logs only runtime guards, self-heal markers, and degraded map/geocode states.
- **Application action boundary** logs save-flow lifecycle around persistence boundaries (`profile`, `avatar`, `address`).
- **Must not log from UI-only state**:
  - free-form address text, profile fields, phone/email, avatar blob metadata;
  - verbose stack traces in expected validation flows.
- **Required structured fields** for UI/runtime markers:
  - `flow` (when action-boundary marker),
  - `boundary` (`before_persistence`/`after_persistence` for save flows),
  - `reason` (normalized compact reason code),
  - `user_id` (if authenticated),
  - minimal booleans/counters needed for triage (`has_coordinates`, `canonical_online`, etc).

### Operator diagnostics surface

- Admin API endpoint: `GET /api/admin/runtime-diagnostics` (web auth + admin only).
- Returns lightweight runtime envelope:
  - `runtime_mode`,
  - `queue_driver`,
  - `cache_driver`,
  - latest `release_summary` file metadata.
- Endpoint is intentionally minimal and excludes user/session/UI payloads.

### PR observability hook (runtime-heavy changes)

For runtime-heavy PRs touching Livewire/Alpine/map/geocode/save flows, include:

1. at least one canonical marker for critical failure/degraded path;
2. one marker proving self-heal/guard activation where applicable;
3. targeted tests asserting marker emission and no duplicate-noise logging.

## Legacy ambiguities (remaining)

- Cross-tab runtime sync still transports hint payloads without strict versioned schema enforcement.
- `busy` is still mirrored in both `users` flags and inferred from active order; repair reconciles this, but storage remains duplicated.

## Dispatch/read hot-path reference

- Dispatch and courier cabinet hot-path boundaries, query model, scoring split (SQL vs PHP), indexes and observability markers are documented in `docs/courier-dispatch-read-flow.md`.

## Transition layer invariants (enforced)

- `offline -> online`: only when courier has no active order conflict; writes `status=online`, `is_online=true`, `is_busy=false`, `session_state=ready`.
- `online -> assigned`: only through order accept / active-order reconciliation; writes `status=assigned`, `is_online=true`, `is_busy=true`, `session_state=assigned`.
- `assigned -> delivering`: only through order start / active-order reconciliation; writes `status=delivering`, `is_online=true`, `is_busy=true`, `session_state=in_progress`.
- `delivering -> online`: only after order completion/cancel flow with no active order; writes `status=online`, `is_online=true`, `is_busy=false`, `session_state=ready`.
- `online -> offline`: allowed only when no active order; forced offline attempts with active order are ignored and repaired back to assigned/delivering.
- Login reset + stale sweep (`ResetCourierSessionOnLogin`, `MarkInactiveCouriers`) always call repair first and cannot break active courier into false offline/free state.

## Regression suite (courier)

- online toggle/sync/navigation/session stability:
  - `tests/Feature/Courier/CourierOnlineToggleActionTest.php`
  - `tests/Feature/Courier/AvailableOrdersOnlineSyncTest.php`
  - `tests/Feature/Courier/CourierOnlineNavigationSyncTest.php`
  - `tests/Feature/Courier/CourierOnlineSessionStabilityTest.php`
- busy/accept/start/complete lifecycle:
  - `tests/Feature/Courier/CourierBusyUxFlowTest.php`
  - `tests/Feature/Courier/AcceptFlowArchitectureRegressionTest.php`
  - `tests/Feature/Api/CourierAcceptFlowParityTest.php`
- map bootstrap/runtime observability:
  - `tests/Feature/Courier/CourierMapBootstrapLoggingTest.php`
  - `tests/Unit/Frontend/mapLocationBootstrap.test.js`
- contract-focused additions:
  - `tests/Feature/Courier/CourierRuntimeSnapshotApiTest.php`
  - `tests/Feature/Courier/AvailableOrdersOnlineSyncTest.php`
  - `tests/Feature/Courier/CourierRuntimeComponentConsistencyTest.php`
