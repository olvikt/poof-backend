<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class ReleaseWorkflowContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_deploy_script_keeps_the_documented_blocking_release_contract(): void
    {
        $script = $this->normalizedFile('scripts/deploy.sh');

        $this->assertStringContainsString('DEFAULT_DEPLOY_REF="${DEFAULT_DEPLOY_REF:-origin/main}"', $script);
        $this->assertStringContainsString('EXPLICIT_DEPLOY_REF="${DEPLOY_REF:-${1:-}}"', $script);
        $this->assertStringContainsString('WARNING: no explicit release ref provided; falling back to legacy path', $script);
        $this->assertStringContainsString('git fetch --prune --tags origin', $script);
        $this->assertStringContainsString('git rev-parse --verify --quiet "$DEPLOY_REF^{commit}"', $script);
        $this->assertStringContainsString('npm run build', $script);
        $this->assertStringContainsString('test -f public/build/manifest.json', $script);
        $this->assertStringContainsString('"$PHP_BIN" artisan migrate --force', $script);
        $this->assertStringContainsString('curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null', $script);
        $this->assertStringContainsString('"fallback_used": $([[ "$FALLBACK_DEPLOY_USED" -eq 1 ]] && echo true || echo false)', $script);
        $this->assertStringContainsString('"selection_mode": "$DEPLOY_SELECTION_MODE"', $script);
        $this->assertStringContainsString('append_history_entry "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE"', $script);
        $this->assertStringContainsString('current release state was not updated; previous known-good release remains recorded', $script);

        $healthCheckPosition = strpos($script, 'curl --fail --silent --show-error "$HEALTHCHECK_URL" > /dev/null');
        $recordStatePosition = strpos($script, 'echo "[deploy] recording release state"');

        $this->assertNotFalse($healthCheckPosition);
        $this->assertNotFalse($recordStatePosition);
        $this->assertLessThan($recordStatePosition, $healthCheckPosition);
    }

    public function test_show_release_script_renders_current_previous_and_history_sections_from_release_state(): void
    {
        $tmpDir = sys_get_temp_dir().'/poof-show-release-'.bin2hex(random_bytes(6));
        mkdir($tmpDir.'/storage/app', 0777, true);

        $stateFile = $tmpDir.'/storage/app/current-release.json';
        $historyFile = $tmpDir.'/storage/app/release-history.jsonl';

        file_put_contents($stateFile, json_encode([
            'release_ref' => 'release-20260323-1200',
            'deployment_type' => 'deploy',
            'requested_ref' => 'release-20260323-1200',
            'resolved_ref' => 'release-20260323-1200',
            'fallback_ref' => 'origin/main',
            'fallback_used' => false,
            'selection_mode' => 'explicit',
            'commit' => 'abc123',
            'deployed_at_utc' => '2026-03-23T12:00:00Z',
            'previous_release_ref' => 'release-20260322-0900',
            'previous_commit' => 'def456',
            'previous_deployed_at_utc' => '2026-03-22T09:00:00Z',
            'previous_deployment_type' => 'deploy',
            'previous_selection_mode' => 'explicit',
            'deploy_log' => '/tmp/deploy-log.json',
            'release_history' => $historyFile,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $historyEntries = [
            [
                'release_ref' => 'release-20260323-1200',
                'deployment_type' => 'deploy',
                'commit' => 'abc123',
                'deployed_at_utc' => '2026-03-23T12:00:00Z',
                'previous_release_ref' => 'release-20260322-0900',
                'fallback_ref' => 'origin/main',
                'fallback_used' => false,
                'selection_mode' => 'explicit',
            ],
            [
                'release_ref' => 'release-20260322-0900',
                'deployment_type' => 'rollback',
                'commit' => 'def456',
                'deployed_at_utc' => '2026-03-22T09:00:00Z',
                'previous_release_ref' => 'release-20260321-0800',
                'fallback_ref' => 'origin/main',
                'fallback_used' => true,
                'selection_mode' => 'fallback',
            ],
        ];

        file_put_contents(
            $historyFile,
            implode(PHP_EOL, array_map(static fn (array $entry): string => json_encode($entry, JSON_UNESCAPED_SLASHES), $historyEntries)).PHP_EOL
        );

        $command = sprintf(
            'APP_DIR=%s DEPLOY_STATE_FILE=%s RELEASE_HISTORY_FILE=%s HISTORY_LIMIT=2 PHP_BIN=%s bash %s/scripts/show-release.sh 2>&1',
            escapeshellarg($tmpDir),
            escapeshellarg($stateFile),
            escapeshellarg($historyFile),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->repoRoot)
        );

        exec($command, $outputLines, $exitCode);
        $output = implode(PHP_EOL, $outputLines);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Current release', $output);
        $this->assertStringContainsString('Mode: EXPLICIT release ref', $output);
        $this->assertStringContainsString('Release ref: release-20260323-1200', $output);
        $this->assertStringContainsString('Selection mode: explicit', $output);
        $this->assertStringContainsString('Fallback used: no', $output);
        $this->assertStringContainsString('Previous known-good release', $output);
        $this->assertStringContainsString('Recent release transitions (last 2)', $output);
        $this->assertStringContainsString('rollback | release-20260322-0900 | def456 | previous=release-20260321-0800 | FALLBACK legacy path (fallback ref: origin/main)', $output);
    }

    public function test_check_server_script_keeps_the_mandatory_post_deploy_smoke_sequence(): void
    {
        $script = $this->normalizedFile('scripts/check-server.sh');

        $requiredChecks = [
            'run "HTTP availability (base URL)" curl --fail --silent --show-error --head "$API_BASE_URL"',
            'run "Health endpoint" curl --fail --silent --show-error "$HEALTHCHECK_URL"',
            'run "Nginx service" systemctl is-active nginx',
            'run "PHP-FPM service" systemctl is-active php8.3-fpm',
            'run "Redis service" systemctl is-active redis-server',
            'run "Cron service" systemctl is-active cron',
            'run "Supervisor workers" "$SUPERVISORCTL_BIN" status',
            'run "Redis ping" "$REDIS_CLI_BIN" ping',
            "run \"Laravel scheduler contract\" bash -lc \"cd '$APP_DIR' && '$PHP_BIN' artisan schedule:list\"",
            "run \"Worker log tail\" bash -lc \"cd '$APP_DIR' && tail -n 50 storage/logs/worker.log\"",
            "run \"Application log tail\" bash -lc \"cd '$APP_DIR' && tail -n 100 storage/logs/laravel.log\"",
        ];

        foreach ($requiredChecks as $check) {
            $this->assertStringContainsString($check, $script);
        }
    }

    public function test_blocking_ci_workflow_includes_release_contract_regression_suite(): void
    {
        $workflow = $this->normalizedFile('.github/workflows/tests.yml');

        $this->assertStringContainsString('Run minimal blocking release suite', $workflow);
        $this->assertStringContainsString('tests/Feature/Api/OrderStoreTest.php', $workflow);
        $this->assertStringContainsString('tests/Unit/OrderLifecycleStatusContractTest.php', $workflow);
        $this->assertStringContainsString('tests/Feature/Courier/AcceptFlowArchitectureRegressionTest.php', $workflow);
        $this->assertStringContainsString('tests/Unit/Release/ReleaseWorkflowContractTest.php', $workflow);
        $this->assertStringContainsString('tests/Feature/Api/GeocodeControllerTest.php', $workflow);
        $this->assertStringContainsString('tests/Unit/Address/PrepareAddressSavePayloadTest.php', $workflow);
    }

    private function normalizedFile(string $relativePath): string
    {
        $contents = file_get_contents($this->repoRoot.'/'.$relativePath);

        $this->assertIsString($contents, sprintf('Failed to read %s', $relativePath));

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }
}
