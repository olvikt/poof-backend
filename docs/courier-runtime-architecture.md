# Courier runtime architecture contract

## Source of truth

1. **Server runtime snapshot** (`User::courierRuntimeSnapshot()`) — canonical source for courier runtime.
2. **Client map runtime state** (`resources/js/poof/map.js`) — projection/observability cache only.
3. **Livewire courier components** (`AvailableOrders`, `MyOrders`, `OnlineToggle`) must re-read snapshot and self-heal to server truth.

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

## Legacy ambiguities (remaining)

- Cross-tab runtime sync still transports hint payloads without strict versioned schema enforcement.
- `busy` is still mirrored in both `users` flags and inferred from active order; repair reconciles this, but storage remains duplicated.

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
