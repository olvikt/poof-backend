<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

class LandingPagePwaManifestLinkTest extends TestCase
{
    public function test_client_landing_renders_client_manifest_link(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('/manifest-client.json');
    }

    public function test_courier_landing_renders_courier_manifest_link(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua', 'SERVER_NAME' => 'courier.poof.com.ua'])->get('/');

        $response->assertOk();
        $response->assertSee('/manifest-courier.json');
    }
}
