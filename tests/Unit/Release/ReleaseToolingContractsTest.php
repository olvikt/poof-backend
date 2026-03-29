<?php

namespace Tests\Unit\Release;

use PHPUnit\Framework\TestCase;

class ReleaseToolingContractsTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_deploy_and_rollback_scripts_keep_current_release_and_summary_contracts(): void
    {
        $deploy = file_get_contents($this->repoRoot.'/scripts/deploy.sh');
        $rollback = file_get_contents($this->repoRoot.'/scripts/rollback.sh');

        $this->assertNotFalse($deploy);
        $this->assertNotFalse($rollback);

        $this->assertStringContainsString('current-release.json', $deploy);
        $this->assertStringContainsString('release-history.jsonl', $deploy);
        $this->assertStringContainsString('release_summary', $deploy);
        $this->assertStringContainsString('release_summary_file', $deploy);

        $this->assertStringContainsString('current-release.json', $rollback);
        $this->assertStringContainsString('release-history.jsonl', $rollback);
        $this->assertStringContainsString('release_summary', $rollback);
    }
}
