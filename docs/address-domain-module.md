# Address domain module note

## Scope and intent

This note documents the extracted **Address domain module** and clarifies boundaries between domain, application, and UI/runtime layers.
The extraction keeps current UX and observable behavior intact.

## Current responsibilities audit (before extraction)

| Responsibility | Previous primary locations |
|---|---|
| Address parsing | `ResolveAddressFromPoint`, `ResolveAddressPointFromFields`, `PrepareAddressSavePayload`, `AddressForm` string normalization |
| Precision semantics | `App\Support\Address\AddressPrecision`, `AddressCoordinatePolicy`, `OrderCreate` traits, `AddressForm` |
| Coordinate trust / stale policy | `AddressForm::shouldIgnoreIncomingCoords`, `AddressCoordinatePolicy`, map-related guards in Livewire |
| Geocode / reverse-geocode | `ResolveAddressPointFromFields`, `ResolveAddressFromPoint`, `HandlesGeocodingMapSync`, `/api/geocode` calls |
| Marker sync contract | Event names and source checks in `AddressForm` + `OrderCreate` concerns |
| Saved address hydrate/select | `HandlesAddressSelection`, `AddressForm::loadAddress`, `AddressManager` |
| Create/edit persistence rules | `PrepareAddressSavePayload`, `FilterClientAddressPayload`, `PersistClientAddressAction` |

## What is now in Address domain module

`app/Domain/Address` now contains the domain-facing semantics:

- `AddressParser`
  - canonical search normalization;
  - house/street normalization;
  - search-part splitting and extraction.
- `Precision` (domain precision model).
- `CoordinateTrustPolicy`
  - precision assignment by coordinate origin;
  - stale geolocation ignore policy;
  - exact-point overwrite guard.
- `MarkerSyncContract`
  - accepted marker sync sources;
  - source-to-precision mapping.
- `Contracts/GeocodeContract`
  - domain geocode/reverse-geocode interface.

## What is explicitly NOT part of Address domain

- sheet/modal open-close lifecycle;
- Livewire UI state (`isAddressSearchOpen`, active suggestion index, etc.);
- browser event orchestration beyond domain marker contract;
- widget/bootstrap rendering logic.

## Layer boundaries

- **Domain rules**: `app/Domain/Address/*`.
- **Application actions/use cases**:
  - `PersistClientAddressAction`;
  - `AddressGeocoding` (adapter over geocode/reverse services);
  - address payload preparation/filtering services.
- **Form/input DTOs**: `app/DTO/Address/*`.
- **UI/runtime orchestration**: Livewire components/concerns + browser event dispatching.

## Construction compatibility contract

- Preferred pattern in runtime code: resolve address services through the DI container (`app(...)` / constructor injection).
- `PrepareAddressSavePayload` additionally supports manual `new PrepareAddressSavePayload()` for test/utilities backward compatibility.
- Manual instantiation with explicit parser (`new PrepareAddressSavePayload(new AddressParser())`) is also supported.
- Other extracted services (`ResolveAddressFromPoint`, `ResolveAddressPointFromFields`, `AddressGeocoding`) are expected to be container-resolved.

## Source of truth

- Coordinates + precision source-of-truth is the pair:
  - coordinate values on the active form/runtime state;
  - precision computed only via `CoordinateTrustPolicy` / `MarkerSyncContract`.

## Legacy exceptions kept intentionally

- `App\Support\Address\AddressPrecision` and `AddressCoordinatePolicy` remain as compatibility wrappers.
- Existing Livewire/browser event names are unchanged for regression safety.

## Changelog

- Extracted address parsing, precision and trust rules into `app/Domain/Address`.
- Refactored `AddressForm` and `OrderCreate` address concerns to consume domain module.
- Refactored address services to use shared domain parser.
- Added domain-focused unit tests and strengthened edit single-record contract test.

## Risk assessment

- **Low functional risk**: behavior-preserving extraction with compatibility wrappers.
- **Main risk**: accidental divergence between old support wrappers and domain rules; mitigated by shared delegation.
- **Operational risk**: low, no schema/API contract changes, no new UX paths.

## How to extend consistently

1. Add/adjust semantics in `app/Domain/Address` first.
2. Keep DTOs shape-compatible; adapt in application services/actions.
3. Let Livewire consume domain contracts; avoid embedding parsing/policy logic in component methods.
4. Add targeted tests at domain level, then one integration regression at Livewire level.
