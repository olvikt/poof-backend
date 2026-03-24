#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
API_BASE_URL="${API_BASE_URL:-https://api.poof.com.ua}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://api.poof.com.ua/up}"
PHP_BIN="${PHP_BIN:-php}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
REDIS_CLI_BIN="${REDIS_CLI_BIN:-redis-cli}"
LOG_RECENT_WINDOW_MINUTES="${LOG_RECENT_WINDOW_MINUTES:-20}"

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
  local recent_lines

  cutoff_epoch="$(date -u -d "-${LOG_RECENT_WINDOW_MINUTES} minutes" +%s)"

  recent_lines="$(
    cd "$APP_DIR" && tail -n "$recent_scan_lines" "$log_file" | awk -v cutoff_epoch="$cutoff_epoch" '
      function line_epoch(line, ts) {
        if (match(line, /\[[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\]/)) {
          ts = substr(line, RSTART + 1, 19)
        } else if (match(line, /^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/)) {
          ts = substr(line, RSTART, 19)
        } else {
          return 0
        }

        gsub(/[-:]/, " ", ts)
        return mktime(ts)
      }

      {
        if (line_epoch($0) >= cutoff_epoch) {
          print $0
        }
      }
    '
  )"

  echo
  echo "==> $description"
  echo "Recent deploy-window context (best effort): last ${LOG_RECENT_WINDOW_MINUTES} minute(s) by timestamp."

  if [[ -n "$recent_lines" ]]; then
    printf '%s\n' "$recent_lines"
    return 0
  fi

  echo "No timestamp-matched lines in recent window; showing fallback tail for operator context."
  cd "$APP_DIR" && tail -n "$fallback_tail_lines" "$log_file"
}

run "HTTP availability (base URL)" curl --fail --silent --show-error --head "$API_BASE_URL"
run "Health endpoint" curl --fail --silent --show-error "$HEALTHCHECK_URL"
run "Laravel scheduler contract" bash -lc "cd '$APP_DIR' && '$PHP_BIN' artisan schedule:list"
run "Supervisor workers" "$SUPERVISORCTL_BIN" status
run "Redis ping" "$REDIS_CLI_BIN" ping
run_log_evidence "Worker log evidence" "storage/logs/worker.log" 50 400
run_log_evidence "Application log evidence" "storage/logs/laravel.log" 100 600
run "Nginx service" systemctl is-active nginx
run "PHP-FPM service" systemctl is-active php8.3-fpm
run "Redis service" systemctl is-active redis-server
run "Cron service" systemctl is-active cron
