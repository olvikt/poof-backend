#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://api.poof.com.ua/up}"
HEALTHCHECK_ATTEMPTS="${HEALTHCHECK_ATTEMPTS:-10}"
HEALTHCHECK_DELAY="${HEALTHCHECK_DELAY:-3}"
DEFAULT_DEPLOY_REF="${DEFAULT_DEPLOY_REF:-origin/main}"
DEPLOY_REF="${DEPLOY_REF:-${1:-$DEFAULT_DEPLOY_REF}}"
DEPLOY_LOG_DIR="${DEPLOY_LOG_DIR:-$APP_DIR/storage/logs/deploy}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"

cd "$APP_DIR"

mkdir -p "$DEPLOY_LOG_DIR" "$(dirname "$DEPLOY_STATE_FILE")"

echo "[deploy] fetching refs"
git fetch --prune --tags origin

if ! git rev-parse --verify --quiet "$DEPLOY_REF^{commit}" > /dev/null; then
  echo "[deploy] unknown deploy ref: $DEPLOY_REF" >&2
  exit 1
fi

RESOLVED_COMMIT="$(git rev-parse "$DEPLOY_REF^{commit}")"
RESOLVED_REF="$(git describe --tags --exact-match "$RESOLVED_COMMIT" 2>/dev/null || true)"
if [[ -z "$RESOLVED_REF" ]]; then
  RESOLVED_REF="$DEPLOY_REF"
fi

DEPLOYED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
DEPLOY_LOG_FILE="$DEPLOY_LOG_DIR/${DEPLOYED_AT//:/-}-${RESOLVED_COMMIT}.log"

echo "[deploy] requested ref: $DEPLOY_REF"
echo "[deploy] resolved release ref: $RESOLVED_REF"
echo "[deploy] resolved commit: $RESOLVED_COMMIT"
echo "[deploy] deploy started at: $DEPLOYED_AT"
echo "[deploy] deploy log: $DEPLOY_LOG_FILE"

git reset --hard "$RESOLVED_COMMIT"
git clean -fd

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

echo "[deploy] recording release state"
cat > "$DEPLOY_STATE_FILE" <<STATE
{
  "release_ref": "$RESOLVED_REF",
  "requested_ref": "$DEPLOY_REF",
  "commit": "$RESOLVED_COMMIT",
  "deployed_at_utc": "$DEPLOYED_AT",
  "deploy_log": "$DEPLOY_LOG_FILE"
}
STATE
cp "$DEPLOY_STATE_FILE" "$DEPLOY_LOG_FILE"

echo "[deploy] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[deploy] health check passed on attempt $attempt"
    echo "[deploy] current release state: $DEPLOY_STATE_FILE"
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
