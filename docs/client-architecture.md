# Client runtime architecture hardening (POOF)

## Цели

- удержать рабочее продуктовое поведение без rewrite;
- снизить вероятность Livewire/Alpine regressions;
- зафиксировать единый interaction contract для order/profile/address/avatar flows.

## Runtime boundary

### 1) Livewire component responsibilities

- `OrderCreate` — orchestration only: состояние страницы, вызовы action/services, UI events.
- `AddressForm` — address editor + map point contract + precision policy.
- `ProfileForm` / `AvatarForm` — isolated write forms (single-save actions).

### 2) Transport contract (Livewire ↔ JS)

- Доменные действия должны вызываться через component action (`$wire` / `component.call`) при наличии компонента.
- `window.Livewire.dispatch(...)` допускается как fallback/backward-compat только для кросс-компонентных и bootstrap edge-cases.
- Browser events используются только для визуальных side-effects (sheet open/close, map marker updates, date picker UI).

## OrderCreate decomposition

`app/Livewire/Client/OrderCreate.php` разложен на concern-слои:

- `HandlesAddressSelection` — saved address и address-book/repeat hydration;
- `HandlesGeocodingMapSync` — geocode/reverse-geocode и map marker sync;
- `HandlesScheduleSlots` — дата/слоты;
- `HandlesPricingTrialPolicy` — bags/trial/price/coordinate guard;
- `HandlesOrderSubmission` — submit orchestration.

Это уменьшает blast radius: изменение map/geocode логики больше не затрагивает submit или trial policy.

## Unified profile/address/avatar flow pattern

Одинаковый паттерн для всех client forms:

1. `load/open` step (инициализация формы из source of truth).
2. локальная валидация в компоненте.
3. один write action (`save`).
4. UI-only events (`sheet:*`, `*-saved`) после успешного persist.

## Regression suite map (critical client paths)

- Order create invariants: `tests/Feature/Livewire/OrderCreateAddressInvariantsTest.php`.
- Address form save/set-coords/search-modal: `tests/Feature/Livewire/AddressForm*Test.php`.
- Profile contracts: `tests/Unit/Profile/ClientProfileWriteContractTest.php`.
- Frontend order-create transport fallback/preference: `tests/Unit/Frontend/orderCreateBootstrap.test.js`.
