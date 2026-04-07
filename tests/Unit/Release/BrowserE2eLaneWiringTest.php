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
        $this->assertStringContainsString('echo "SESSION_DOMAIN=null"', $workflow);
        $this->assertStringContainsString('echo "SANCTUM_STATEFUL_DOMAINS=127.0.0.1,127.0.0.1:8000,localhost,localhost:3000,::1"', $workflow);
    }

    public function test_env_example_keeps_local_safe_session_defaults_for_ci_and_dev(): void
    {
        $envExample = file_get_contents($this->repoRoot.'/.env.example');

        $this->assertNotFalse($envExample);
        $this->assertStringContainsString('APP_URL=http://localhost', $envExample);
        $this->assertStringContainsString('SESSION_DOMAIN=null', $envExample);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=false', $envExample);
        $this->assertStringContainsString('SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1', $envExample);
    }

    public function test_production_domain_split_guidance_remains_documented_even_with_local_safe_env_defaults(): void
    {
        $readme = file_get_contents($this->repoRoot.'/README.md');

        $this->assertNotFalse($readme);
        $this->assertStringContainsString('app.poof.com.ua', $readme);
        $this->assertStringContainsString('api.poof.com.ua', $readme);
        $this->assertStringContainsString('For production, set explicit values in server `.env`', $readme);
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

    public function test_browser_e2e_seeder_resets_courier_fixture_to_idle_before_lane_execution(): void
    {
        $seeder = file_get_contents($this->repoRoot.'/database/seeders/BrowserE2eSeeder.php');

        $this->assertNotFalse($seeder);
        $this->assertStringContainsString('private function resetCourierFixtureToIdleState(User $courierUser): void', $seeder);
        $this->assertStringContainsString("->where('courier_id', $courierUser->id)", $seeder);
        $this->assertStringContainsString('Order::STATUS_ACCEPTED', $seeder);
        $this->assertStringContainsString('Order::STATUS_IN_PROGRESS', $seeder);
        $this->assertStringContainsString("\$courierUser->markFree();", $seeder);
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
        $this->assertStringContainsString('Ви не на лінії', $spec);
        $this->assertStringContainsString("data-e2e-online-state", $spec);
        $this->assertStringContainsString("/livewire/update", $spec);
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
        $this->assertStringContainsString('data-e2e-online-state="{{ $online ? \'online\' : \'offline\' }}"', $onlineToggle);
        $this->assertStringContainsString('data-e2e-busy="{{ $busyWithActiveOrder ? \'1\' : \'0\' }}"', $onlineToggle);
        $this->assertStringContainsString('data-e2e="courier-accept-offer"', $offerCard);
    }

    public function test_app_runtime_bootstraps_livewire_with_alpine_fallback_for_e2e_interactions(): void
    {
        $appJs = file_get_contents($this->repoRoot.'/resources/js/app.js');

        $this->assertNotFalse($appJs);
        $this->assertStringContainsString("from '../../vendor/livewire/livewire/dist/livewire.esm'", $appJs);
        $this->assertStringContainsString('const livewire = window.Livewire ?? Livewire ?? null', $appJs);
        $this->assertStringContainsString('const alpine = window.Alpine ?? LivewireAlpine ?? Alpine', $appJs);
        $this->assertStringContainsString('window.Livewire = livewire', $appJs);
        $this->assertStringContainsString('window.Alpine = alpine', $appJs);
        $this->assertStringContainsString('livewire.start()', $appJs);
    }
}
