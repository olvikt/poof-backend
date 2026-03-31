# PWA subsystem contract

This document formalizes the current PWA subsystem so that service worker behavior, install UX, and deployment cache rules stay aligned.

## A. Current PWA audit

### Manifest

- Manifest is served from `public/manifest.json` and linked from the landing page via `<link rel="manifest" href="/manifest.json">`.
- The manifest declares:
  - `start_url: "/client"`
  - `scope: "/"`
  - `display: "standalone"`
  - PWA icons under `/assets/icons/...`
  - shortcuts into authenticated client routes.

### Service worker registration

- The service worker is registered in `resources/js/app.js`.
- Registration happens on `window.load` and points to `/sw.js`.
- Registration is passive: there is no custom update UI and no extra runtime orchestration beyond the browser lifecycle.

### Service worker cache behavior

Current `public/sw.js` contract:

- Cache version is explicit via `CACHE_VERSION`.
- Install-time pre-cache contains only:
  - `/`
  - `/manifest.json`
- Activate step deletes all old caches whose key does not match the current static cache name.
- Runtime cache is allowed only for same-origin image/icon-like assets:
  - `/images/`
  - `/assets/images/`
  - `/assets/icons/`
- Requests are explicitly excluded from service-worker caching when the path starts with:
  - `/api/`
  - `/build/`
- Non-GET requests are ignored by the service worker.

### Install UX

Current install surfaces live in `resources/views/welcome.blade.php`:

- Manifest and mobile/PWA meta tags are wired into the landing page.
- A hidden install CTA button (`#installAppBtn`) becomes visible only after `beforeinstallprompt` fires.
- A mobile install banner (`#installBanner`) also becomes visible only after `beforeinstallprompt`, and only when:
  - viewport matches mobile breakpoint (`max-width: 768px`)
  - app is not already in standalone display mode.
- Both install surfaces consume the same deferred `beforeinstallprompt` event.
- Closing the banner hides it for the current page view only.

### Deploy/cache interaction

- Frontend assets are loaded through Vite helpers, so HTML references the current `public/build/manifest.json` output.
- Built Vite files under `/build/assets/` are hashed and are expected to be immutable.
- `sw.js` intentionally does not cache `/build/` requests, so service-worker storage cannot pin old hashed bundles.
- `docs/deployment/nginx-cache.md` already requires HTML revalidation and long-lived immutable caching for `/build/assets/`.

## B. Canonical PWA contract

### 1. Cache policy

#### Pre-cache targets

The install-time cache is intentionally minimal and currently limited to:

- `/`
- `/manifest.json`

Reasoning:

- `/` preserves the basic landing shell/install entrypoint for repeat visits and offline fallback at a minimal scope.
- `/manifest.json` keeps install metadata available without depending on runtime network state.
- Keeping pre-cache small reduces deploy risk and avoids pinning old application bundles.

#### Runtime-cache targets

Runtime caching is limited to same-origin visual assets used by the landing/PWA shell:

- `/images/`
- `/assets/images/`
- `/assets/icons/`

The strategy is effectively cache-first with network refresh:

- return cached asset when available;
- otherwise fetch from network;
- on successful `200` response, store the response in the current static cache.

#### Never cache via service worker

The following paths are never handled by the service worker cache:

- `/api/`
- `/build/`

Reasoning:

- `/api/` must always reflect fresh server state and must not be hidden behind stale client-side cache.
- `/build/` contains hashed Vite outputs whose freshness is already controlled by filename versioning plus server cache headers; duplicating them in service-worker cache would create deploy rollback/update hazards.

#### Activate/cleanup contract

- Every service-worker change that should invalidate prior PWA caches must update `CACHE_VERSION` in `public/sw.js`.
- On activation, all cache buckets except the current one are deleted.
- This is the canonical cache invalidation mechanism for service-worker managed assets.

#### Future change rule

Bump `CACHE_VERSION` when any of the following changes affect what the worker serves:

- pre-cache URL list;
- runtime-cache target rules;
- fetch strategy logic;
- landing shell content that must not rely on the previous worker-managed cache;
- manifest/install assets whose old cached copies would be user-visible or misleading.

Do not rely on Vite hashed filenames alone to invalidate service-worker-managed caches for `/`, `/manifest.json`, images, or icons.

### 2. Install contract

#### Supported install surface

The current supported install flow is the landing page install experience in `resources/views/welcome.blade.php`.

Install UI exists in two forms:

- a general install button in the landing content;
- a mobile install banner near the bottom of the viewport.

No other install surface is currently part of the subsystem contract.

#### When install UI may appear

Install UI appears only if the browser fires `beforeinstallprompt`.

Expected behavior:

- If `beforeinstallprompt` does not fire, install controls remain hidden and this is not considered a bug by itself.
- The button may appear on supported browsers/platforms that allow deferred prompting.
- The banner may appear only on mobile-sized viewports and only when the app is not already running in standalone mode.
- In standalone mode, the banner is expected to stay hidden.

#### Platform limitations

`beforeinstallprompt` is browser-dependent and not guaranteed across all platforms.

This means:

- install affordances are opportunistic, not guaranteed;
- unsupported browsers may still be valid PWA consumers without showing the custom install prompt;
- iOS/mobile standalone behavior may differ because install flow support is browser-specific.

