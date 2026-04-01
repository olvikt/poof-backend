<?php

namespace Tests\Unit\Pwa;

use PHPUnit\Framework\TestCase;

class FrontendPwaContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_landing_sources_link_role_specific_manifests_and_use_vite_entrypoints(): void
    {
        $clientView = file_get_contents($this->repoRoot.'/resources/views/welcome.blade.php');
        $courierView = file_get_contents($this->repoRoot.'/resources/views/welcome-courier.blade.php');

        $this->assertNotFalse($clientView);
        $this->assertNotFalse($courierView);
        $this->assertStringContainsString("route('manifest.client')", $clientView);
        $this->assertStringContainsString("route('manifest.courier')", $courierView);
        $this->assertStringContainsString("@vite(['resources/css/app.css','resources/js/app.js'])", $clientView);
        $this->assertStringContainsString("@vite(['resources/css/app.css','resources/js/app.js'])", $courierView);
    }

    public function test_vite_config_keeps_app_js_in_the_build_inputs(): void
    {
        $viteConfig = file_get_contents($this->repoRoot.'/vite.config.js');

        $this->assertNotFalse($viteConfig);
        $this->assertStringContainsString("'resources/js/app.js'", $viteConfig);
        $this->assertStringContainsString("'resources/css/app.css'", $viteConfig);
    }

    public function test_app_js_still_registers_the_service_worker_from_sw_js_on_window_load(): void
    {
        $appJs = file_get_contents($this->repoRoot.'/resources/js/app.js');

        $this->assertNotFalse($appJs);
        $this->assertStringContainsString("if ('serviceWorker' in navigator)", $appJs);
        $this->assertStringContainsString("window.addEventListener('load'", $appJs);
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $appJs);
    }
}
