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
COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}"
NPM_BIN="${NPM_BIN:-npm}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://api.poof.com.ua/up}"
HEALTHCHECK_ATTEMPTS="${HEALTHCHECK_ATTEMPTS:-10}"
HEALTHCHECK_DELAY="${HEALTHCHECK_DELAY:-3}"
DEPLOY_LOG_DIR="${DEPLOY_LOG_DIR:-$APP_DIR/storage/logs/deploy}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"
RELEASE_HISTORY_FILE="${RELEASE_HISTORY_FILE:-$APP_DIR/storage/app/release-history.jsonl}"
RELEASE_SUMMARY_DIR="${RELEASE_SUMMARY_DIR:-$APP_DIR/docs/release-summaries}"
ROLLBACK_SELECTION_MODE="explicit"
DEPLOY_RUNTIME_EVIDENCE_FILE="${DEPLOY_RUNTIME_EVIDENCE_FILE:-}"

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

append_runtime_evidence() {
  local event_name="$1"
  local event_status="$2"
  local event_details="${3:-{}}"

  if [[ -z "${DEPLOY_RUNTIME_EVIDENCE_FILE:-}" ]]; then
    return 0
  fi

  local event_at
  event_at="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

  "$PHP_BIN" -r '
    $payload = [
      "event_at_utc" => $argv[1],
      "deploy_started_at_utc" => $argv[2],
      "event" => $argv[3],
      "status" => $argv[4],
      "commit" => $argv[5],
      "release_ref" => $argv[6],
      "requested_ref" => $argv[7] !== "" ? $argv[7] : null,
      "selection_mode" => $argv[8],
      "details" => json_decode($argv[9], true) ?: new stdClass(),
    ];
    echo json_encode($payload, JSON_UNESCAPED_SLASHES), PHP_EOL;
  ' "$event_at" "$ROLLED_BACK_AT" "$event_name" "$event_status" "$RESOLVED_COMMIT" "$RESOLVED_REF" "$TARGET_REF" "$ROLLBACK_SELECTION_MODE" "$event_details" >> "$DEPLOY_RUNTIME_EVIDENCE_FILE"
}

resolve_release_summary_file() {
  local release_ref="$1"
  echo "$RELEASE_SUMMARY_DIR/$release_ref.md"
}

extract_release_summary() {
  local summary_file="$1"

  "$PHP_BIN" -r '
    $file = $argv[1];
    if (!is_file($file)) {
        exit(0);
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === "" || str_starts_with($trimmed, "#")) {
            continue;
        }
        echo $trimmed;
        exit(0);
    }
  ' "$summary_file"
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

RELEASE_SUMMARY_FILE=""
RELEASE_SUMMARY_TEXT=""
IS_TAG_RELEASE=0
if git rev-parse --verify --quiet "refs/tags/$RESOLVED_REF" > /dev/null; then
  IS_TAG_RELEASE=1
  RELEASE_SUMMARY_FILE="$(resolve_release_summary_file "$RESOLVED_REF")"
  if [[ ! -f "$RELEASE_SUMMARY_FILE" ]]; then
    echo "[rollback] missing release summary for tag $RESOLVED_REF: $RELEASE_SUMMARY_FILE" >&2
    echo "[rollback] add a short note for this explicit release tag before rollback" >&2
    exit 1
  fi
  RELEASE_SUMMARY_TEXT="$(extract_release_summary "$RELEASE_SUMMARY_FILE")"
fi
RELEASE_REF_KIND="$([[ "$IS_TAG_RELEASE" -eq 1 ]] && echo tag || echo ref)"
RELEASE_SUMMARY_REQUIRED="$([[ "$IS_TAG_RELEASE" -eq 1 ]] && echo true || echo false)"
RELEASE_SUMMARY_PRESENT="$([[ -n "$RELEASE_SUMMARY_TEXT" ]] && echo true || echo false)"

ROLLED_BACK_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
ROLLBACK_LOG_FILE="$DEPLOY_LOG_DIR/${ROLLED_BACK_AT//:/-}-${RESOLVED_COMMIT}-rollback.json"
DEPLOY_RUNTIME_EVIDENCE_FILE="${DEPLOY_RUNTIME_EVIDENCE_FILE:-$DEPLOY_LOG_DIR/${ROLLED_BACK_AT//:/-}-${RESOLVED_COMMIT}-rollback.runtime.jsonl}"

