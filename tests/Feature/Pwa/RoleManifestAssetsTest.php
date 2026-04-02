<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

class RoleManifestAssetsTest extends TestCase
{
    public function test_client_manifest_uses_client_assets_only(): void
    {
        $manifest = $this->get(route('manifest.client'))
            ->assertOk()
            ->json();

        $iconSources = array_column($manifest['icons'] ?? [], 'src');
        $screenshotSources = array_column($manifest['screenshots'] ?? [], 'src');

        $this->assertContains('/assets/icons/client-icon-192.png', $iconSources);
        $this->assertContains('/assets/icons/client-icon-512.png', $iconSources);
        $this->assertContains('/assets/icons/client-icon-512-maskable.png', $iconSources);
        $this->assertContains('/assets/screenshots/client-home.png', $screenshotSources);

        foreach (array_merge($iconSources, $screenshotSources) as $src) {
            $this->assertStringNotContainsString('courier-', $src);
        }
    }

    public function test_courier_manifest_uses_courier_assets_only(): void
    {
        $manifest = $this->get(route('manifest.courier'))
            ->assertOk()
            ->json();

        $iconSources = array_column($manifest['icons'] ?? [], 'src');
        $screenshotSources = array_column($manifest['screenshots'] ?? [], 'src');

        $this->assertContains('/assets/icons/courier-icon-192.png', $iconSources);
        $this->assertContains('/assets/icons/courier-icon-512.png', $iconSources);
        $this->assertContains('/assets/icons/courier-icon-512-maskable.png', $iconSources);
        $this->assertContains('/assets/screenshots/courier-home.png', $screenshotSources);
        $this->assertTrue(collect($manifest['icons'] ?? [])->contains(fn (array $icon): bool => ($icon['src'] ?? null) === '/assets/icons/courier-icon-512-maskable.png' && ($icon['purpose'] ?? null) === 'maskable'));

        foreach (array_merge($iconSources, $screenshotSources) as $src) {
            $this->assertStringNotContainsString('client-', $src);
        }
    }

    public function test_default_manifest_route_is_client_oriented_on_client_host(): void
    {
        $manifest = $this->get('/manifest.json')
            ->assertOk()
            ->json();

        $this->assertSame('/client', $manifest['start_url'] ?? null);
        $this->assertSame('/', $manifest['id'] ?? null);
        $this->assertNotEmpty($manifest['icons'] ?? []);
    }

    public function test_default_manifest_route_is_courier_oriented_on_courier_host(): void
    {
        $manifest = $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/manifest.json')
            ->assertOk()
            ->json();

        $this->assertSame('/courier', $manifest['start_url'] ?? null);
        $this->assertSame('/courier', $manifest['scope'] ?? null);
        $this->assertSame('/courier', $manifest['id'] ?? null);
    }
}
