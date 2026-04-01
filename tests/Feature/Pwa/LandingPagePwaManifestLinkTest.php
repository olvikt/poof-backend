<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

class LandingPagePwaManifestLinkTest extends TestCase
{
    public function test_client_landing_renders_client_manifest_link(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="'.route('manifest.client').'">', false);
    }

    public function test_courier_landing_renders_courier_manifest_link(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="'.route('manifest.courier').'">', false);
    }
}
