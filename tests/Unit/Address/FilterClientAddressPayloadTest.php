<?php

namespace Tests\Unit\Address;

use App\Services\Address\FilterClientAddressPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FilterClientAddressPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_only_real_client_address_columns(): void
    {
        $service = new FilterClientAddressPayload();

        $payload = $service->execute([
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
            'not_a_column' => 'drop-me',
        ]);

        $columns = Schema::getColumnListing('client_addresses');

        $this->assertSame([
            'label' => 'home',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
        ], $payload);
        $this->assertContains('label', $columns);
        $this->assertNotContains('not_a_column', array_keys($payload));
    }
}
