# AddressForm baseline before refactor

This document captures the current `AddressForm` behavior as a **source of truth before extraction/refactor**. The goal is to preserve behavior, side effects, and persistence contracts, not to redesign the flow.

## Scope

- Primary baseline: `app/Livewire/Client/AddressForm.php`.
- Secondary reference only: `app/Livewire/Client/OrderCreate.php`.
- `OrderCreate` is used here only to confirm neighboring address invariants already present in the codebase; it does **not** expand the refactor scope.

## Current entrypoints in address flow

### `open(?int $addressId = null)`

Current behavior:

1. Stores the incoming `addressId` in component state.
2. If `addressId` is present, delegates to `loadAddress($addressId)`.
3. Otherwise resets the form with `resetForm()`.
4. Dispatches `sheet:open` with `name: 'addressForm'`.
5. If coordinates already exist after open/load, dispatches `map:set-marker` again to re-sync the marker after map mount.

Why this matters:

- `open()` is both the sheet entrypoint and the last-mile marker resync guard.
- The post-open `map:set-marker` dispatch is intentionally redundant with `loadAddress()` and must be preserved unless the lifecycle is reworked carefully.

### `loadAddress(int $id)`

Current behavior:

1. Loads only the authenticated user's `ClientAddress`.
2. Copies persisted address data into Livewire state, including:
   - label/title/building_type
   - normalized `search`
   - `lat`/`lng`
   - apartment details
   - `city`/`region`/`street`/`house`
3. Clears autocomplete UI state: `suggestions`, `activeSuggestionIndex`, `suggestionsMessage`.
4. If coordinates exist, dispatches `map:set-marker` so JS can place the marker.

Why this matters:

- Edit mode is hydrated from DB state directly.
- Coordinates are treated as already trusted and immediately pushed back into map sync.

### `updatedSearch($value = null)`

Current behavior:

1. Normalizes the incoming value through `normalizeSearch()`.
2. Writes the normalized string back into `$search` if needed.
3. If the normalized search text is shorter than 3 characters, it:
   - clears `suggestions`
   - resets `activeSuggestionIndex` to `-1`
   - clears `suggestionsMessage`

Important non-behavior:

- This method does **not** geocode.
- This method does **not** mutate `lat`/`lng`.
- This method only normalizes the input and resets autocomplete UI state for short text.

### `selectSuggestion(int $index)`

Current behavior:

1. Reads the chosen item from `suggestions`; ignores invalid indexes.
2. Clears `place_id`.
3. Sets `search` from `label` or `line1`, normalized via `normalizeSearch()`.
4. Copies `lat`/`lng` from the suggestion into state.
5. Copies address fragments into form state when available:
   - `street`
   - `house`
   - `city`
   - `region`
6. Clears autocomplete UI state.
7. If coordinates are present, dispatches both:
   - `map:set-location` with `source: 'autocomplete'`, `zoom: 17`
   - `map:update` with `zoom: 17`

Why this matters:

- Autocomplete selection is a full state transfer, not just a search-field update.
- Map synchronization currently depends on **both** map events being emitted.

### `updatedHouse()`

Current behavior:

1. If `updatingHouseFromMap` is `true`, returns immediately.
   - This is the guard that prevents map-originated autofill from being reinterpreted as manual input.
2. Marks `houseTouchedManually = true`.
3. Exits early if trimmed `house` is empty.
4. Builds `street` and `city` from current state.
5. If `street` is empty but `search` exists, falls back to parsing `search` by comma:
   - first segment → `street`
   - second segment → `city`, but only if `city` is still empty
6. If `street` is still empty, exits without geocoding.
7. Builds a forward-geocode query: `street, house[, city]`.
8. Calls `/api/geocode` with:
   - `q` = built query
   - current `lat`
   - current `lng`
9. On a successful response with a first result containing coordinates:
   - updates `lat`/`lng`
   - dispatches `map:set-marker`
10. Swallows network/runtime errors silently.

Why this matters:

- House edits are treated as the manual trigger for forward geocoding.
- Existing coordinates are sent into `/api/geocode`, so the current flow may bias geocode selection around the current point.
- This method updates the marker directly but does **not** dispatch `map:update`.

### `setCoords(float $lat, float $lng, ?string $source = null)`

Current behavior:

1. Writes incoming `lat`/`lng` into component state immediately.
2. Clears `place_id`, `suggestions`, `activeSuggestionIndex`, and `suggestionsMessage`.
3. Runs reverse geocoding **only** when `source === 'map'`.
4. For map-originated updates, calls Nominatim reverse geocode.
5. Maps reverse-geocode output into state:
   - `street` from `road` / `pedestrian` / `street`, normalized with `normalizeStreet()`
   - `house` from `house_number`, normalized with `normalizeHouse()`
   - `city` from `city` / `town` / `village`
   - `region` from `state` / `region`
