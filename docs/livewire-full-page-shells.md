# Full-page Livewire orchestration shells

## Shell components (current baseline)

- `App\Livewire\Client\OrderCreate`
- `App\Livewire\Client\AddressForm`
- `App\Livewire\Courier\AvailableOrders`
- `App\Livewire\Courier\MyOrders`

These components are treated as orchestration shells: they coordinate runtime state, dispatch UI events, and delegate domain/application semantics to actions/services/DTOs.

## What stays in a full-page shell

- page-level routing/mount wiring;
- canonical runtime re-sync from backend snapshots;
- browser event dispatching;
- minimal composition of pre-extracted concerns.

## What must be extracted

Extract to concerns/actions/services/DTOs when logic is:

- domain/application semantics (validation policy, persistence, lifecycle state changes);
- reusable runtime policy shared by multiple components;
- dense UI-only state machines (modal/sheet/suggestions) that obscure business flow.

## Practical rule of thumb for PRs

If a full-page component starts mixing 3+ responsibility groups (or grows beyond ~250–300 LOC), keep it as a shell and split by concern boundaries before adding new behavior.

Keep UI-only state separate from persistence/domain semantics, and do not re-introduce transport/runtime magic that bypasses existing contracts.
