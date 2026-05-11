#!/usr/bin/env bash
set -euo pipefail

# Delivery mechanism C: mounted/shared artifact directory.
# Usage:
#   scripts/fetch-runtime-artifact.sh <commit-sha> [dest-file]
# Env:
#   RUNTIME_ARTIFACTS_DIR (default: /mnt/runtime-artifacts)
#   RUNTIME_ARTIFACT_PREFIX (default: runtime-vendor-)

commit_sha="${1:-}"
dest_file="${2:-vendor.tar.gz}"

if [[ -z "$commit_sha" ]]; then
  echo 'result=error'
  echo 'reason=missing_commit_sha'
  echo 'artifact_delivered=false'
  exit 2
fi

artifacts_dir="${RUNTIME_ARTIFACTS_DIR:-/mnt/runtime-artifacts}"
artifact_prefix="${RUNTIME_ARTIFACT_PREFIX:-runtime-vendor-}"

candidate_name="${artifact_prefix}${commit_sha}.tar.gz"
source_path="${artifacts_dir}/${candidate_name}"
sha_file="${source_path}.sha256"

if [[ ! -f "$source_path" ]]; then
  echo 'result=error'
  echo 'reason=artifact_not_found_in_shared_dir'
  echo "artifacts_dir=$artifacts_dir"
  echo "expected_artifact=$candidate_name"
  echo 'artifact_delivered=false'
  exit 3
fi

cp "$source_path" "$dest_file"
actual_sha="$(sha256sum "$dest_file" | awk '{print $1}')"

if [[ -f "$sha_file" ]]; then
  expected_sha="$(awk '{print $1}' "$sha_file")"
  if [[ "$expected_sha" != "$actual_sha" ]]; then
    echo 'result=error'
    echo 'reason=sha256_mismatch'
    echo "expected_sha256=$expected_sha"
    echo "actual_sha256=$actual_sha"
    echo 'artifact_delivered=false'
    exit 4
  fi
  echo "expected_sha256=$expected_sha"
else
  echo 'sha256_source=local_compute_only'
fi

echo "artifact_path=$dest_file"
echo "artifact_sha256=$actual_sha"
echo 'result=ok'
echo 'reason=artifact_delivered_from_shared_dir'
echo 'artifact_delivered=true'
