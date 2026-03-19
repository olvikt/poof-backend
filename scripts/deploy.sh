#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://api.poof.com.ua/up}"
HEALTHCHECK_ATTEMPTS="${HEALTHCHECK_ATTEMPTS:-10}"
HEALTHCHECK_DELAY="${HEALTHCHECK_DELAY:-3}"

cd "$APP_DIR"

echo "[deploy] pulling code"

git reset --hard origin/main
git clean -fd
git pull --ff-only

echo "[deploy] installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader

echo "[deploy] installing JS dependencies"
npm ci

echo "[deploy] building frontend assets"
npm run build

echo "[deploy] verifying frontend build artifacts"
test -f public/build/manifest.json

echo "[deploy] running migrations"
"$PHP_BIN" artisan migrate --force

echo "[deploy] clearing Laravel config cache"
"$PHP_BIN" artisan config:clear

echo "[deploy] clearing Laravel optimized caches"
"$PHP_BIN" artisan optimize:clear

echo "[deploy] optimizing Laravel caches"
"$PHP_BIN" artisan optimize

echo "[deploy] restarting workers"
"$SUPERVISORCTL_BIN" restart poof-worker:* || true

echo "[deploy] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[deploy] health check passed on attempt $attempt"
    echo "[deploy] done"
    exit 0
  fi

  if [ "$attempt" -eq "$HEALTHCHECK_ATTEMPTS" ]; then
    echo "[deploy] health check failed after $attempt attempts"
    exit 1
  fi

  echo "[deploy] health check attempt $attempt failed; retrying in ${HEALTHCHECK_DELAY}s"
  sleep "$HEALTHCHECK_DELAY"
done
