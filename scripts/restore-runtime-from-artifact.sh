#!/usr/bin/env bash
set -euo pipefail

artifact_path="${1:-}"

if [[ -z "$artifact_path" ]]; then
  echo 'result=error'
  echo 'reason=missing_argument'
  echo 'runtime_restored=false'
  exit 2
fi

if [[ ! -f "$artifact_path" ]]; then
  echo 'result=error'
  echo 'reason=artifact_not_found'
  echo "artifact_path=$artifact_path"
  echo 'runtime_restored=false'
  exit 3
fi

echo "artifact_path=$artifact_path"
sha256_value="$(sha256sum "$artifact_path" | awk '{print $1}')"
echo "artifact_sha256=$sha256_value"

tar -xzf "$artifact_path"

if [[ ! -f vendor/autoload.php ]]; then
  echo 'result=error'
  echo 'reason=missing_vendor_autoload'
  echo 'runtime_restored=false'
  exit 4
fi

artisan_output="$(php artisan --version 2>&1)"
echo "artisan_version=$artisan_output"

echo 'result=ok'
echo 'reason=runtime_restored_from_artifact'
echo 'runtime_restored=true'
