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
        $this->assertStringContainsString('"known_good_release_ref": "$RESOLVED_REF"', $deploy);
        $this->assertStringContainsString('"previous_known_good_release_ref": $(release_state_json_string_or_null "$PREVIOUS_KNOWN_GOOD_RELEASE_REF" "$PHP_BIN")', $deploy);
        $this->assertStringContainsString('"rollback_target_release_ref": null', $deploy);
        $this->assertStringContainsString('release_gate_file_for_ref()', $deploy);
        $this->assertStringContainsString('[deploy] validating mandatory pre-deploy gate (runtime contract + browser smoke)', $deploy);
        $this->assertStringContainsString('release blocked: pre-deploy gate artifact is missing', $deploy);
        $this->assertStringContainsString('browser smoke checklist item failed/missing', $deploy);
        $this->assertStringContainsString('[deploy] pre-deploy release gate: PASS', $deploy);
        $this->assertStringContainsString('write_release_state_and_history "$DEPLOY_STATE_PAYLOAD" "$DEPLOY_STATE_FILE" "$RELEASE_HISTORY_FILE" "$PHP_BIN"', $deploy);

        $this->assertStringContainsString('source "$SCRIPT_DIR/release-state-lib.sh"', $rollback);
        $this->assertStringContainsString('current-release.json', $rollback);
        $this->assertStringContainsString('release-history.jsonl', $rollback);
        $this->assertStringContainsString('"release_summary_required": $RELEASE_SUMMARY_REQUIRED', $rollback);
        $this->assertStringContainsString('"release_summary_present": $RELEASE_SUMMARY_PRESENT', $rollback);
        $this->assertStringContainsString('"release_summary_file": $(release_state_json_string_or_null "$RELEASE_SUMMARY_FILE" "$PHP_BIN")', $rollback);
        $this->assertStringContainsString('"release_summary": $(release_state_json_string_or_null "$RELEASE_SUMMARY_TEXT" "$PHP_BIN")', $rollback);
        $this->assertStringContainsString('"deployment_type": "rollback"', $rollback);
        $this->assertStringContainsString('"known_good_release_ref": "$RESOLVED_REF"', $rollback);
        $this->assertStringContainsString('"previous_known_good_release_ref": $(release_state_json_string_or_null "$PREVIOUS_KNOWN_GOOD_RELEASE_REF" "$PHP_BIN")', $rollback);
        $this->assertStringContainsString('"rollback_target_release_ref": "$RESOLVED_REF"', $rollback);
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
        $this->assertStringContainsString('MERGED_HEAD_REF="${MERGED_HEAD_REF:-origin/main}"', $showRelease);
        $this->assertStringContainsString('print_section(\'Current release\')', $showRelease);
        $this->assertStringContainsString('print_section(\'Previous known-good release\')', $showRelease);
        $this->assertStringContainsString('print_section(\'Rollback context\')', $showRelease);
        $this->assertStringContainsString('print_section(\'Incident summary\')', $showRelease);
        $this->assertStringContainsString('print_section("Recent release transitions (last {$historyLimit})")', $showRelease);
        $this->assertStringContainsString("echo 'Merged state gap (informational)'", $showRelease);
        $this->assertStringContainsString('Merged PRs ahead of confirmed production', $showRelease);
    }

    public function test_pre_deploy_gate_script_requires_runtime_contract_and_browser_smoke_attestation(): void
    {
        $script = file_get_contents($this->repoRoot.'/scripts/prepare-release-gate.sh');

        $this->assertNotFalse($script);
        $this->assertStringContainsString('BROWSER_SMOKE_EVIDENCE is required', $script);
        $this->assertStringContainsString('SMOKE_HOME_OK', $script);
        $this->assertStringContainsString('SMOKE_CLIENT_ORDER_CREATE_OK', $script);
        $this->assertStringContainsString('SMOKE_PROFILE_ADDRESS_AVATAR_EDIT_OK', $script);
        $this->assertStringContainsString('SMOKE_COURIER_AVAILABLE_MY_ORDERS_OK', $script);
        $this->assertStringContainsString('SMOKE_CRITICAL_POPUPS_CAROUSELS_OK', $script);
        $this->assertStringContainsString('scripts/check-server.sh', $script);
        $this->assertStringContainsString('"gate_type" => "pre_deploy_release_gate"', $script);
        $this->assertStringContainsString('"browser_smoke" => [', $script);
        $this->assertStringContainsString('"status" => "passed"', $script);
    }
}
