#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <git-ref>"
  echo "Example: $0 HEAD~1"
  exit 1
fi

TARGET_REF="$1"
APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"

cd "$APP_DIR"

echo "[rollback] target: $TARGET_REF"

git fetch --all --tags
git reset --hard "$TARGET_REF"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader
"$PHP_BIN" artisan optimize
"$SUPERVISORCTL_BIN" restart poof-worker:*

echo "[rollback] done"
