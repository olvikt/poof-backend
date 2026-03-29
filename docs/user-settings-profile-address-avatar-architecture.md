# User settings architecture note: profile / address / avatar

## Scope

This note fixes a single implementation pattern for three existing user settings flows:

- profile edit;
- avatar upload;
- address create/edit.

Product behavior and user-visible contracts stay unchanged.

## Audit summary (before unification)

### Profile flow (`App\Livewire\Client\ProfileForm`)

- **Orchestration**: in Livewire component (`save`, `loadUser`).
- **Form state**: Livewire public fields (`name`, `phone`, `email`).
- **Persistence**: inline `auth()->user()->update(...)` in component.
- **UI-only sheet state**: sheet close via dispatched browser event without explicit sheet name.
- **Events/dispatch**: `profile-saved` + generic `sheet:close`.
- **Inconsistency**: orchestration + persistence mixed in one class.

### Avatar flow (`App\Livewire\Client\AvatarForm`)

- **Orchestration**: in Livewire component (`save`).
- **Form state**: Livewire upload field (`avatar`).
- **Persistence**: inline upload + update in component.
- **UI-only sheet state**: sheet close via generic `sheet:close`.
- **Events/dispatch**: `avatar-saved` + generic `sheet:close`.
- **Inconsistency**: orchestration + persistence mixed in one class.

### Address flow (`App\Livewire\Client\AddressForm` + `AddressManager`)

- **Orchestration**: Livewire manager/form components and sheet events.
- **Form state**: Livewire fields in `AddressForm`.
- **Persistence**: dedicated action `PersistClientAddressAction` + DTO/payload services.
- **UI-only sheet state**: explicit `sheet:open/close` events (named sheet).
- **Events/dispatch**: `address-saved` and map/browser events.
- **Inconsistency**: this flow already separates persistence better than profile/avatar.

## Chosen unified pattern

**Pattern: full Livewire orchestration with explicit persistence actions + DTO input objects.**

Rationale:

1. All three flows are already Livewire-driven in production UI.
2. Existing regression contracts are event-centric (`*-saved` + sheet lifecycle), so Livewire orchestration is the least disruptive path.
3. Address flow already demonstrates clear separation (`form state` -> `action`), so profile/avatar were aligned to it.

## Responsibility model (target)

For each flow:

1. **Form component**: owns field state + validation (`ProfileForm`, `AvatarForm`, `AddressForm`).
2. **Manager/orchestrator**: component method orchestrates save sequence + emits UI/domain events.
3. **Persistence action**: single action class performs write (`PersistClientProfileAction`, `PersistClientAvatarAction`, `PersistClientAddressAction`).
4. **UI-only component/state**: bottom sheets remain in Blade, lifecycle through named `sheet:*` events.
5. **Domain validation/policy**: validation rules/policies stay in component + existing address policy services.

## Contract conventions fixed

- Keep `*-saved` domain-facing Livewire events as observable contract surface.
- Use **named** sheet close events per flow:
  - profile -> `sheet:close` with `name: editProfile`;
  - avatar -> `sheet:close` with `name: editAvatar`;
  - address -> `sheet:close` with `name: addressForm`.
- No controller redirect introduced into these three save flows.

## Guidance for new user-settings flows

When adding a similar flow:

1. Build a dedicated Livewire form component.
2. Keep persistence in an `App\Actions\...\Persist...` action.
3. Pass normalized input via a small DTO.
4. Emit one `*-saved` event and close a **named** sheet event.
5. Keep UI state in Blade/Alpine sheets; keep persistence/domain logic out of Blade/UI glue.

## Legacy exceptions intentionally kept

- `AddressForm` still dispatches map/browser synchronization events because map integration is part of existing runtime contract.
- `AddressManager` still uses redirects for "order from address" actions; this is outside profile/avatar/address save contract and not part of this unification.
