#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <release-tag> <short-summary>"
  echo "Example: $0 release-20260325-1200 \"Checkout race fix; auth guard hardening\""
  exit 1
fi

RELEASE_TAG="$1"
SHORT_SUMMARY="$2"
SHORT_SUMMARY_TRIMMED="$(echo "$SHORT_SUMMARY" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
RELEASE_SUMMARY_DIR="${RELEASE_SUMMARY_DIR:-$APP_DIR/docs/release-summaries}"
SUMMARY_FILE="$RELEASE_SUMMARY_DIR/$RELEASE_TAG.md"
CREATED_AT_UTC="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

if [[ -z "$SHORT_SUMMARY_TRIMMED" ]]; then
  echo "[release-summary] short summary must be non-empty" >&2
  exit 1
fi

mkdir -p "$RELEASE_SUMMARY_DIR"

if [[ -f "$SUMMARY_FILE" ]]; then
  echo "[release-summary] summary file already exists: $SUMMARY_FILE" >&2
  exit 1
fi

cat > "$SUMMARY_FILE" <<EOF
# $RELEASE_TAG

$SHORT_SUMMARY_TRIMMED

Created at (UTC): $CREATED_AT_UTC
EOF

echo "[release-summary] created: $SUMMARY_FILE"
