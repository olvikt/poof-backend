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

    public function test_manifest_exists_and_is_valid_json(): void
    {
        $manifestPath = $this->repoRoot.'/public/manifest.json';

        $this->assertFileExists($manifestPath);

        $manifestContents = file_get_contents($manifestPath);

        $this->assertNotFalse($manifestContents);

        $manifest = json_decode($manifestContents, true);

        $this->assertIsArray($manifest);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());
    }

    public function test_manifest_keeps_the_documented_core_pwa_contract(): void
    {
        $manifest = json_decode(file_get_contents($this->repoRoot.'/public/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('/', $manifest['id'] ?? null);
        $this->assertSame('POOF — швидкий винос сміття', $manifest['name'] ?? null);
        $this->assertSame('POOF', $manifest['short_name'] ?? null);
        $this->assertSame('/client', $manifest['start_url'] ?? null);
        $this->assertSame('/', $manifest['scope'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertSame('#18191f', $manifest['background_color'] ?? null);
        $this->assertSame('#18191f', $manifest['theme_color'] ?? null);

        $icons = $manifest['icons'] ?? [];
        $this->assertNotEmpty($icons);
        $this->assertContains('/assets/icons/icon-192.png', array_column($icons, 'src'));
        $this->assertContains('/assets/icons/icon-512.png', array_column($icons, 'src'));

        $shortcuts = $manifest['shortcuts'] ?? [];
        $this->assertNotEmpty($shortcuts);
        $this->assertSame(
            ['/client/order/create', '/client/orders', '/client/profile'],
            array_column($shortcuts, 'url')
        );
    }
}
