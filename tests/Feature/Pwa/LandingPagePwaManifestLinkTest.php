<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

class LandingPagePwaManifestLinkTest extends TestCase
{
    public function test_landing_page_renders_the_documented_manifest_link(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
    }
}
