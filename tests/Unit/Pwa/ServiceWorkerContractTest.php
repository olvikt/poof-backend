<?php

namespace Tests\Unit\Pwa;

use PHPUnit\Framework\TestCase;

class ServiceWorkerContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_service_worker_keeps_an_explicit_cache_version_and_minimal_precache(): void
    {
        $sw = file_get_contents($this->repoRoot.'/public/sw.js');

        $this->assertNotFalse($sw);
        $this->assertMatchesRegularExpression('/const\\s+CACHE_VERSION\\s*=\\s*["\'][^"\']+["\']/', $sw);
        $this->assertStringContainsString('"/"', $sw);
        $this->assertStringContainsString('"/manifest.json"', $sw);
    }

    public function test_service_worker_explicitly_excludes_api_and_build_requests_from_cache_handling(): void
    {
        $sw = file_get_contents($this->repoRoot.'/public/sw.js');

        $this->assertNotFalse($sw);
        $this->assertStringContainsString('url.pathname.startsWith("/api/")', $sw);
        $this->assertStringContainsString('url.pathname.startsWith("/build/")', $sw);
        $this->assertStringContainsString('if (event.request.method !== "GET")', $sw);
    }

    public function test_service_worker_runtime_cache_scope_stays_limited_to_same_origin_visual_assets(): void
    {
        $sw = file_get_contents($this->repoRoot.'/public/sw.js');

        $this->assertNotFalse($sw);
        $this->assertStringContainsString('url.origin === self.location.origin', $sw);
        $this->assertStringContainsString('url.pathname.startsWith("/images/")', $sw);
        $this->assertStringContainsString('url.pathname.startsWith("/assets/images/")', $sw);
        $this->assertStringContainsString('url.pathname.startsWith("/assets/icons/")', $sw);
    }
}
