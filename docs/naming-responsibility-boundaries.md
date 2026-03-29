# Naming & responsibility boundaries

## Glossary (canonical roles)

- **Form component** (`*Form`): isolated UI write-flow component (`load/validate/save`), no heavy integration/persistence logic inline.
- **Manager/Orchestrator component** (`*Manager`, full-page shell): coordinates UI state and routing between forms/concerns.
- **Application action** (`*Action`): write-side use-case boundary (state mutation/persistence, often transactional).
- **Domain policy/rule** (`*Policy`, enum rule/value object): pure domain decision logic, no transport/UI concerns.
- **Runtime helper** (`*Runtime*`, `*Support*`): UI/runtime projection and deterministic helper logic, no source-of-truth mutation.
- **Controller** (`*Controller`): transport boundary only (auth + delegation + response mapping).

## Practical naming rules

1. Classes in `app/Actions/**` must end with `Action`.
2. Livewire forms handling direct write interactions must end with `Form` and delegate persistence to actions.
3. Livewire coordination components without direct form-write responsibility should use `Manager` or explicit shell names.
4. Domain decision classes use `Policy` / rule-like names; infra abstractions keep `Contract` only at domain boundary.
5. Controllers must not embed lifecycle mutations directly when a canonical action exists.
6. Concern traits (`Handles*`) are allowed only for component-internal orchestration slices; promote to class/service if reused across components or if they own integration/persistence logic.

## Do / Don't examples

- **Do:** `PersistClientProfileAction` called from `ProfileForm::save()`.
- **Don't:** keep write-side class in `app/Actions` without `Action` suffix.
- **Do:** `CourierOrderLifecycleController` delegates to `AcceptOrderByCourierAction`.
- **Don't:** controller performing inline lifecycle transitions.

## Legacy exceptions intentionally kept

- `Order::acceptBy/startBy/completeBy/cancel` method names remain unchanged for public/canonical domain API compatibility.
- Existing `Services/Address/*` wrappers keep current naming to avoid broad churn while contracts stay explicit via DTO and `GeocodeContract`.

## Guard coverage

- `tests/Unit/Architecture/NamingResponsibilityBoundaryArchitectureTest.php` pins critical naming and delegation boundaries for client form write-actions.
- Existing courier lifecycle architecture tests pin controller-to-action thin boundary and action-level transactional ownership.
