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

    public function test_deploy_and_rollback_scripts_keep_current_release_history_and_summary_contracts(): void
    {
        $deploy = file_get_contents($this->repoRoot.'/scripts/deploy.sh');
        $rollback = file_get_contents($this->repoRoot.'/scripts/rollback.sh');

        $this->assertNotFalse($deploy);
        $this->assertNotFalse($rollback);

        $this->assertStringContainsString('source "$SCRIPT_DIR/release-state-lib.sh"', $deploy);
        $this->assertStringContainsString('current-release.json', $deploy);
        $this->assertStringContainsString('release-history.jsonl', $deploy);
        $this->assertStringContainsString('"release_summary_required": $RELEASE_SUMMARY_REQUIRED', $deploy);
        $this->assertStringContainsString('"release_summary_present": $RELEASE_SUMMARY_PRESENT', $deploy);
        $this->assertStringContainsString('"release_summary_file": $(release_state_json_string_or_null "$RELEASE_SUMMARY_FILE" "$PHP_BIN")', $deploy);
        $this->assertStringContainsString('"release_summary": $(release_state_json_string_or_null "$RELEASE_SUMMARY_TEXT" "$PHP_BIN")', $deploy);
        $this->assertStringContainsString('"deployment_type": "deploy"', $deploy);
        $this->assertStringContainsString('write_release_state_and_history "$DEPLOY_STATE_PAYLOAD" "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE" "$PHP_BIN"', $deploy);

        $this->assertStringContainsString('source "$SCRIPT_DIR/release-state-lib.sh"', $rollback);
        $this->assertStringContainsString('current-release.json', $rollback);
        $this->assertStringContainsString('release-history.jsonl', $rollback);
        $this->assertStringContainsString('"release_summary_required": $RELEASE_SUMMARY_REQUIRED', $rollback);
        $this->assertStringContainsString('"release_summary_present": $RELEASE_SUMMARY_PRESENT', $rollback);
        $this->assertStringContainsString('"release_summary_file": $(release_state_json_string_or_null "$RELEASE_SUMMARY_FILE" "$PHP_BIN")', $rollback);
        $this->assertStringContainsString('"release_summary": $(release_state_json_string_or_null "$RELEASE_SUMMARY_TEXT" "$PHP_BIN")', $rollback);
        $this->assertStringContainsString('"deployment_type": "rollback"', $rollback);
        $this->assertStringContainsString('write_release_state_and_history "$DEPLOY_STATE_PAYLOAD" "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE" "$PHP_BIN"', $rollback);
    }

    public function test_show_release_script_exposes_current_release_contract_summary_and_invariants(): void
    {
        $showRelease = file_get_contents($this->repoRoot.'/scripts/show-release.sh');

        $this->assertNotFalse($showRelease);

        $this->assertStringContainsString('DEPLOY_STATE_FILE="${DEPLOY_STATE_FILE:-$APP_DIR/storage/app/current-release.json}"', $showRelease);
        $this->assertStringContainsString('RELEASE_HISTORY_FILE="${RELEASE_HISTORY_FILE:-$APP_DIR/storage/app/release-history.jsonl}"', $showRelease);
        $this->assertStringContainsString('release_summary_required', $showRelease);
        $this->assertStringContainsString('release_summary_present', $showRelease);
        $this->assertStringContainsString('release_summary_file', $showRelease);
        $this->assertStringContainsString('release_summary', $showRelease);
        $this->assertStringContainsString('print_section(\'Current release\')', $showRelease);
        $this->assertStringContainsString('print_section(\'Previous known-good release\')', $showRelease);
        $this->assertStringContainsString('print_section("Recent release transitions (last {$historyLimit})")', $showRelease);
    }
}
