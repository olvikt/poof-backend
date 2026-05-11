#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

runtime_ready=false
missing_vendor=false
artisan_bootstrap=false
composer_network_blocked=false

if [[ ! -f vendor/autoload.php ]]; then
  missing_vendor=true
fi

if php artisan --version >/tmp/poof_artisan_version.out 2>/tmp/poof_artisan_version.err; then
  artisan_bootstrap=true
else
  artisan_bootstrap=false
fi

if command -v composer >/dev/null 2>&1; then
  if composer diagnose >/tmp/poof_composer_diag.out 2>/tmp/poof_composer_diag.err; then
    composer_network_blocked=false
  else
    if rg -q "CONNECT tunnel failed|curl error 56|403" /tmp/poof_composer_diag.err /tmp/poof_composer_diag.out 2>/dev/null; then
      composer_network_blocked=true
    fi
  fi
fi

if [[ "$missing_vendor" == "false" && "$artisan_bootstrap" == "true" ]]; then
  runtime_ready=true
fi

echo "runtime_ready=${runtime_ready}"
echo "missing_vendor=${missing_vendor}"
echo "artisan_bootstrap=${artisan_bootstrap}"
echo "composer_network_blocked=${composer_network_blocked}"
