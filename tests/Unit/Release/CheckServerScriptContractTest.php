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

    public function test_check_server_prefers_explicit_operator_contracts_with_degraded_fallbacks(): void
    {
        $script = file_get_contents($this->repoRoot.'/scripts/check-server.sh');

        $this->assertNotFalse($script);
        $this->assertStringContainsString('LOG_RECENT_WINDOW_MINUTES="${LOG_RECENT_WINDOW_MINUTES:-20}"', $script);
        $this->assertStringContainsString('DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"', $script);
        $this->assertStringContainsString('DEPLOY_RUNTIME_EVIDENCE_FILE="${DEPLOY_RUNTIME_EVIDENCE_FILE:-}"', $script);
        $this->assertStringContainsString('run_contract_with_degraded_fallback()', $script);
        $this->assertStringContainsString('run_deploy_runtime_evidence()', $script);
        $this->assertStringContainsString('run "Readiness endpoint contract" bash -lc', $script);
        $this->assertStringContainsString('run "Runtime cache/queue/session contract (production-like)" bash -lc', $script);
        $this->assertStringContainsString('cache default resolves to database store in production-like runtime', $script);
        $this->assertStringContainsString('runtime resolves to database/sqlite cache lock path in production-like runtime', $script);
        $this->assertStringContainsString('"cache_store_driver" => (string) data_get(config("cache.stores"), config("cache.default").".driver", "")', $script);
        $this->assertStringContainsString('[[ "$response" == "ok" ]]', $script);
        $this->assertStringContainsString("artisan ops:contract:scheduler --max-age-seconds=180", $script);
        $this->assertStringContainsString("artisan ops:contract:workers --program-prefix=poof-worker:", $script);
        $this->assertStringContainsString("raw supervisorctl status + worker log evidence", $script);
        $this->assertStringContainsString('"deploy_runtime_evidence"', $script);
        $this->assertStringContainsString('Recent deploy-window context (best effort):', $script);
        $this->assertStringContainsString('filtering timestamped lines since ${cutoff_label}.', $script);
        $this->assertStringContainsString('run_log_evidence "Application log evidence (degraded fallback)" "storage/logs/laravel.log" 100 600', $script);
        $this->assertStringContainsString('run_deploy_runtime_evidence "Deploy/runtime evidence contract"', $script);
    }
}