6. Rebuilds `search` from payload label or from `street house, city, region`.
7. Auto-fills `house` only if `houseTouchedManually === false`.
8. Wraps map-originated `house` assignment with `updatingHouseFromMap = true/false` so `updatedHouse()` will ignore it.
9. If `house_number` is missing, attempts a fallback house extraction from `display_name` using regex.
10. Swallows reverse-geocode errors silently.

Why this matters:

- `lat`/`lng` are updated before reverse geocoding and remain the canonical state even if reverse lookup fails.
- Manual house input wins over later map autofill.
- Reverse geocode is intentionally isolated from triggering manual forward geocode through the `updatingHouseFromMap` guard.

### `save()`

Current behavior:

1. Detects create vs update using `addressId`.
2. If `street` is empty but `search` exists, parses `search` by comma:
   - first segment → normalized `street`
   - second segment → `city`, but only if `city` is still empty
3. Validates the form with `rules()`.
4. Enforces coordinates separately even though validation already includes `lat`/`lng` rules:
   - if either coordinate is missing, throws a validation error on `search`
   - error text: `Уточніть точку на мапі.`
5. Builds a payload with form fields, coordinates, and geocoding metadata.
6. Persists apartment-only fields only when `building_type === 'apartment'`; otherwise stores them as `null`.
7. Filters the payload through `filterPersistedPayload()` before writing to DB.
8. Performs either:
   - update of the user's existing address, or
   - create with `user_id`
9. Dispatches post-save events:
   - global `address-saved`
   - targeted `address-saved` to `client.address-manager`
   - `sheet:close` with `name: 'addressForm'`
   - plain `sheet:close`
10. Logs validation failures and unexpected exceptions.
11. Adds a user-visible `search` error on unexpected save failures.

Why this matters:

- `save()` currently contains both business fallbacks and persistence-safety filtering.
- Event fan-out after save is part of the contract, not incidental behavior.

## State and internal guards that must not be lost

### `lat` / `lng` are the current truth

`AddressForm` explicitly labels coordinates as the truth for the address in form state.

Implications:

- `open()` and `loadAddress()` re-sync the marker from stored coordinates.
- `selectSuggestion()` writes coordinates immediately from the suggestion.
- `updatedHouse()` may replace coordinates through forward geocode.
- `setCoords()` writes coordinates immediately from the map before reverse geocode succeeds.
- `save()` refuses to persist an address without coordinates.

### `houseTouchedManually` + `updatingHouseFromMap`

These two guards work together and are mandatory to preserve.

- `houseTouchedManually` flips to `true` on real `updatedHouse()` execution.
- `updatingHouseFromMap` temporarily suppresses `updatedHouse()` when map/reverse-geocode code writes `house` programmatically.
- `setCoords()` only autofills `house` while `houseTouchedManually` is still `false`.

Invariant protected by this pair:

- reverse geocode from the map must not overwrite or reinterpret a user-confirmed house value as if it were fresh manual input.

### `addressColumns` + `filterPersistedPayload()`

This is the persistence boundary guard.

Current behavior:

- `filterPersistedPayload()` lazy-loads DB columns using `Schema::getColumnListing('client_addresses')`.
- Only keys present in the real `client_addresses` table are kept.
- Save uses the filtered payload for both create and update.

Invariant protected by this pair:

- extraction/refactor must not accidentally start persisting transport-only or UI-only fields.

### Post-save events are part of the contract

Current events dispatched after a successful save:

1. `address-saved`
2. `address-saved` targeted to `client.address-manager`
3. `sheet:close` with `name: 'addressForm'`
4. plain `sheet:close`

Required compatibility note:

- The semantics of the first three events explicitly requested for preservation are non-negotiable:
  - `address-saved`
  - `address-saved` in `client.address-manager`
  - `sheet:close`
- The extra plain `sheet:close` currently exists as a compatibility fallback and should not be removed casually without confirming listeners.

## Business rules that must stay behaviorally equivalent

### 1. Street/city fallback from `search`

Current rule:

- If `street` is empty during `updatedHouse()` or `save()`, parse it from `search`.
- `city` is populated from `search` only when it is still empty.

Do not break:

- A refactor must not start overwriting a user-selected `city` with parsed search text.

### 2. Coordinates are always required

Current rule:

- `save()` must fail when `lat` or `lng` is missing.
- The validation error must remain on the `search` field.
- The message must remain `Уточніть точку на мапі.`

Do not break:

