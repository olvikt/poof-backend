#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"
RELEASE_HISTORY_FILE="${RELEASE_HISTORY_FILE:-$APP_DIR/storage/app/release-history.jsonl}"
HISTORY_LIMIT="${HISTORY_LIMIT:-5}"
MERGED_HEAD_REF="${MERGED_HEAD_REF:-origin/main}"

if ! [[ "$HISTORY_LIMIT" =~ ^[1-9][0-9]*$ ]]; then
  echo "[show-release] HISTORY_LIMIT must be a positive integer" >&2
  exit 1
fi

if [[ ! -f "$DEPLOY_STATE_FILE" ]]; then
  echo "[show-release] current release state file not found: $DEPLOY_STATE_FILE" >&2
  exit 1
fi

"$PHP_BIN" /dev/stdin "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE" "$HISTORY_LIMIT" <<'PHP'
<?php
$stateFile = $argv[1];
$historyFile = $argv[2];
$historyLimit = (int) $argv[3];

function load_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function yes_no($value): string
{
    return $value ? 'yes' : 'no';
}

function stringify($value, string $default = '<none>'): string
{
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    $value = trim((string) $value);
    return $value === '' ? $default : $value;
}

function deployment_mode(array $state): string
{
    $selectionMode = stringify($state['selection_mode'] ?? null);
    $fallbackUsed = !empty($state['fallback_used']);

    if ($fallbackUsed || $selectionMode === 'fallback') {
        $fallbackRef = stringify($state['fallback_ref'] ?? null);
        return "FALLBACK legacy path (fallback ref: {$fallbackRef})";
    }

    return 'EXPLICIT release ref';
}

function format_summary(array $state): array
{
    return [
        'release_ref' => stringify($state['release_ref'] ?? null),
        'release_ref_kind' => stringify($state['release_ref_kind'] ?? null),
        'deployment_type' => stringify($state['deployment_type'] ?? null),
        'requested_ref' => stringify($state['requested_ref'] ?? null),
        'resolved_ref' => stringify($state['resolved_ref'] ?? null),
        'commit' => stringify($state['commit'] ?? null),
        'deployed_at_utc' => stringify($state['deployed_at_utc'] ?? null),
        'selection_mode' => stringify($state['selection_mode'] ?? null),
        'fallback_used' => yes_no(!empty($state['fallback_used'])),
        'previous_release_ref' => stringify($state['previous_release_ref'] ?? null),
        'previous_commit' => stringify($state['previous_commit'] ?? null),
        'previous_deployed_at_utc' => stringify($state['previous_deployed_at_utc'] ?? null),
        'previous_deployment_type' => stringify($state['previous_deployment_type'] ?? null),
        'previous_selection_mode' => stringify($state['previous_selection_mode'] ?? null),
        'deploy_log' => stringify($state['deploy_log'] ?? null),
        'release_history' => stringify($state['release_history'] ?? $GLOBALS['historyFile']),
        'release_summary_required' => yes_no(!empty($state['release_summary_required'])),
        'release_summary_present' => yes_no(!empty($state['release_summary_present'])),
        'release_summary_file' => stringify($state['release_summary_file'] ?? null),
        'release_summary' => stringify($state['release_summary'] ?? null),
    ];
}

function print_section(string $title): void
{
    echo $title, PHP_EOL;
    echo str_repeat('-', strlen($title)), PHP_EOL;
}

$state = load_json_file($stateFile);
if ($state === null) {
    fwrite(STDERR, "[show-release] failed to parse current release state: {$stateFile}\n");
    exit(1);
}

$summary = format_summary($state);

print_section('Current release');
echo 'Mode: ', deployment_mode($state), PHP_EOL;
echo 'Release ref: ', $summary['release_ref'], PHP_EOL;
echo 'Release ref kind: ', $summary['release_ref_kind'], PHP_EOL;
echo 'Deployment type: ', $summary['deployment_type'], PHP_EOL;
echo 'Requested ref: ', $summary['requested_ref'], PHP_EOL;
echo 'Resolved ref: ', $summary['resolved_ref'], PHP_EOL;
echo 'Commit: ', $summary['commit'], PHP_EOL;
echo 'Deployed at (UTC): ', $summary['deployed_at_utc'], PHP_EOL;
echo 'Selection mode: ', $summary['selection_mode'], PHP_EOL;
echo 'Fallback used: ', $summary['fallback_used'], PHP_EOL;
echo 'Deploy log: ', $summary['deploy_log'], PHP_EOL;
echo 'History file: ', $summary['release_history'], PHP_EOL;
echo 'Release summary required: ', $summary['release_summary_required'], PHP_EOL;
echo 'Release summary present: ', $summary['release_summary_present'], PHP_EOL;
echo 'Release summary file: ', $summary['release_summary_file'], PHP_EOL;
echo 'Release summary: ', $summary['release_summary'], PHP_EOL;
echo PHP_EOL;

