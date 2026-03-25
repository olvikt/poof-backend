#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
API_BASE_URL="${API_BASE_URL:-https://api.poof.com.ua}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://api.poof.com.ua/readyz}"
PHP_BIN="${PHP_BIN:-php}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
REDIS_CLI_BIN="${REDIS_CLI_BIN:-redis-cli}"
LOG_RECENT_WINDOW_MINUTES="${LOG_RECENT_WINDOW_MINUTES:-20}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"

run() {
  local description="$1"
  shift

  echo
  echo "==> $description"
  "$@"
}

run_log_evidence() {
  local description="$1"
  local log_file="$2"
  local fallback_tail_lines="$3"
  local recent_scan_lines="$4"
  local cutoff_epoch
  local now_epoch
  local deploy_epoch
  local deploy_started_at
  local deploy_commit
  local release_ref
  local requested_ref
  local cutoff_label
  local windowed_lines
  local marker_lines
  local recent_buffer

  now_epoch="$(date -u +%s)"
  cutoff_epoch="$(date -u -d "-${LOG_RECENT_WINDOW_MINUTES} minutes" +%s)"
  cutoff_label="rolling ${LOG_RECENT_WINDOW_MINUTES} minute window"
  deploy_started_at=""
  deploy_commit=""
  release_ref=""
  requested_ref=""

  if [[ -f "$DEPLOY_STATE_FILE" ]]; then
    mapfile -t _deploy_state_fields < <(
      "$PHP_BIN" -r '
        $data = json_decode(@file_get_contents($argv[1]), true);
        if (!is_array($data)) {
            exit(0);
        }
        $fields = ["deployed_at_utc", "commit", "release_ref", "requested_ref"];
        foreach ($fields as $field) {
            $value = trim((string) ($data[$field] ?? ""));
            echo $value, PHP_EOL;
        }
      ' "$DEPLOY_STATE_FILE"
    )
    deploy_started_at="${_deploy_state_fields[0]:-}"
    deploy_commit="${_deploy_state_fields[1]:-}"
    release_ref="${_deploy_state_fields[2]:-}"
    requested_ref="${_deploy_state_fields[3]:-}"
  fi

  if [[ -n "$deploy_started_at" ]]; then
    deploy_epoch="$(date -u -d "$deploy_started_at" +%s 2>/dev/null || true)"
    if [[ -n "$deploy_epoch" ]] && [[ "$deploy_epoch" -le "$now_epoch" ]] && [[ "$deploy_epoch" -gt "$cutoff_epoch" ]]; then
      cutoff_epoch="$deploy_epoch"
      cutoff_label="current release deployed_at_utc ($deploy_started_at)"
    fi
  fi

  recent_buffer="$(mktemp)"
  trap 'rm -f "$recent_buffer"' RETURN

  cd "$APP_DIR" && tail -n "$recent_scan_lines" "$log_file" > "$recent_buffer"

  windowed_lines="$(
    TZ=UTC awk -v cutoff_epoch="$cutoff_epoch" '
      function line_epoch(line, ts) {
        if (match(line, /\[[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}:[0-9]{2}/)) {
          ts = substr(line, RSTART + 1, 19)
        } else if (match(line, /^[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}:[0-9]{2}/)) {
          ts = substr(line, RSTART, 19)
        } else {
          return 0
        }

        gsub(/T/, " ", ts)
        gsub(/[-:]/, " ", ts)
        return mktime(ts)
      }

      {
        if (line_epoch($0) >= cutoff_epoch) {
          print $0
        }
      }
    ' "$recent_buffer"
  )"

  marker_lines="$(
    RECENT_BUFFER="$recent_buffer" DEPLOY_COMMIT="$deploy_commit" RELEASE_REF="$release_ref" REQUESTED_REF="$requested_ref" DEPLOY_STARTED_AT="$deploy_started_at" bash <<'BASH'
set -euo pipefail
max_line=0

if [[ -n "${DEPLOY_COMMIT:-}" ]]; then
  short_commit="${DEPLOY_COMMIT:0:12}"
  for pattern in "$DEPLOY_COMMIT" "$short_commit"; do
    line_number="$(grep -nF "$pattern" "$RECENT_BUFFER" | tail -n 1 | cut -d: -f1 || true)"
    if [[ -n "$line_number" ]] && [[ "$line_number" -gt "$max_line" ]]; then
      max_line="$line_number"
    fi
  done
fi

for pattern in "${RELEASE_REF:-}" "${REQUESTED_REF:-}"; do
  if [[ -z "$pattern" ]]; then
    continue
  fi
  line_number="$(grep -nF "$pattern" "$RECENT_BUFFER" | tail -n 1 | cut -d: -f1 || true)"
  if [[ -n "$line_number" ]] && [[ "$line_number" -gt "$max_line" ]]; then
    max_line="$line_number"
  fi
done

if [[ -n "${DEPLOY_STARTED_AT:-}" ]]; then
  deploy_date="${DEPLOY_STARTED_AT%%T*}"
  line_number="$(grep -nF "$deploy_date" "$RECENT_BUFFER" | tail -n 1 | cut -d: -f1 || true)"
  if [[ -n "$line_number" ]] && [[ "$line_number" -gt "$max_line" ]]; then
    max_line="$line_number"
  fi
fi

if [[ "$max_line" -gt 0 ]]; then
  start_line=$((max_line - 20))
  if [[ "$start_line" -lt 1 ]]; then
    start_line=1
  fi
  end_line=$((max_line + 20))
  sed -n "${start_line},${end_line}p" "$RECENT_BUFFER"
fi
BASH
  )"

  echo
  echo "==> $description"
  echo "Recent deploy-window context (best effort): filtering timestamped lines since ${cutoff_label}."

  if [[ -n "$windowed_lines" ]]; then
    printf '%s\n' "$windowed_lines"
    return 0
  fi

  if [[ -n "$marker_lines" ]]; then
    echo "No timestamp-window match; showing bounded context around latest deploy marker (commit/ref/date) in recent log slice."
    printf '%s\n' "$marker_lines"
    return 0
  fi

  echo "No deploy-window or marker evidence found in recent scan; degraded mode: showing fallback tail for operator context."
  cd "$APP_DIR" && tail -n "$fallback_tail_lines" "$log_file"
}

run "HTTP availability (base URL)" curl --fail --silent --show-error --head "$API_BASE_URL"
run "Readiness endpoint contract" bash -lc 'response=$(curl --fail --silent --show-error "$1"); [[ "$response" == "ok" ]]' _ "$HEALTHCHECK_URL"
run "Laravel scheduler contract" bash -lc "cd '$APP_DIR' && '$PHP_BIN' artisan schedule:list"
run "Supervisor workers" "$SUPERVISORCTL_BIN" status
run "Redis ping" "$REDIS_CLI_BIN" ping
run_log_evidence "Worker log evidence" "storage/logs/worker.log" 50 400
run_log_evidence "Application log evidence" "storage/logs/laravel.log" 100 600
run "Nginx service" systemctl is-active nginx
run "PHP-FPM service" systemctl is-active php8.3-fpm
run "Redis service" systemctl is-active redis-server
run "Cron service" systemctl is-active cron
