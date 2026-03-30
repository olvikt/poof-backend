#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}"
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
RELEASE_SUMMARY_DIR="${RELEASE_SUMMARY_DIR:-$APP_DIR/docs/release-summaries}"
RELEASE_GATE_STATE_DIR="${RELEASE_GATE_STATE_DIR:-$APP_DIR/storage/app/release-gates}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/release-state-lib.sh"

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
  ' "$event_at" "$DEPLOYED_AT" "$event_name" "$event_status" "$RESOLVED_COMMIT" "$RESOLVED_REF" "${EXPLICIT_DEPLOY_REF:-}" "$DEPLOY_SELECTION_MODE" "$event_details" >> "$DEPLOY_RUNTIME_EVIDENCE_FILE"
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

release_gate_file_for_ref() {
  local release_ref="$1"
  local release_ref_hash
  release_ref_hash="$(printf '%s' "$release_ref" | sha1sum | awk '{print $1}')"

  echo "$RELEASE_GATE_STATE_DIR/${release_ref_hash}.json"
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

RELEASE_GATE_FILE="$(release_gate_file_for_ref "$RESOLVED_REF")"

IS_TAG_RELEASE=0
if git rev-parse --verify --quiet "refs/tags/$RESOLVED_REF" > /dev/null; then
  IS_TAG_RELEASE=1
fi

RELEASE_SUMMARY_FILE=""
RELEASE_SUMMARY_TEXT=""
if [[ "$IS_TAG_RELEASE" -eq 1 ]]; then
  RELEASE_SUMMARY_FILE="$(resolve_release_summary_file "$RESOLVED_REF")"
  if [[ ! -f "$RELEASE_SUMMARY_FILE" ]]; then
    echo "[deploy] missing release summary for tag $RESOLVED_REF: $RELEASE_SUMMARY_FILE" >&2
    echo "[deploy] add a short note for this explicit release tag before deploying" >&2
    exit 1
  fi
  RELEASE_SUMMARY_TEXT="$(extract_release_summary "$RELEASE_SUMMARY_FILE")"
  if [[ -z "$RELEASE_SUMMARY_TEXT" ]]; then
    echo "[deploy] empty release summary in $RELEASE_SUMMARY_FILE" >&2
    echo "[deploy] provide a non-empty short operator summary (first non-header line)" >&2
    exit 1
  fi
fi

RELEASE_REF_KIND="$([[ "$IS_TAG_RELEASE" -eq 1 ]] && echo tag || echo ref)"
RELEASE_SUMMARY_REQUIRED="$([[ "$IS_TAG_RELEASE" -eq 1 ]] && echo true || echo false)"
RELEASE_SUMMARY_PRESENT="$([[ -n "$RELEASE_SUMMARY_TEXT" ]] && echo true || echo false)"

DEPLOYED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
DEPLOY_LOG_FILE="$DEPLOY_LOG_DIR/${DEPLOYED_AT//:/-}-${RESOLVED_COMMIT}.json"
DEPLOY_RUNTIME_EVIDENCE_FILE="${DEPLOY_RUNTIME_EVIDENCE_FILE:-$DEPLOY_LOG_DIR/${DEPLOYED_AT//:/-}-${RESOLVED_COMMIT}.runtime.jsonl}"

echo "[deploy] requested ref: ${EXPLICIT_DEPLOY_REF:-<none>}"
echo "[deploy] fallback ref: $DEFAULT_DEPLOY_REF"
echo "[deploy] resolved deploy ref: $DEPLOY_REF"
echo "[deploy] resolved release ref: $RESOLVED_REF"
echo "[deploy] release ref kind: $RELEASE_REF_KIND"
echo "[deploy] resolved commit: $RESOLVED_COMMIT"
echo "[deploy] selection mode: $DEPLOY_SELECTION_MODE"
echo "[deploy] fallback path used: $([[ "$FALLBACK_DEPLOY_USED" -eq 1 ]] && echo yes || echo no)"
echo "[deploy] deploy started at: $DEPLOYED_AT"
echo "[deploy] deploy log: $DEPLOY_LOG_FILE"
echo "[deploy] runtime evidence log: $DEPLOY_RUNTIME_EVIDENCE_FILE"
echo "[deploy] release history: $RELEASE_HISTORY_FILE"
echo "[deploy] required pre-deploy gate artifact: $RELEASE_GATE_FILE"
if [[ -n "$RELEASE_SUMMARY_TEXT" ]]; then
  echo "[deploy] release summary: $RELEASE_SUMMARY_TEXT"
  echo "[deploy] release summary file: $RELEASE_SUMMARY_FILE"
else
  echo "[deploy] release summary: <not required for non-tag ref>"
fi

echo "[deploy] validating mandatory pre-deploy gate (runtime contract + browser smoke)"
if ! "$PHP_BIN" -r '
  $gateFile = $argv[1];
  $resolvedRef = $argv[2];
  $resolvedCommit = $argv[3];
  $now = new DateTimeImmutable("now", new DateTimeZone("UTC"));

  if (!is_file($gateFile)) {
    fwrite(STDERR, "[deploy] release blocked: pre-deploy gate artifact is missing: {$gateFile}\n");
    fwrite(STDERR, "[deploy] run scripts/prepare-release-gate.sh for this release ref before deploy\n");
    exit(1);
  }

  $gate = json_decode((string) file_get_contents($gateFile), true);
  if (!is_array($gate)) {
    fwrite(STDERR, "[deploy] release blocked: pre-deploy gate artifact is not valid JSON: {$gateFile}\n");
    exit(1);
  }

  $required = [
    "release_ref",
    "resolved_commit",
    "generated_at_utc",
    "expires_at_utc",
    "runtime_contract",
    "browser_smoke",
  ];

  foreach ($required as $field) {
    if (!array_key_exists($field, $gate)) {
      fwrite(STDERR, "[deploy] release blocked: pre-deploy gate missing field: {$field}\n");
      exit(1);
    }
  }

  if ((string) $gate["release_ref"] !== $resolvedRef) {
    fwrite(STDERR, "[deploy] release blocked: gate release_ref mismatch (expected {$resolvedRef}, got ".((string) $gate["release_ref"]).")\n");
    exit(1);
  }

  if ((string) $gate["resolved_commit"] !== $resolvedCommit) {
    fwrite(STDERR, "[deploy] release blocked: gate commit mismatch (expected {$resolvedCommit}, got ".((string) $gate["resolved_commit"]).")\n");
    exit(1);
  }

  try {
    $expiresAt = new DateTimeImmutable((string) $gate["expires_at_utc"], new DateTimeZone("UTC"));
  } catch (Throwable $e) {
    fwrite(STDERR, "[deploy] release blocked: invalid gate expires_at_utc value\n");
    exit(1);
  }

  if ($expiresAt < $now) {
    fwrite(STDERR, "[deploy] release blocked: pre-deploy gate expired at ".$expiresAt->format(DATE_ATOM)." UTC\n");
    fwrite(STDERR, "[deploy] rerun scripts/prepare-release-gate.sh and repeat browser smoke\n");
    exit(1);
  }

  $runtimeStatus = (string) ($gate["runtime_contract"]["status"] ?? "");
  if ($runtimeStatus !== "passed") {
    fwrite(STDERR, "[deploy] release blocked: runtime contract gate status must be passed\n");
    exit(1);
  }

  $browserStatus = (string) ($gate["browser_smoke"]["status"] ?? "");
  if ($browserStatus !== "passed") {
    fwrite(STDERR, "[deploy] release blocked: browser smoke gate status must be passed\n");
    exit(1);
  }

  $requiredSmoke = [
    "home_page",
    "client_order_create",
    "address_profile_avatar_edit",
    "courier_available_orders_and_my_orders",
    "critical_popups_carousels_click_flows",
  ];
  foreach ($requiredSmoke as $key) {
    if (!array_key_exists($key, $gate["browser_smoke"]["checklist"] ?? []) || !$gate["browser_smoke"]["checklist"][$key]) {
      fwrite(STDERR, "[deploy] release blocked: browser smoke checklist item failed/missing: {$key}\n");
      exit(1);
    }
  }
' "$RELEASE_GATE_FILE" "$RESOLVED_REF" "$RESOLVED_COMMIT"; then
  exit 1
fi
echo "[deploy] pre-deploy release gate: PASS"

git reset --hard "$RESOLVED_COMMIT"
git clean -fd

: > "$DEPLOY_RUNTIME_EVIDENCE_FILE"
append_runtime_evidence "deploy_started" "ok"

echo "[deploy] installing PHP dependencies"
COMPOSER_ALLOW_SUPERUSER="$COMPOSER_ALLOW_SUPERUSER" "$COMPOSER_BIN" install --no-interaction --no-dev --optimize-autoloader
append_runtime_evidence "php_dependencies_installed" "ok"

echo "[deploy] installing JS dependencies"
npm ci
append_runtime_evidence "js_dependencies_installed" "ok"

echo "[deploy] building frontend assets"
npm run build
append_runtime_evidence "frontend_assets_built" "ok"

echo "[deploy] verifying frontend build artifacts"
test -f public/build/manifest.json
append_runtime_evidence "frontend_artifacts_verified" "ok"

echo "[deploy] running migrations"
"$PHP_BIN" artisan migrate --force
append_runtime_evidence "database_migrations_completed" "ok"

echo "[deploy] clearing Laravel config cache"
"$PHP_BIN" artisan config:clear

echo "[deploy] clearing Laravel optimized caches"
"$PHP_BIN" artisan optimize:clear

echo "[deploy] optimizing Laravel caches"
"$PHP_BIN" artisan optimize
append_runtime_evidence "laravel_caches_optimized" "ok"

echo "[deploy] restarting workers"
"$SUPERVISORCTL_BIN" restart poof-worker:* || true
append_runtime_evidence "workers_restarted" "ok"

echo "[deploy] running blocking health check (${HEALTHCHECK_ATTEMPTS} attempts, ${HEALTHCHECK_DELAY}s delay)"
for attempt in $(seq 1 "$HEALTHCHECK_ATTEMPTS"); do
  if curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null; then
    echo "[deploy] health check passed on attempt $attempt"
    append_runtime_evidence "health_check_passed" "ok" "{\"attempt\":$attempt}"

    echo "[deploy] recording release state"
    DEPLOY_STATE_PAYLOAD="$(cat <<STATE
{
  "release_ref": "$RESOLVED_REF",
  "release_ref_kind": "$RELEASE_REF_KIND",
  "requested_ref": "${EXPLICIT_DEPLOY_REF:-}",
  "resolved_ref": "$DEPLOY_REF",
  "fallback_ref": "$DEFAULT_DEPLOY_REF",
  "fallback_used": $([[ "$FALLBACK_DEPLOY_USED" -eq 1 ]] && echo true || echo false),
  "selection_mode": "$DEPLOY_SELECTION_MODE",
  "commit": "$RESOLVED_COMMIT",
  "deployed_at_utc": "$DEPLOYED_AT",
  "deployment_type": "deploy",
  "previous_release_ref": $(release_state_json_string_or_null "$PREVIOUS_RELEASE_REF" "$PHP_BIN"),
  "previous_commit": $(release_state_json_string_or_null "$PREVIOUS_COMMIT" "$PHP_BIN"),
  "previous_deployed_at_utc": $(release_state_json_string_or_null "$PREVIOUS_DEPLOYED_AT" "$PHP_BIN"),
  "previous_deployment_type": $(release_state_json_string_or_null "$PREVIOUS_DEPLOYMENT_TYPE" "$PHP_BIN"),
  "previous_selection_mode": $(release_state_json_string_or_null "$PREVIOUS_SELECTION_MODE" "$PHP_BIN"),
  "deploy_log": "$DEPLOY_LOG_FILE",
  "deploy_runtime_evidence": "$DEPLOY_RUNTIME_EVIDENCE_FILE",
  "release_history": "$RELEASE_HISTORY_FILE",
  "release_summary_required": $RELEASE_SUMMARY_REQUIRED,
  "release_summary_present": $RELEASE_SUMMARY_PRESENT,
  "release_summary_file": $(release_state_json_string_or_null "$RELEASE_SUMMARY_FILE" "$PHP_BIN"),
  "release_summary": $(release_state_json_string_or_null "$RELEASE_SUMMARY_TEXT" "$PHP_BIN")
}
STATE
)"
    write_release_state_and_history "$DEPLOY_STATE_PAYLOAD" "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE" "$PHP_BIN"
    append_runtime_evidence "release_state_recorded" "ok"
    cp "$DEPLOY_STATE_FILE" "$DEPLOY_LOG_FILE"

    echo "[deploy] current release state: $DEPLOY_STATE_FILE"
    echo "[deploy] done"
    exit 0
  fi

  if [ "$attempt" -eq "$HEALTHCHECK_ATTEMPTS" ]; then
    echo "[deploy] health check failed after $attempt attempts"
    append_runtime_evidence "health_check_failed" "fail" "{\"attempt\":$attempt}"
    echo "[deploy] current release state was not updated; previous known-good release remains recorded in $DEPLOY_STATE_FILE"
    exit 1
  fi

  echo "[deploy] health check attempt $attempt failed; retrying in ${HEALTHCHECK_DELAY}s"
  sleep "$HEALTHCHECK_DELAY"
done
