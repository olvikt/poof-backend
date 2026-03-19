#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 && -z "${ROLLBACK_REF:-}" ]]; then
  echo "Usage: $0 <release-ref>"
  echo "Example: $0 release-20260319-1200"
  exit 1
fi

TARGET_REF="${ROLLBACK_REF:-${1:-}}"
APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://api.poof.com.ua/up}"
HEALTHCHECK_ATTEMPTS="${HEALTHCHECK_ATTEMPTS:-10}"
HEALTHCHECK_DELAY="${HEALTHCHECK_DELAY:-3}"
DEPLOY_LOG_DIR="${DEPLOY_LOG_DIR:-$APP_DIR/storage/logs/deploy}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"

cd "$APP_DIR"

mkdir -p "$DEPLOY_LOG_DIR" "$(dirname "$DEPLOY_STATE_FILE")"

echo "[rollback] fetching refs"
git fetch --prune --tags origin

if ! git rev-parse --verify --quiet "$TARGET_REF^{commit}" > /dev/null; then
  echo "[rollback] unknown rollback ref: $TARGET_REF" >&2
  exit 1
fi

RESOLVED_COMMIT="$(git rev-parse "$TARGET_REF^{commit}")"
RESOLVED_REF="$(git describe --tags --exact-match "$RESOLVED_COMMIT" 2>/dev/null || true)"
if [[ -z "$RESOLVED_REF" ]]; then
  RESOLVED_REF="$TARGET_REF"
fi

ROLLED_BACK_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
ROLLBACK_LOG_FILE="$DEPLOY_LOG_DIR/${ROLLED_BACK_AT//:/-}-${RESOLVED_COMMIT}-rollback.log"

echo "[rollback] requested ref: $TARGET_REF"
echo "[rollback] resolved release ref: $RESOLVED_REF"
echo "[rollback] resolved commit: $RESOLVED_COMMIT"
echo "[rollback] rollback started at: $ROLLED_BACK_AT"

git reset --hard "$RESOLVED_COMMIT"
git clean -fd

echo "[rollback] installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader

echo "[rollback] optimizing Laravel caches"
"$PHP_BIN" artisan optimize

echo "[rollback] restarting workers"
"$SUPERVISORCTL_BIN" restart poof-worker:*

echo "[rollback] recording release state"
cat > "$DEPLOY_STATE_FILE" <<STATE
{
  "release_ref": "$RESOLVED_REF",
  "requested_ref": "$TARGET_REF",
  "commit": "$RESOLVED_COMMIT",
  "deployed_at_utc": "$ROLLED_BACK_AT",
  "deployment_type": "rollback",
  "deploy_log": "$ROLLBACK_LOG_FILE"
}
STATE
cp "$DEPLOY_STATE_FILE" "$ROLLBACK_LOG_FILE"

echo "[rollback] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[rollback] health check passed on attempt $attempt"
    echo "[rollback] current release state: $DEPLOY_STATE_FILE"
    echo "[rollback] done"
    exit 0
  fi

  if [ "$attempt" -eq "$HEALTHCHECK_ATTEMPTS" ]; then
    echo "[rollback] health check failed after $attempt attempts"
    exit 1
  fi

  echo "[rollback] health check attempt $attempt failed; retrying in ${HEALTHCHECK_DELAY}s"
  sleep "$HEALTHCHECK_DELAY"
done
