#!/usr/bin/env bash

release_state_json_string_or_null() {
  local value="$1"
  local php_bin="${2:-php}"

  if [[ -z "$value" ]]; then
    echo null
  else
    "$php_bin" -r 'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES);' -- "$value"
  fi
}

write_release_state_and_history() {
  local state_payload="$1"
  local state_file="$2"
  local history_file="$3"
  local php_bin="${4:-php}"
  local state_dir
  local history_dir
  local state_tmp
  local history_tmp

  state_dir="$(dirname "$state_file")"
  history_dir="$(dirname "$history_file")"
  mkdir -p "$state_dir" "$history_dir"

  state_tmp="$(mktemp "$state_dir/current-release.json.tmp.XXXXXX")"
  history_tmp="$(mktemp "$history_dir/release-history.jsonl.tmp.XXXXXX")"

  cleanup_release_state_tmp() {
    rm -f "$state_tmp" "$history_tmp"
  }
  trap cleanup_release_state_tmp RETURN

  printf '%s\n' "$state_payload" > "$state_tmp"

  if ! "$php_bin" -r '
      $data = json_decode((string) file_get_contents($argv[1]), true);
      if (!is_array($data)) {
          fwrite(STDERR, "failed to decode staged release state\n");
          exit(1);
      }
    ' "$state_tmp"; then
    return 1
  fi

  if [[ -f "$history_file" ]]; then
    cat "$history_file" > "$history_tmp"
  else
    : > "$history_tmp"
  fi

  if ! "$php_bin" -r '
      $data = json_decode((string) file_get_contents($argv[1]), true);
      if (!is_array($data)) {
          fwrite(STDERR, "failed to decode release state for history append\n");
          exit(1);
      }
      echo json_encode($data, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    ' "$state_tmp" >> "$history_tmp"; then
    return 1
  fi

  mv -f "$state_tmp" "$state_file"
  mv -f "$history_tmp" "$history_file"
}
