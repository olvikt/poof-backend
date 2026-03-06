# Poof Backend (Laravel 12)

Poof Backend is a Laravel 12 application with a Vite-powered frontend and PWA capabilities.

## Project architecture

The project follows a backend-first architecture with a modern asset pipeline:

- **Laravel backend** handles routing, domain logic, queues, authentication, and API endpoints.
- **Vite frontend build** compiles CSS/JS assets for optimized production delivery.
- **PWA layer** uses a service worker for selective static caching of images/icons.
- **Operational scripts** under `scripts/` provide deploy, rollback, and server diagnostics.

## Tech stack

- Laravel 12 (PHP 8.3+)
- Vite
- Tailwind CSS
- Alpine.js
- Service Worker (PWA)
- MySQL / Redis
- Nginx (recommended reverse proxy and cache layer)

## Deployment

Production deployment is handled by `scripts/deploy.sh`:

1. Pull latest code from `main`
2. Install PHP dependencies (`composer install --no-dev --optimize-autoloader`)
3. Install frontend dependencies (`npm ci`)
4. Build assets (`npm run build`)
5. Run database migrations (`php artisan migrate --force`)
6. Optimize Laravel (`php artisan optimize`)
7. Restart queues (`php artisan queue:restart`)

Rollback and diagnostics:

- `scripts/rollback.sh <git-ref>`
- `scripts/check-server.sh`

## Development setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
php artisan serve
```

Optional (for active frontend development):

```bash
npm run dev
```

## Folder structure

- `app/` — Laravel application code (models, services, middleware, Livewire components)
- `bootstrap/` — framework bootstrap and middleware registration
- `config/` — application and environment-driven configuration
- `public/` — public assets, service worker, build output
- `resources/` — Blade views, JS, CSS sources
- `routes/` — web, API, console route definitions
- `scripts/` — deployment and server operations scripts
- `.github/workflows/` — CI/CD automation

## Caching and edge strategy

- **Service Worker caching** is intentionally limited to same-origin images/icons.
- **Vite `/build/` assets are never cached** by the service worker.
- **API responses are never cached** by the service worker.
- **Nginx caching strategy** should cache immutable static assets aggressively while bypassing dynamic/API responses.

## Production operations

For server setup and operations guidance see:

- `docs/production-server-setup.md`

The repository includes helper scripts for operations:

- `scripts/deploy.sh`
- `scripts/rollback.sh`
- `scripts/check-server.sh`
