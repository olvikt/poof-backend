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
        $this->assertStringContainsString('echo "ASSET_URL=http://127.0.0.1:8000"', $workflow);
    }

    public function test_browser_e2e_workflow_guards_against_production_asset_origin_leakage(): void
    {
        $workflow = file_get_contents($this->repoRoot.'/.github/workflows/tests.yml');

        $this->assertNotFalse($workflow);
        $this->assertStringContainsString('Assert e2e pages do not leak production asset origin', $workflow);
        $this->assertStringContainsString('https://poof.com.ua/build/assets', $workflow);
        $this->assertStringContainsString('grep -qE', $workflow);
        $this->assertStringNotContainsString(' | rg -q', $workflow);
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

    public function test_browser_e2e_spec_uses_stable_selectors_instead_of_livewire_css_paths(): void
    {
        $spec = file_get_contents($this->repoRoot.'/tests/e2e/specs/minimal-blocking-interactions.spec.js');

        $this->assertNotFalse($spec);
        $this->assertStringContainsString("getByLabel('Вулиця')", $spec);
        $this->assertStringContainsString("getByTestId('client-order-submit')", $spec);
        $this->assertStringContainsString("Вийти з акаунту", $spec);
        $this->assertStringContainsString("getByTestId('courier-online-toggle')", $spec);
        $this->assertStringContainsString('Пошук замовлень...', $spec);
        $this->assertStringNotContainsString('wire\\:model\\.live', $spec);
    }

    public function test_browser_e2e_template_hooks_required_by_blocking_lane_exist(): void
    {
        $orderCreate = file_get_contents($this->repoRoot.'/resources/views/livewire/client/order-create.blade.php');
        $bottomSheet = file_get_contents($this->repoRoot.'/resources/views/components/poof/ui/bottom-sheet.blade.php');
        $onlineToggle = file_get_contents($this->repoRoot.'/resources/views/livewire/courier/online-toggle.blade.php');
        $offerCard = file_get_contents($this->repoRoot.'/resources/views/livewire/courier/offer-card.blade.php');

        $this->assertNotFalse($orderCreate);
        $this->assertNotFalse($bottomSheet);
        $this->assertNotFalse($onlineToggle);
        $this->assertNotFalse($offerCard);

        $this->assertStringContainsString('data-e2e="open-address-picker"', $orderCreate);
        $this->assertStringContainsString('data-e2e="address-picker-item"', $orderCreate);
        $this->assertStringContainsString('data-e2e="client-order-submit"', $orderCreate);
        $this->assertStringContainsString('data-e2e="{{ $name }}-sheet-panel"', $bottomSheet);
        $this->assertStringContainsString('data-e2e="courier-online-toggle"', $onlineToggle);
        $this->assertStringContainsString('data-e2e="courier-accept-offer"', $offerCard);
    }
}
