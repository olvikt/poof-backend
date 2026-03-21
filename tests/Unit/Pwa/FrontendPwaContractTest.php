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

    public function test_landing_page_source_links_the_manifest_and_uses_vite_entrypoints(): void
    {
        $welcomeView = file_get_contents($this->repoRoot.'/resources/views/welcome.blade.php');

        $this->assertNotFalse($welcomeView);
        $this->assertStringContainsString('<link rel="manifest" href="/manifest.json">', $welcomeView);
        $this->assertStringContainsString("@vite(['resources/css/app.css','resources/js/app.js'])", $welcomeView);
        $this->assertStringNotContainsString('/build/assets/', $welcomeView);
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
