<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class ReleaseStateWriterContractTest extends TestCase
{
    private string $repoRoot;
    private string $tmpDir;
    private string $stateFile;
    private string $historyFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
        $this->tmpDir = sys_get_temp_dir().'/release-state-writer-'.bin2hex(random_bytes(6));
        $this->stateFile = $this->tmpDir.'/current-release.json';
        $this->historyFile = $this->tmpDir.'/release-history.jsonl';

        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            exec('rm -rf '.escapeshellarg($this->tmpDir));
        }

        parent::tearDown();
    }

    public function test_release_state_writer_records_valid_deploy_state_and_history_atomically(): void
    {
        file_put_contents($this->stateFile, json_encode([
            'release_ref' => 'release-20260328-ops-hotfix-22',
            'commit' => 'aaaabbbbccccdddd',
            'deployed_at_utc' => '2026-03-28T22:00:00Z',
            'deployment_type' => 'deploy',
            'selection_mode' => 'explicit',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $payload = json_encode([
            'release_ref' => 'release-20260330-fix-incident',
            'release_ref_kind' => 'tag',
            'requested_ref' => 'release-20260330-fix-incident',
            'resolved_ref' => 'release-20260330-fix-incident',
            'fallback_ref' => null,
            'fallback_used' => false,
            'selection_mode' => 'explicit',
            'commit' => '1111222233334444555566667777888899990000',
            'deployed_at_utc' => '2026-03-30T10:00:00Z',
            'deployment_type' => 'rollback',
            'transition_type' => 'rollback',
            'current_release_ref' => 'release-20260330-fix-incident',
            'current_commit' => '1111222233334444555566667777888899990000',
            'known_good_release_ref' => 'release-20260330-fix-incident',
            'known_good_commit' => '1111222233334444555566667777888899990000',
            'current_is_known_good' => true,
            'previous_release_ref' => 'release-20260328-ops-hotfix-22',
            'previous_commit' => 'aaaabbbbccccdddd',
            'previous_known_good_release_ref' => 'release-20260328-ops-hotfix-22',
            'previous_known_good_commit' => 'aaaabbbbccccdddd',
            'previous_deployed_at_utc' => '2026-03-28T22:00:00Z',
            'previous_deployment_type' => 'deploy',
            'previous_selection_mode' => 'explicit',
            'rollback_source_release_ref' => 'release-20260328-ops-hotfix-22',
            'rollback_source_commit' => 'aaaabbbbccccdddd',
            'rollback_target_release_ref' => 'release-20260330-fix-incident',
            'rollback_target_commit' => '1111222233334444555566667777888899990000',
            'deploy_log' => $this->tmpDir.'/logs/deploy-rollback.json',
            'deploy_runtime_evidence' => $this->tmpDir.'/logs/deploy-rollback.runtime.jsonl',
            'release_history' => $this->historyFile,
            'release_summary_required' => true,
            'release_summary_present' => true,
            'release_summary_file' => '/tmp/release-summary.md',
            'release_summary' => '- incident rollback recovery',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $this->runWriter($payload);

        $state = json_decode((string) file_get_contents($this->stateFile), true);
        $this->assertIsArray($state);
        $this->assertSame('rollback', $state['deployment_type'] ?? null);
        $this->assertSame($this->tmpDir.'/logs/deploy-rollback.json', $state['deploy_log'] ?? null);
        $this->assertSame($this->tmpDir.'/logs/deploy-rollback.runtime.jsonl', $state['deploy_runtime_evidence'] ?? null);
        $this->assertSame('release-20260328-ops-hotfix-22', $state['previous_release_ref'] ?? null);
        $this->assertSame('release-20260330-fix-incident', $state['known_good_release_ref'] ?? null);
        $this->assertSame('release-20260328-ops-hotfix-22', $state['previous_known_good_release_ref'] ?? null);
        $this->assertSame('release-20260328-ops-hotfix-22', $state['rollback_source_release_ref'] ?? null);
        $this->assertSame('release-20260330-fix-incident', $state['rollback_target_release_ref'] ?? null);
        $this->assertSame('aaaabbbbccccdddd', $state['previous_commit'] ?? null);

        $historyLines = file($this->historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotFalse($historyLines);
        $this->assertCount(1, $historyLines);

        $historyEntry = json_decode((string) $historyLines[0], true);
        $this->assertIsArray($historyEntry);
        $this->assertSame($state['commit'], $historyEntry['commit'] ?? null);
        $this->assertSame($state['deploy_runtime_evidence'], $historyEntry['deploy_runtime_evidence'] ?? null);
    }

    public function test_release_state_writer_does_not_replace_current_release_file_on_invalid_payload(): void
    {
        file_put_contents($this->stateFile, '{"release_ref":"stable-release","commit":"good"}');
        file_put_contents($this->historyFile, '{"release_ref":"stable-release","commit":"good"}'."\n");

        $script = sprintf(
            'set -euo pipefail; source %s; payload_file=%s; set +e; write_release_state_and_history "$(cat "$payload_file")" %s %s %s; exit_code=$?; set -e; exit "$exit_code"',
            escapeshellarg($this->repoRoot.'/scripts/release-state-lib.sh'),
            escapeshellarg($this->tmpDir.'/invalid-payload.json'),
            escapeshellarg($this->stateFile),
            escapeshellarg($this->historyFile),
            escapeshellarg(PHP_BINARY)
        );
        file_put_contents($this->tmpDir.'/invalid-payload.json', '{invalid json');
        exec('bash -lc '.escapeshellarg($script), $output, $exitCode);

        $this->assertSame(1, $exitCode);
        $this->assertSame('{"release_ref":"stable-release","commit":"good"}', trim((string) file_get_contents($this->stateFile)));

        $historyLines = file($this->historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotFalse($historyLines);
        $this->assertCount(1, $historyLines);
    }

    private function runWriter(string $payload): void
    {
        file_put_contents($this->tmpDir.'/payload.json', $payload);

        $script = sprintf(
            'set -euo pipefail; source %s; payload_file=%s; write_release_state_and_history "$(cat "$payload_file")" %s %s %s',
            escapeshellarg($this->repoRoot.'/scripts/release-state-lib.sh'),
            escapeshellarg($this->tmpDir.'/payload.json'),
            escapeshellarg($this->stateFile),
            escapeshellarg($this->historyFile),
            escapeshellarg(PHP_BINARY)
        );
        exec('bash -lc '.escapeshellarg($script), $output, $exitCode);

        $this->assertSame(0, $exitCode, 'release state writer script failed');
    }
}
