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
RELEASE_HISTORY_FILE="${RELEASE_HISTORY_FILE:-$APP_DIR/storage/app/release-history.jsonl}"
ROLLBACK_SELECTION_MODE="explicit"

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
  echo "[rollback] previous known-good release: $PREVIOUS_RELEASE_REF (${PREVIOUS_COMMIT:-unknown commit})"
else
  echo "[rollback] previous known-good release: <none recorded>"
fi

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
ROLLBACK_LOG_FILE="$DEPLOY_LOG_DIR/${ROLLED_BACK_AT//:/-}-${RESOLVED_COMMIT}-rollback.json"

echo "[rollback] requested ref: $TARGET_REF"
echo "[rollback] resolved rollback ref: $TARGET_REF"
echo "[rollback] resolved release ref: $RESOLVED_REF"
echo "[rollback] resolved commit: $RESOLVED_COMMIT"
echo "[rollback] selection mode: $ROLLBACK_SELECTION_MODE"
echo "[rollback] fallback path used: no"
echo "[rollback] rollback started at: $ROLLED_BACK_AT"
echo "[rollback] rollback log: $ROLLBACK_LOG_FILE"
echo "[rollback] release history: $RELEASE_HISTORY_FILE"

git reset --hard "$RESOLVED_COMMIT"
git clean -fd

echo "[rollback] installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader

echo "[rollback] optimizing Laravel caches"
"$PHP_BIN" artisan optimize

echo "[rollback] restarting workers"
"$SUPERVISORCTL_BIN" restart poof-worker:*

echo "[rollback] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[rollback] health check passed on attempt $attempt"

    echo "[rollback] recording release state"
    cat > "$DEPLOY_STATE_FILE" <<STATE
{
  "release_ref": "$RESOLVED_REF",
  "requested_ref": "$TARGET_REF",
  "resolved_ref": "$TARGET_REF",
  "fallback_ref": null,
  "fallback_used": false,
  "selection_mode": "$ROLLBACK_SELECTION_MODE",
  "commit": "$RESOLVED_COMMIT",
  "deployed_at_utc": "$ROLLED_BACK_AT",
  "deployment_type": "rollback",
  "previous_release_ref": $(json_string_or_null "$PREVIOUS_RELEASE_REF"),
  "previous_commit": $(json_string_or_null "$PREVIOUS_COMMIT"),
  "previous_deployed_at_utc": $(json_string_or_null "$PREVIOUS_DEPLOYED_AT"),
  "previous_deployment_type": $(json_string_or_null "$PREVIOUS_DEPLOYMENT_TYPE"),
  "previous_selection_mode": $(json_string_or_null "$PREVIOUS_SELECTION_MODE"),
  "deploy_log": "$ROLLBACK_LOG_FILE",
  "release_history": "$RELEASE_HISTORY_FILE"
}
STATE
    cp "$DEPLOY_STATE_FILE" "$ROLLBACK_LOG_FILE"
    append_history_entry "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE"

    echo "[rollback] current release state: $DEPLOY_STATE_FILE"
    echo "[rollback] done"
    exit 0
  fi

  if [ "$attempt" -eq "$HEALTHCHECK_ATTEMPTS" ]; then
    echo "[rollback] health check failed after $attempt attempts"
    echo "[rollback] current release state was not updated; previous known-good release remains recorded in $DEPLOY_STATE_FILE"
    exit 1
  fi

  echo "[rollback] health check attempt $attempt failed; retrying in ${HEALTHCHECK_DELAY}s"
  sleep "$HEALTHCHECK_DELAY"
done