Those limitations are expected behavior for the current subsystem.

### 3. Deploy behavior contract

The PWA subsystem depends on three layers staying consistent:

1. HTML responses must be revalidated on each request.
2. Vite `/build/assets/...` files must stay content-hashed and immutable.
3. Service worker must not cache `/build/` or `/api/`.

Combined effect:

- after deploy, fresh HTML points to the latest Vite manifest entries;
- old hashed bundles may remain browser-cached safely because their URLs change;
- service-worker cache cannot keep outdated build assets alive;
- explicit service-worker cache version bumps clear old PWA-managed cache entries when worker behavior changes.

## C. Minimal changes made

- Added this subsystem contract document.
- Linked deployment cache guidance to this document so Nginx/Vite/service-worker rules are documented together.

## D. PWA regression checklist

Run this checklist after deploy and after any change to `public/sw.js`, `public/manifest.json`, landing install UI, or the Vite asset pipeline.

### Required checks

1. `GET /manifest.json` returns `200` and valid JSON.
2. `GET /sw.js` returns `200` and the browser registers the worker successfully.
3. Landing page still includes `<link rel="manifest" href="/manifest.json">`.
4. Install button stays hidden until `beforeinstallprompt` is available.
5. Mobile install banner appears only on mobile-sized landing viewports and not in standalone mode.
6. Requests to `/api/...` are not stored in service-worker cache.
7. Requests to `/build/...` are not stored in service-worker cache.
8. After deploy, the page resolves the new `public/build/manifest.json` outputs correctly.
9. HTML is revalidated so the browser does not keep serving stale markup that references removed hashed assets.
10. Offline/repeat-visit behavior still preserves a basic landing load/install surface without breaking the page shell.

### Canonical operator smoke runner

Run `bash scripts/check-pwa.sh` on the deployed host when you need the narrow HTTP/rendered-response portion of the checklist.

The script intentionally automates only the stable post-deploy checks that are safe to verify with shell + `curl`:

- `GET /manifest.json` returns `200`;
- `/manifest.json` decodes as valid JSON;
- `GET /sw.js` returns `200`;
- landing HTML contains `<link rel="manifest" href="/manifest.json">`;
- landing HTML references the current Vite output paths from local `public/build/manifest.json`;
- landing HTML headers expose an observable revalidation signal such as `Cache-Control: no-cache`, `Cache-Control: max-age=0`, `ETag`, or `Last-Modified`.

Configuration:

- `APP_BASE_URL` is the canonical target and defaults to `https://app.poof.com.ua` (UI domain);
- UI smoke checks must target `app.poof.com.ua` so HTML/assets stay same-origin; API health remains `https://api.poof.com.ua/up`.
- `APP_DIR` defaults to `/var/www/poof`;
- `PHP_BIN` and `CURL_BIN` may be overridden if the host uses non-default binaries.

### Automated vs manual boundary

Current narrow automated regression coverage intentionally stays deterministic and source-contract focused:

- automated file-level/unit coverage:
  - `public/manifest.json` exists and is valid JSON;
  - manifest core contract remains aligned for `id`, `name`, `short_name`, `start_url`, `scope`, `display`, theme/background colors, icons, and shortcut URLs;
  - `resources/js/app.js` still registers `/sw.js` on `window.load`;
  - `public/sw.js` still keeps explicit `CACHE_VERSION`;
  - `public/sw.js` still excludes `/api/` and `/build/` from cache handling.
- automated rendered-page coverage:
  - landing page response still includes `<link rel="manifest" href="/manifest.json">`.

The following checks remain manual/browser-level because stable automation would require a real browser/runtime environment and would be disproportionately wide for the current gate:

- successful browser registration of `/sw.js`;
- install button/banner visibility behavior around `beforeinstallprompt`, viewport size, and standalone mode;
- service-worker cache inspection for `/api/...` and `/build/...` in a real browser;
- offline/repeat-visit shell behavior;
- install prompt/platform-specific UX validation across supported browsers.

## E. Address picker top chrome limitation note

- The client address picker intentionally extends the map into the safe-area using `viewport-fit=cover`, safe-area offsets, and an overdrawn map container.
- If a dark strip still remains at the very top on mobile, treat it as browser/system chrome first, not as proof of a local picker gap.
- Ordinary in-browser mode and standalone PWA mode can paint the top area differently:
  - browser mode may still reserve/tint address-bar chrome outside page CSS control;
  - standalone mode can follow manifest/meta theme colors, but that still does not guarantee true transparent status-bar rendering on every platform/browser combination.
- In this project, the safe fallback is to keep maximum map continuity under the top area, tune `theme-color` close to the map hero, and use a soft fade rather than risky layout rewrites chasing true transparency.

### When to bump `CACHE_VERSION`

Bump `CACHE_VERSION` before deployment when:

- pre-cache URLs changed;
- runtime cache rules changed;
- cached icons/images changed and stale copies would be confusing;
- the cached landing/install shell changed in a way that should evict the previous worker cache immediately.

## E. Out of scope / follow-up notes

This contract does **not** introduce:

- a broader offline-first redesign;
- richer install analytics or custom prompt tracking;
- expanded install UI beyond the current landing page flow;
- SPA/PWA architecture changes outside the existing Laravel + Vite setup.
