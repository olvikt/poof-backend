# Courier runtime architecture contract

## Source of truth

1. **Server runtime snapshot** (API + Livewire courier components) — canonical state for:
   - online/offline;
   - active order;
   - busy state;
   - availability lists.
2. **Client map runtime state** — presentation cache and optimistic UI only.

## Online/offline contract

- Переключение online/offline инициируется через Livewire action (`OnlineToggle`).
- После toggle клиент обязан синхронизировать:
  - available orders list;
  - my-orders list;
  - busy indicator.
- При divergence клиент должен выполнить auto-resync polling и восстановить server truth.

## Active order / busy state contract

- `active_order_id != null` => courier busy;
- busy courier не может принимать новый unrelated offer;
- start/complete transitions выполняются только через lifecycle actions и валидируются статусными guards.

## Polling + optimistic sync rules

- Polling работает как consistency loop, не как первичный source of truth.
- Optimistic UI допустим только до ближайшего успешного sync;
- при конфликте optimistic vs server — побеждает server snapshot.

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
