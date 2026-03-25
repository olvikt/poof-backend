<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class CheckServerScriptContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_check_server_log_evidence_stays_deploy_relative_with_labeled_best_effort_fallback(): void
    {
        $script = file_get_contents($this->repoRoot.'/scripts/check-server.sh');

        $this->assertNotFalse($script);
        $this->assertStringContainsString('LOG_RECENT_WINDOW_MINUTES="${LOG_RECENT_WINDOW_MINUTES:-20}"', $script);
        $this->assertStringContainsString('DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"', $script);
        $this->assertStringContainsString('run "Readiness endpoint contract" bash -lc', $script);
        $this->assertStringContainsString('[[ "$response" == "ok" ]]', $script);
        $this->assertStringContainsString('Recent deploy-window context (best effort):', $script);
        $this->assertStringContainsString('filtering timestamped lines since ${cutoff_label}.', $script);
        $this->assertStringContainsString('No timestamp-matched lines in derived deploy window; showing fallback tail for operator context.', $script);
        $this->assertStringContainsString('run_log_evidence "Worker log evidence" "storage/logs/worker.log" 50 400', $script);
        $this->assertStringContainsString('run_log_evidence "Application log evidence" "storage/logs/laravel.log" 100 600', $script);
    }
}
