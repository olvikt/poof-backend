<?php

declare(strict_types=1);

namespace Tests\Unit\Release;

use Database\Seeders\BrowserE2eSeeder;
use PHPUnit\Framework\TestCase;

class BrowserE2eLaneWiringTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_browser_e2e_seeder_class_is_autoloadable(): void
    {
        $this->assertTrue(class_exists(BrowserE2eSeeder::class));
    }

    public function test_browser_e2e_workflow_uses_non_fragile_short_seed_class_invocation(): void
    {
        $workflow = file_get_contents($this->repoRoot.'/.github/workflows/tests.yml');

        $this->assertNotFalse($workflow);
        $this->assertStringContainsString('php artisan migrate:fresh --force', $workflow);
        $this->assertStringContainsString('php artisan db:seed --class=BrowserE2eSeeder --force', $workflow);
        $this->assertStringNotContainsString('--seeder=Database\\Seeders\\BrowserE2eSeeder', $workflow);
    }

    public function test_browser_e2e_workflow_uses_project_local_playwright_commands(): void
    {
        $workflow = file_get_contents($this->repoRoot.'/.github/workflows/tests.yml');

        $this->assertNotFalse($workflow);
        $this->assertStringContainsString('run: npm ci', $workflow);
        $this->assertStringContainsString('run: npm run e2e:install', $workflow);
        $this->assertStringContainsString('run: npm run e2e:test', $workflow);
        $this->assertStringNotContainsString('npx --yes playwright@1.53.2', $workflow);
    }

    public function test_browser_e2e_workflow_uses_persistent_session_driver_for_real_login_flow(): void
    {
        $workflow = file_get_contents($this->repoRoot.'/.github/workflows/tests.yml');

        $this->assertNotFalse($workflow);
        $this->assertStringContainsString('echo "SESSION_DRIVER=file"', $workflow);
    }

    public function test_package_manifest_pins_project_local_playwright_dependency_and_scripts(): void
    {
        $packageJson = file_get_contents($this->repoRoot.'/package.json');

        $this->assertNotFalse($packageJson);
        $package = json_decode((string) $packageJson, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('1.53.2', $package['devDependencies']['@playwright/test'] ?? null);
        $this->assertSame('playwright install --with-deps chromium', $package['scripts']['e2e:install'] ?? null);
        $this->assertSame('playwright test --config=playwright.config.js', $package['scripts']['e2e:test'] ?? null);
    }

    public function test_browser_e2e_client_address_seed_payload_avoids_removed_is_verified_column(): void
    {
        $seeder = file_get_contents($this->repoRoot.'/database/seeders/BrowserE2eSeeder.php');

        $this->assertNotFalse($seeder);

        preg_match('/\$address\s*=\s*ClientAddress::updateOrCreate\((.*?)\);/s', (string) $seeder, $matches);
        $this->assertNotEmpty($matches, 'ClientAddress::updateOrCreate payload block must exist in BrowserE2eSeeder');

        $clientAddressPayloadBlock = $matches[1];

        $this->assertStringNotContainsString("'is_verified' =>", $clientAddressPayloadBlock);
        $this->assertStringContainsString("'geocoded_at' => now()", $clientAddressPayloadBlock);
    }

    public function test_browser_e2e_seeder_keeps_expected_login_credentials_contract(): void
    {
        $seeder = file_get_contents($this->repoRoot.'/database/seeders/BrowserE2eSeeder.php');

        $this->assertNotFalse($seeder);
        $this->assertStringContainsString("'email' => 'client@test.com'", $seeder);
        $this->assertStringContainsString("'email' => 'courier@poof.app'", $seeder);
        $this->assertStringContainsString("'password' => Hash::make('password')", $seeder);
        $this->assertStringContainsString("'is_active' => true", $seeder);
    }

    public function test_browser_e2e_auth_helper_exposes_actionable_login_failure_context(): void
    {
        $helper = file_get_contents($this->repoRoot.'/tests/e2e/helpers/auth.js');

        $this->assertNotFalse($helper);
        $this->assertStringContainsString("Невірний email/телефон або пароль", $helper);
        $this->assertStringContainsString('E2E login failed for', $helper);
    }
}
