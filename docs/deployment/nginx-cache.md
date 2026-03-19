# Nginx cache strategy for Laravel + Vite deployments

Use this configuration to avoid stale HTML pointing to old hashed Vite files.

For the full PWA/service-worker/install contract that builds on these cache rules, see `docs/pwa-subsystem.md`.

```nginx
location /build/assets/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
    add_header Cache-Control "no-cache";
}
```

## Why this works

- `index.php`/HTML responses are revalidated on each request, so Blade always references the latest Vite manifest.
- Vite hashed assets under `/build/assets/` are immutable and can be cached for up to a year.
- The PWA service worker intentionally does not cache `/build/` requests, so deploy freshness remains controlled by HTML revalidation plus Vite hashed filenames.

## Deployment pipeline

Run these steps on each deployment:

```bash
git pull
npm ci
npm run build
php artisan optimize:clear
```

## Verification checklist

1. Blade templates load assets via Vite helper:
   - `@vite(['resources/css/app.css','resources/js/app.js'])`
2. No direct hardcoded references to `/build/assets/*.js` or `/build/assets/*.css` in Blade templates.
3. `public/build/manifest.json` exists after `npm run build`.
4. Service worker does not cache `/build/assets` requests.
5. If `public/sw.js` changed in a way that affects cached shell/assets, `CACHE_VERSION` was bumped before deploy.
