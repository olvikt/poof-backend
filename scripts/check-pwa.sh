#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
APP_BASE_URL="${APP_BASE_URL:-${API_BASE_URL:-https://api.poof.com.ua}}"
CURL_BIN="${CURL_BIN:-curl}"
PHP_BIN="${PHP_BIN:-php}"
TMP_DIR="$(mktemp -d)"
MANIFEST_RESPONSE="$TMP_DIR/manifest.json"
SW_RESPONSE="$TMP_DIR/sw.js"
LANDING_RESPONSE="$TMP_DIR/landing.html"
LANDING_HEADERS="$TMP_DIR/landing.headers"
LOCAL_BUILD_MANIFEST="$APP_DIR/public/build/manifest.json"

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

pass() {
  echo "[PASS] $1"
}

fail() {
  echo "[FAIL] $1" >&2
  exit 1
}

note() {
  echo "[INFO] $1"
}

fetch() {
  local url="$1"
  local body_path="$2"
  shift 2

  "$CURL_BIN" --fail --silent --show-error --location "$@" "$url" -o "$body_path"
}

fetch_headers() {
  local url="$1"
  local header_path="$2"

  "$CURL_BIN" --fail --silent --show-error --location -D "$header_path" -o /dev/null "$url"
}

assert_contains() {
  local file_path="$1"
  local pattern="$2"
  local description="$3"

  if ! grep -Eq "$pattern" "$file_path"; then
    fail "$description"
  fi

  pass "$description"
}

cd "$APP_DIR"

note "PWA smoke target: $APP_BASE_URL"
note "App dir: $APP_DIR"

fetch "$APP_BASE_URL/manifest.json" "$MANIFEST_RESPONSE"
pass "GET /manifest.json returns 200"

"$PHP_BIN" -r '
  $json = file_get_contents($argv[1]);
  $data = json_decode($json, true);
  if (!is_array($data)) {
      fwrite(STDERR, "manifest.json is not valid JSON\n");
      exit(1);
  }
' "$MANIFEST_RESPONSE"
pass "/manifest.json response is valid JSON"

fetch "$APP_BASE_URL/sw.js" "$SW_RESPONSE"
pass "GET /sw.js returns 200"

fetch "$APP_BASE_URL" "$LANDING_RESPONSE"
pass "Landing page returns 200"

fetch_headers "$APP_BASE_URL" "$LANDING_HEADERS"
pass "Landing page headers captured"

assert_contains "$LANDING_RESPONSE" '<link[^>]+rel=["'"'']manifest["'"''][^>]+href=["'"'']/manifest\.json["'"'']' 'Landing page contains <link rel="manifest" href="/manifest.json">'

if [[ ! -f "$LOCAL_BUILD_MANIFEST" ]]; then
  fail "Local build manifest not found at $LOCAL_BUILD_MANIFEST; cannot verify rendered Vite asset paths"
fi

"$PHP_BIN" -r '
  $html = file_get_contents($argv[1]);
  $manifestPath = $argv[2];
  $manifest = json_decode(file_get_contents($manifestPath), true);

  if (!is_array($manifest)) {
      fwrite(STDERR, "local build manifest is not valid JSON\n");
      exit(1);
  }

  $expectedEntries = ["resources/css/app.css", "resources/js/app.js"];

  foreach ($expectedEntries as $entry) {
      if (!isset($manifest[$entry]["file"])) {
          fwrite(STDERR, "missing Vite manifest entry for {$entry}\n");
          exit(1);
      }

      $assetPath = "/build/" . ltrim($manifest[$entry]["file"], "/");
      if (strpos($html, $assetPath) === false) {
          fwrite(STDERR, "landing page does not reference current Vite asset {$assetPath}\n");
          exit(1);
      }

      if ($entry === "resources/js/app.js" && isset($manifest[$entry]["css"]) && is_array($manifest[$entry]["css"])) {
          foreach ($manifest[$entry]["css"] as $cssAsset) {
              $cssPath = "/build/" . ltrim($cssAsset, "/");
              if (strpos($html, $cssPath) === false) {
                  fwrite(STDERR, "landing page does not reference imported CSS asset {$cssPath}\n");
                  exit(1);
              }
          }
      }
  }
' "$LANDING_RESPONSE" "$LOCAL_BUILD_MANIFEST"
pass "Landing page references current Vite build asset paths from public/build/manifest.json"

if grep -Eiq '^Cache-Control: .*no-cache' "$LANDING_HEADERS" \
  || grep -Eiq '^Cache-Control: .*max-age=0' "$LANDING_HEADERS" \
  || grep -Eiq '^ETag:' "$LANDING_HEADERS" \
  || grep -Eiq '^Last-Modified:' "$LANDING_HEADERS"; then
  pass "Landing page headers expose observable HTML revalidation signals"
else
  fail "Landing page headers do not expose an observable HTML revalidation signal (expected Cache-Control no-cache/max-age=0 and/or ETag/Last-Modified)"
fi

note "PWA smoke completed successfully"