echo "[rollback] requested ref: $TARGET_REF"
echo "[rollback] resolved rollback ref: $TARGET_REF"
echo "[rollback] resolved release ref: $RESOLVED_REF"
echo "[rollback] release ref kind: $RELEASE_REF_KIND"
echo "[rollback] resolved commit: $RESOLVED_COMMIT"
echo "[rollback] selection mode: $ROLLBACK_SELECTION_MODE"
echo "[rollback] fallback path used: no"
echo "[rollback] rollback started at: $ROLLED_BACK_AT"
echo "[rollback] rollback log: $ROLLBACK_LOG_FILE"
echo "[rollback] runtime evidence log: $DEPLOY_RUNTIME_EVIDENCE_FILE"
echo "[rollback] release history: $RELEASE_HISTORY_FILE"
if [[ -n "$RELEASE_SUMMARY_TEXT" ]]; then
  echo "[rollback] release summary: $RELEASE_SUMMARY_TEXT"
  echo "[rollback] release summary file: $RELEASE_SUMMARY_FILE"
fi

git reset --hard "$RESOLVED_COMMIT"
git clean -fd

: > "$DEPLOY_RUNTIME_EVIDENCE_FILE"
append_runtime_evidence "rollback_started" "ok"

echo "[rollback] installing PHP dependencies"
COMPOSER_ALLOW_SUPERUSER="$COMPOSER_ALLOW_SUPERUSER" "$COMPOSER_BIN" install --no-interaction --no-dev --optimize-autoloader
append_runtime_evidence "php_dependencies_installed" "ok"

echo "[rollback] installing JS dependencies"
"$NPM_BIN" ci
append_runtime_evidence "js_dependencies_installed" "ok"

echo "[rollback] building frontend assets"
"$NPM_BIN" run build
append_runtime_evidence "frontend_assets_built" "ok"

echo "[rollback] verifying frontend build artifacts"
test -f public/build/manifest.json
append_runtime_evidence "frontend_artifacts_verified" "ok"

echo "[rollback] clearing Laravel config cache"
"$PHP_BIN" artisan config:clear

echo "[rollback] clearing Laravel optimized caches"
"$PHP_BIN" artisan optimize:clear

echo "[rollback] optimizing Laravel caches"
"$PHP_BIN" artisan optimize
append_runtime_evidence "laravel_caches_optimized" "ok"

echo "[rollback] restarting workers"
"$SUPERVISORCTL_BIN" restart poof-worker:*
append_runtime_evidence "workers_restarted" "ok"

echo "[rollback] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[rollback] health check passed on attempt $attempt"
    append_runtime_evidence "health_check_passed" "ok" "{\"attempt\":$attempt}"

    echo "[rollback] recording release state"
    cat > "$DEPLOY_STATE_FILE" <<STATE
{
  "release_ref": "$RESOLVED_REF",
  "release_ref_kind": "$RELEASE_REF_KIND",
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
  "deploy_runtime_evidence": "$DEPLOY_RUNTIME_EVIDENCE_FILE",
  "release_history": "$RELEASE_HISTORY_FILE",
  "release_summary_required": $RELEASE_SUMMARY_REQUIRED,
  "release_summary_present": $RELEASE_SUMMARY_PRESENT,
  "release_summary_file": $(json_string_or_null "$RELEASE_SUMMARY_FILE"),
  "release_summary": $(json_string_or_null "$RELEASE_SUMMARY_TEXT")
}
STATE
    append_runtime_evidence "release_state_recorded" "ok"
    cp "$DEPLOY_STATE_FILE" "$ROLLBACK_LOG_FILE"
    append_history_entry "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE"

    echo "[rollback] current release state: $DEPLOY_STATE_FILE"
    echo "[rollback] done"
    exit 0
  fi

  if [ "$attempt" -eq "$HEALTHCHECK_ATTEMPTS" ]; then
    echo "[rollback] health check failed after $attempt attempts"
    append_runtime_evidence "health_check_failed" "fail" "{\"attempt\":$attempt}"
    echo "[rollback] current release state was not updated; previous known-good release remains recorded in $DEPLOY_STATE_FILE"
    exit 1
  fi

  echo "[rollback] health check attempt $attempt failed; retrying in ${HEALTHCHECK_DELAY}s"
  sleep "$HEALTHCHECK_DELAY"
done
