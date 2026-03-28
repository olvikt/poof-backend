<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class DeployScriptRuntimeEvidenceContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_deploy_script_records_compact_runtime_evidence_for_current_deploy_window(): void
    {
        $script = file_get_contents($this->repoRoot.'/scripts/deploy.sh');

        $this->assertNotFalse($script);
        $this->assertStringContainsString('append_runtime_evidence()', $script);
        $this->assertStringContainsString('DEPLOY_RUNTIME_EVIDENCE_FILE="${DEPLOY_RUNTIME_EVIDENCE_FILE:-$DEPLOY_LOG_DIR/', $script);
        $this->assertStringContainsString('append_runtime_evidence "deploy_started" "ok"', $script);
        $this->assertStringContainsString('append_runtime_evidence "workers_restarted" "ok"', $script);
        $this->assertStringContainsString('append_runtime_evidence "health_check_passed" "ok" "{\\"attempt\\":$attempt}"', $script);
        $this->assertStringContainsString('append_runtime_evidence "release_state_recorded" "ok"', $script);
        $this->assertStringContainsString('"deploy_runtime_evidence": "$DEPLOY_RUNTIME_EVIDENCE_FILE"', $script);
    }

    public function test_deploy_script_json_string_or_null_handles_dash_prefixed_release_summary(): void
    {
        $script = file_get_contents($this->repoRoot.'/scripts/deploy.sh');

        $this->assertNotFalse($script);
        $this->assertStringContainsString(
            '"$PHP_BIN" -r \'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES);\' -- "$value"',
            $script
        );
        $this->assertStringContainsString(
            '"release_summary": $(json_string_or_null "$RELEASE_SUMMARY_TEXT")',
            $script
        );
        $this->assertStringContainsString('append_history_entry "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE"', $script);
    }

    public function test_rollback_script_rejects_empty_tag_release_summary_before_writing_state(): void
    {
        $script = file_get_contents($this->repoRoot.'/scripts/rollback.sh');

        $this->assertNotFalse($script);
        $this->assertStringContainsString('if [[ -z "$RELEASE_SUMMARY_TEXT" ]]; then', $script);
        $this->assertStringContainsString('[rollback] empty release summary for tag $RESOLVED_REF: $RELEASE_SUMMARY_FILE', $script);
        $this->assertStringContainsString('release state satisfies summary contract', $script);
        $this->assertStringContainsString('exit 1', $script);
    }
}