- Moving validation into another class/service must preserve both field binding and exact message semantics unless explicitly changed later.

### 3. Apartment-only details are conditional

Current rule:

- `entrance`, `intercom`, `floor`, and `apartment` are stored only when `building_type === 'apartment'`.
- For `house`, those fields are explicitly persisted as `null`.

Do not break:

- A refactor must not leak apartment-specific data into house addresses.

### 4. Reverse geocode must not trigger unwanted manual geocode

Current rule:

- `setCoords(..., source: 'map')` may write `house` from reverse geocode.
- This write is wrapped in `updatingHouseFromMap`, so `updatedHouse()` exits early.

Do not break:

- Map-originated autofill must remain silent with respect to the manual forward-geocode pipeline.

### 5. Autocomplete must continue syncing the map

Current rule:

- `selectSuggestion()` dispatches both `map:set-location` and `map:update` when coordinates exist.

Do not break:

- A refactor must preserve the same map sync side effects unless the frontend event contract is deliberately migrated everywhere.

## Side effects and external integrations

### Network calls

Current `AddressForm` uses two external address-resolution paths:

- Forward geocode in `updatedHouse()`:
  - `GET /api/geocode?q=...&lat=...&lng=...`
- Reverse geocode in `setCoords(..., source: 'map')`:
  - `GET https://nominatim.openstreetmap.org/reverse`

### UI/browser events

Current map/sheet events emitted by `AddressForm`:

- `sheet:open`
- `sheet:close`
- `map:set-marker`
- `map:set-location`
- `map:update`
- `address-saved`

These are observable side effects and should be treated as external contract surfaces.

## Current risk zones to preserve during extraction

1. **Duplicate marker sync on open/edit**
   - Marker dispatch happens in both `loadAddress()` and `open()`.
   - Removing one may change timing relative to map mount.

2. **Coordinate truth vs textual address drift**
   - `lat`/`lng` can be updated even if reverse geocode fails or textual fields remain partial.
   - This asymmetry appears intentional and should be preserved unless separately redesigned.

3. **House manual-input protection is stateful**
   - `houseTouchedManually` is sticky for the form lifetime after manual house edits.
   - Reset behavior currently depends on `resetForm()` / component lifecycle, not on every map change.

4. **Autocomplete and manual geocode use different event paths**
   - Autocomplete dispatches `map:set-location` + `map:update`.
   - Manual house geocode dispatches only `map:set-marker`.
   - Extraction should keep this distinction unless the JS contract is updated together.

5. **Persistence safety depends on runtime schema inspection**
   - Any refactor that bypasses `filterPersistedPayload()` risks widening writes silently.

6. **Silent failure is part of UX**
   - Both `updatedHouse()` and `setCoords()` swallow network/runtime exceptions.
   - Refactor should not suddenly expose noisy failures without a deliberate UX decision.

## Cross-check with `OrderCreate` reference only

`OrderCreate` already contains address invariants that are useful as reference for future extraction, but the current task does not migrate `AddressForm` to that model yet.

### Already present in `OrderCreate`

1. **Explicit address precision states**
   - `address_precision = none | approx | exact`
   - `none`: no coordinates
   - `approx`: geocode-derived point
   - `exact`: confirmed by address book or map/manual point

2. **Programmatic-update guard**
   - `suppressAddressHooks` prevents `updated*` hooks from firing geocode/sync logic during internal state writes.

3. **Field geocode does not overwrite exact coordinates**
   - `geocodeFromFields()` exits early when `address_precision === 'exact'`.

4. **Reverse geocode uses primary + fallback pattern**
   - primary: Google reverse geocode
   - fallback: local `/api/geocode` reverse lookup when street/city are incomplete

### Important conclusion for baseline work

These `OrderCreate` patterns are reference points only. They confirm neighboring invariants worth keeping in mind during extraction, but they do **not** change the current `AddressForm` baseline described above.

## Refactor safety checklist

Before changing `AddressForm`, the refactor should preserve all of the following:

- `open()` still opens the sheet and re-syncs the marker when coordinates already exist.
- Edit mode still hydrates all existing address fields from the current user's saved address.
- `updatedSearch()` still only normalizes search and clears autocomplete UI state for short input.
- `selectSuggestion()` still copies coordinates/address fragments and dispatches `map:set-location` + `map:update`.
- `updatedHouse()` still treats manual house input as the forward-geocode trigger and respects `updatingHouseFromMap`.
- `setCoords(..., source: 'map')` still reverse-geocodes into address fields without breaking manual house input.
- `save()` still falls back from `search`, requires coordinates, filters persisted payload by actual DB columns, and dispatches the same post-save events.
