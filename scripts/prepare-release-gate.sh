#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

RELEASE_REF="${RELEASE_REF:-${1:-}}"
GATE_OPERATOR="${GATE_OPERATOR:-${USER:-unknown}}"
BROWSER_SMOKE_EVIDENCE="${BROWSER_SMOKE_EVIDENCE:-}"
GATE_STATE_DIR="${GATE_STATE_DIR:-$APP_DIR/storage/app/release-gates}"
GATE_TTL_MINUTES="${GATE_TTL_MINUTES:-180}"

require_yes() {
  local value="$1"
  [[ "$value" == "yes" || "$value" == "YES" || "$value" == "true" || "$value" == "1" ]]
}

if [[ -z "$RELEASE_REF" ]]; then
  echo "[release-gate] usage: bash scripts/prepare-release-gate.sh <release-ref>" >&2
  exit 1
fi

if [[ -z "$BROWSER_SMOKE_EVIDENCE" ]]; then
  echo "[release-gate] BROWSER_SMOKE_EVIDENCE is required (ticket/link/screenshot pack id)" >&2
  exit 1
fi

for var_name in \
  SMOKE_HOME_OK \
  SMOKE_CLIENT_ORDER_CREATE_OK \
  SMOKE_PROFILE_ADDRESS_AVATAR_EDIT_OK \
  SMOKE_COURIER_AVAILABLE_MY_ORDERS_OK \
  SMOKE_CRITICAL_POPUPS_CAROUSELS_OK
  do
  current_value="${!var_name:-}"
  if ! require_yes "$current_value"; then
    echo "[release-gate] ${var_name} must be explicitly confirmed with yes/true/1" >&2
    exit 1
  fi
done

cd "$APP_DIR"
git fetch --prune --tags origin > /dev/null

if ! git rev-parse --verify --quiet "$RELEASE_REF^{commit}" > /dev/null; then
  echo "[release-gate] unknown release ref: $RELEASE_REF" >&2
  exit 1
fi

RESOLVED_COMMIT="$(git rev-parse "$RELEASE_REF^{commit}")"
mkdir -p "$GATE_STATE_DIR"

GATE_REF_HASH="$(printf '%s' "$RELEASE_REF" | sha1sum | awk '{print $1}')"
GATE_FILE="$GATE_STATE_DIR/${GATE_REF_HASH}.json"
GATE_GENERATED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
GATE_EXPIRES_AT="$(date -u -d "+${GATE_TTL_MINUTES} minutes" +"%Y-%m-%dT%H:%M:%SZ")"

echo "[release-gate] running blocking production-like runtime contract via scripts/check-server.sh"
"$SCRIPT_DIR/check-server.sh"

echo "[release-gate] recording mandatory browser smoke attestation"

"$PHP_BIN" -r '
  $payload = [
    "gate_type" => "pre_deploy_release_gate",
    "release_ref" => $argv[1],
    "resolved_commit" => $argv[2],
    "generated_at_utc" => $argv[3],
    "expires_at_utc" => $argv[4],
    "operator" => $argv[5],
    "runtime_contract" => [
      "status" => "passed",
      "runner" => "scripts/check-server.sh",
    ],
    "browser_smoke" => [
      "status" => "passed",
      "evidence" => $argv[6],
      "checklist" => [
        "home_page" => true,
        "client_order_create" => true,
        "address_profile_avatar_edit" => true,
        "courier_available_orders_and_my_orders" => true,
        "critical_popups_carousels_click_flows" => true,
      ],
    ],
  ];

  file_put_contents($argv[7], json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
' "$RELEASE_REF" "$RESOLVED_COMMIT" "$GATE_GENERATED_AT" "$GATE_EXPIRES_AT" "$GATE_OPERATOR" "$BROWSER_SMOKE_EVIDENCE" "$GATE_FILE"

echo "[release-gate] gate PASS"
echo "[release-gate] release_ref=$RELEASE_REF"
echo "[release-gate] resolved_commit=$RESOLVED_COMMIT"
echo "[release-gate] gate_file=$GATE_FILE"
echo "[release-gate] expires_at_utc=$GATE_EXPIRES_AT"
echo "[release-gate] deploy now requires this gate artifact"
