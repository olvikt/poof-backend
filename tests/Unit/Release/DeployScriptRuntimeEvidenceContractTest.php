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
}
