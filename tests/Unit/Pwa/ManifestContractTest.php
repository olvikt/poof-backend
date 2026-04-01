<?php

namespace Tests\Unit\Pwa;

use PHPUnit\Framework\TestCase;

class ManifestContractTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_role_manifest_controller_exposes_client_and_courier_start_urls(): void
    {
        $controller = file_get_contents($this->repoRoot.'/app/Http/Controllers/Pwa/ManifestController.php');

        $this->assertNotFalse($controller);
        $this->assertStringContainsString("'start_url' => '/client'", $controller);
        $this->assertStringContainsString("'start_url' => '/courier'", $controller);
        $this->assertStringContainsString("'id' => '/courier'", $controller);
    }

    public function test_public_manifest_file_is_kept_for_backward_compatibility(): void
    {
        $manifestPath = $this->repoRoot.'/public/manifest.json';

        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
    }
}
