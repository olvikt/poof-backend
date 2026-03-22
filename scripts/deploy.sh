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
EXPLICIT_DEPLOY_REF="${DEPLOY_REF:-${1:-}}"
DEPLOY_REF="$EXPLICIT_DEPLOY_REF"
FALLBACK_DEPLOY_USED=0

if [[ -z "$DEPLOY_REF" ]]; then
  DEPLOY_REF="$DEFAULT_DEPLOY_REF"
  FALLBACK_DEPLOY_USED=1
fi
DEPLOY_SELECTION_MODE="$([[ "$FALLBACK_DEPLOY_USED" -eq 1 ]] && echo fallback || echo explicit)"
DEPLOY_LOG_DIR="${DEPLOY_LOG_DIR:-$APP_DIR/storage/logs/deploy}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"
RELEASE_HISTORY_FILE="${RELEASE_HISTORY_FILE:-$APP_DIR/storage/app/release-history.jsonl}"

json_field() {
  local file_path="$1"
  local field_name="$2"

  if [[ ! -f "$file_path" ]]; then
    return 0
  fi

  "$PHP_BIN" -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data) || !array_key_exists($argv[2], $data) || $data[$argv[2]] === null) {
        exit(0);
    }
    if (is_bool($data[$argv[2]])) {
        echo $data[$argv[2]] ? "true" : "false";
        exit(0);
    }
    echo (string) $data[$argv[2]];
  ' "$file_path" "$field_name"
}

json_string_or_null() {
  local value="$1"

  if [[ -z "$value" ]]; then
    echo null
  else
    "$PHP_BIN" -r 'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES);' "$value"
  fi
}

append_history_entry() {
  local state_file="$1"
  local history_file="$2"

  "$PHP_BIN" -r '
    $data = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($data)) {
        fwrite(STDERR, "failed to decode release state for history append\n");
        exit(1);
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES) . PHP_EOL;
  ' "$state_file" >> "$history_file"
}

cd "$APP_DIR"

mkdir -p "$DEPLOY_LOG_DIR" "$(dirname "$DEPLOY_STATE_FILE")" "$(dirname "$RELEASE_HISTORY_FILE")"

PREVIOUS_RELEASE_REF="$(json_field "$DEPLOY_STATE_FILE" release_ref)"
PREVIOUS_COMMIT="$(json_field "$DEPLOY_STATE_FILE" commit)"
PREVIOUS_DEPLOYED_AT="$(json_field "$DEPLOY_STATE_FILE" deployed_at_utc)"
PREVIOUS_DEPLOYMENT_TYPE="$(json_field "$DEPLOY_STATE_FILE" deployment_type)"
PREVIOUS_SELECTION_MODE="$(json_field "$DEPLOY_STATE_FILE" selection_mode)"

if [[ -n "$PREVIOUS_RELEASE_REF" ]]; then
  echo "[deploy] previous known-good release: $PREVIOUS_RELEASE_REF (${PREVIOUS_COMMIT:-unknown commit})"
else
  echo "[deploy] previous known-good release: <none recorded>"
fi

echo "[deploy] fetching refs"
git fetch --prune --tags origin

if [[ "$FALLBACK_DEPLOY_USED" -eq 1 ]]; then
  echo "[deploy] WARNING: no explicit release ref provided; falling back to legacy path: $DEFAULT_DEPLOY_REF" >&2
  echo "[deploy] WARNING: explicit release tag/ref is the canonical production path; use this fallback only for backward compatibility or emergency continuity" >&2
else
  echo "[deploy] explicit release ref requested"
fi

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
DEPLOY_LOG_FILE="$DEPLOY_LOG_DIR/${DEPLOYED_AT//:/-}-${RESOLVED_COMMIT}.json"

echo "[deploy] requested ref: ${EXPLICIT_DEPLOY_REF:-<none>}"
echo "[deploy] fallback ref: $DEFAULT_DEPLOY_REF"
echo "[deploy] resolved deploy ref: $DEPLOY_REF"
echo "[deploy] resolved release ref: $RESOLVED_REF"
echo "[deploy] resolved commit: $RESOLVED_COMMIT"
echo "[deploy] selection mode: $DEPLOY_SELECTION_MODE"
echo "[deploy] fallback path used: $([[ "$FALLBACK_DEPLOY_USED" -eq 1 ]] && echo yes || echo no)"
echo "[deploy] deploy started at: $DEPLOYED_AT"
echo "[deploy] deploy log: $DEPLOY_LOG_FILE"
echo "[deploy] release history: $RELEASE_HISTORY_FILE"

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

echo "[deploy] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[deploy] health check passed on attempt $attempt"

    echo "[deploy] recording release state"
    cat > "$DEPLOY_STATE_FILE" <<STATE
{
  "release_ref": "$RESOLVED_REF",
  "requested_ref": "${EXPLICIT_DEPLOY_REF:-}",
  "resolved_ref": "$DEPLOY_REF",
  "fallback_ref": "$DEFAULT_DEPLOY_REF",
  "fallback_used": $([[ "$FALLBACK_DEPLOY_USED" -eq 1 ]] && echo true || echo false),
  "selection_mode": "$DEPLOY_SELECTION_MODE",
  "commit": "$RESOLVED_COMMIT",
  "deployed_at_utc": "$DEPLOYED_AT",
  "deployment_type": "deploy",
  "previous_release_ref": $(json_string_or_null "$PREVIOUS_RELEASE_REF"),
  "previous_commit": $(json_string_or_null "$PREVIOUS_COMMIT"),
  "previous_deployed_at_utc": $(json_string_or_null "$PREVIOUS_DEPLOYED_AT"),
  "previous_deployment_type": $(json_string_or_null "$PREVIOUS_DEPLOYMENT_TYPE"),
  "previous_selection_mode": $(json_string_or_null "$PREVIOUS_SELECTION_MODE"),
  "deploy_log": "$DEPLOY_LOG_FILE",
  "release_history": "$RELEASE_HISTORY_FILE"
}
STATE
    cp "$DEPLOY_STATE_FILE" "$DEPLOY_LOG_FILE"
    append_history_entry "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE"

    echo "[deploy] current release state: $DEPLOY_STATE_FILE"
    echo "[deploy] done"
    exit 0
  fi

  if [ "$attempt" -eq "$HEALTHCHECK_ATTEMPTS" ]; then
    echo "[deploy] health check failed after $attempt attempts"
    echo "[deploy] current release state was not updated; previous known-good release remains recorded in $DEPLOY_STATE_FILE"
    exit 1
  fi

  echo "[deploy] health check attempt $attempt failed; retrying in ${HEALTHCHECK_DELAY}s"
  sleep "$HEALTHCHECK_DELAY"
done
