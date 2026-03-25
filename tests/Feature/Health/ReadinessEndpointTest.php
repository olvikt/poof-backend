<?php

namespace Tests\Feature\Health;

use Tests\TestCase;

class ReadinessEndpointTest extends TestCase
{
    public function test_readyz_returns_stable_plain_text_contract(): void
    {
        $response = $this->get('/readyz');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('ok');

        $this->assertSame('ok', $response->getContent());
    }
}
