# Role entrypoints architecture

## Domain split

- `app.poof.com.ua` — client-first contour (landing, register, login, client cabinet/orders).
- `courier.poof.com.ua` — courier contour (landing, register, login, courier dashboard/orders).

## Routing and auth

- Root `/` now resolves landing by host:
  - client host -> `resources/views/welcome.blade.php`
  - courier host -> `resources/views/welcome-courier.blade.php`
- Dedicated auth entrypoints:
  - client login: `/login`
  - courier login: `/courier/login`
  - client register: `/register`
  - courier register: `/courier/register`
- Registration role is no longer mixed in UI selector.
  - client route always creates `role=client`
  - courier route/host always creates `role=courier`

## Cross-links inside one ecosystem

- Client landing contains secondary CTA to `https://courier.poof.com.ua`.
- Courier landing contains secondary CTA to `https://app.poof.com.ua`.
- Client register form contains link to courier register flow.
- Courier register form contains link to client register flow.

## Session expired and redirects

- `Authenticate` middleware redirects unauthenticated users to role-aware login:
  - `/client/*` -> `/login?next=...`
  - `/courier/*` -> `/courier/login?next=...`
- Login now respects `next` only inside user's own role-space:
  - clients cannot be redirected to `/courier/*`
  - couriers cannot be redirected outside `/courier*`

## PWA/install surfaces (logic split)

- Role-specific manifests exposed via routes:
  - `/manifest-client.json` (`start_url=/client`)
  - `/manifest-courier.json` (`start_url=/courier`)
- Client and courier landings point to different manifest links.

## Asset work intentionally excluded from this PR

This PR intentionally **does not modify/add** binary assets or static image/icon files.

Follow-up manual asset-only PR should provide:

- courier-specific icon files (e.g. `/assets/icons/courier-192.png`, `/assets/icons/courier-512.png`)
- client/courier dedicated install screenshots for manifest `screenshots`
- optional role-specific splash/icon pack for store-like install polish

Current manifests keep existing icon references as temporary placeholders to avoid binary diff noise.
