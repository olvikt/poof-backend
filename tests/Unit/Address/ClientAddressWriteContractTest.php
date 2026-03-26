<?php

namespace Tests\Unit\Address;

use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAddressWriteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_for_user_ignores_non_boundary_attributes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $address = ClientAddress::createForUser($owner->id, [
            'user_id' => $other->id,
            'city' => 'Dnipro',
            'street' => 'Boundary Street',
            'house' => '7',
            'is_default' => true,
            'created_at' => now()->subYear(),
        ]);

        $address->refresh();

        $this->assertSame($owner->id, $address->user_id);
        $this->assertTrue($address->is_default);
        $this->assertSame('Dnipro', $address->city);
        $this->assertSame('Boundary Street', $address->street);
        $this->assertSame('7', $address->house);
    }

    public function test_update_from_client_ignores_non_boundary_attributes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $address = ClientAddress::query()->create([
            'user_id' => $owner->id,
            'city' => 'Kyiv',
            'street' => 'Before',
            'house' => '1',
            'is_default' => true,
        ]);

        $address->updateFromClient([
            'city' => 'Lviv',
            'street' => 'After',
            'user_id' => $other->id,
            'is_default' => false,
            'created_at' => now()->subDay(),
        ]);

        $address->refresh();

        $this->assertSame($owner->id, $address->user_id);
        $this->assertFalse($address->is_default);
        $this->assertSame('Lviv', $address->city);
        $this->assertSame('After', $address->street);
    }
}