print_section('Previous known-good release');
echo 'Release ref: ', $summary['previous_release_ref'], PHP_EOL;
echo 'Deployment type: ', $summary['previous_deployment_type'], PHP_EOL;
echo 'Commit: ', $summary['previous_commit'], PHP_EOL;
echo 'Deployed at (UTC): ', $summary['previous_deployed_at_utc'], PHP_EOL;
echo 'Selection mode: ', $summary['previous_selection_mode'], PHP_EOL;
echo PHP_EOL;

print_section("Recent release transitions (last {$historyLimit})");
if (!is_file($historyFile)) {
    echo "History file not found: {$historyFile}", PHP_EOL;
    exit(0);
}

$lines = file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
if ($lines === []) {
    echo "No release history entries recorded yet.", PHP_EOL;
    exit(0);
}

$recent = array_slice($lines, -$historyLimit);
$recent = array_reverse($recent);

foreach ($recent as $index => $line) {
    $entry = json_decode($line, true);
    if (!is_array($entry)) {
        echo '- [invalid history entry] ', $line, PHP_EOL;
        continue;
    }

    $mode = deployment_mode($entry);
    $deploymentType = stringify($entry['deployment_type'] ?? null);
    $releaseRef = stringify($entry['release_ref'] ?? null);
    $commit = stringify($entry['commit'] ?? null);
    $deployedAt = stringify($entry['deployed_at_utc'] ?? null);
    $previousRelease = stringify($entry['previous_release_ref'] ?? null);

    echo sprintf(
        "%d. %s | %s | %s | %s | previous=%s | %s",
        $index + 1,
        $deployedAt,
        $deploymentType,
        $releaseRef,
        $commit,
        $previousRelease,
        $mode
    ), PHP_EOL;
}
PHP

CONFIRMED_COMMIT="$($PHP_BIN -r '
  $state = json_decode((string) file_get_contents($argv[1]), true);
  if (!is_array($state)) {
      exit(1);
  }
  echo trim((string) ($state["commit"] ?? ""));
' "$DEPLOY_STATE_FILE")"

echo
echo 'Merged state gap (informational)'
echo '--------------------------------'

if [[ -z "$CONFIRMED_COMMIT" ]]; then
  echo 'Confirmed production commit: <none>'
  echo 'Gap analysis: unavailable (missing commit in release state)'
  exit 0
fi

if ! git -C "$APP_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Confirmed production commit: $CONFIRMED_COMMIT"
  echo "Gap analysis: unavailable (APP_DIR is not a git work tree: $APP_DIR)"
  exit 0
fi

if ! git -C "$APP_DIR" rev-parse --verify --quiet "$CONFIRMED_COMMIT^{commit}" >/dev/null; then
  echo "Confirmed production commit: $CONFIRMED_COMMIT"
  echo 'Gap analysis: unavailable (confirmed commit is not resolvable in local git object set)'
  exit 0
fi

if ! MERGED_HEAD_COMMIT="$(git -C "$APP_DIR" rev-parse --verify --quiet "$MERGED_HEAD_REF^{commit}")"; then
  echo "Confirmed production commit: $CONFIRMED_COMMIT"
  echo "Merged head ref: $MERGED_HEAD_REF (<unresolvable>)"
  echo 'Gap analysis: unavailable (set MERGED_HEAD_REF to a resolvable branch/ref)'
  exit 0
fi

echo "Confirmed production commit: $CONFIRMED_COMMIT"
echo "Merged head ref: $MERGED_HEAD_REF"
echo "Merged head commit: $MERGED_HEAD_COMMIT"

if [[ "$CONFIRMED_COMMIT" == "$MERGED_HEAD_COMMIT" ]]; then
  echo 'Ahead commits: 0 (confirmed production state matches merged head ref)'
  exit 0
fi

if git -C "$APP_DIR" merge-base --is-ancestor "$CONFIRMED_COMMIT" "$MERGED_HEAD_COMMIT" >/dev/null 2>&1; then
  AHEAD_COUNT="$(git -C "$APP_DIR" rev-list --count "$CONFIRMED_COMMIT..$MERGED_HEAD_COMMIT")"
  echo "Ahead commits: $AHEAD_COUNT"
  echo "Ahead range: $CONFIRMED_COMMIT..$MERGED_HEAD_COMMIT"

  PR_LIST="$(git -C "$APP_DIR" log --merges --first-parent --pretty=%s "$CONFIRMED_COMMIT..$MERGED_HEAD_COMMIT" | sed -n 's/^Merge pull request #\([0-9]\+\).*/#\1/p' | tr '\n' ' ' | sed 's/[[:space:]]*$//')"
  if [[ -n "$PR_LIST" ]]; then
    echo "Merged PRs ahead of confirmed production: $PR_LIST"
  else
    echo 'Merged PRs ahead of confirmed production: <none detected on first-parent merges>'
  fi
else
  echo 'Ahead commits: <non-linear history>'
  echo 'Gap analysis: confirmed production commit is not an ancestor of merged head ref'
fi
