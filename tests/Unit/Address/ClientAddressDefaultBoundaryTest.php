<?php

namespace Tests\Unit\Address;

use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAddressDefaultBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_default_for_user_keeps_single_default_within_owner_scope(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerPrimary = ClientAddress::query()->create([
            'user_id' => $owner->id,
            'label' => 'home',
            'street' => 'Owner street 1',
            'house' => '1',
            'is_default' => true,
        ]);

        $ownerSecondary = ClientAddress::query()->create([
            'user_id' => $owner->id,
            'label' => 'work',
            'street' => 'Owner street 2',
            'house' => '2',
            'is_default' => false,
        ]);

        $otherAddress = ClientAddress::query()->create([
            'user_id' => $other->id,
            'label' => 'home',
            'street' => 'Other street',
            'house' => '7',
            'is_default' => true,
        ]);

        $ownerSecondary->markAsDefaultForUser();

        $this->assertDatabaseHas('client_addresses', [
            'id' => $ownerSecondary->id,
            'user_id' => $owner->id,
            'is_default' => 1,
        ]);

        $this->assertDatabaseHas('client_addresses', [
            'id' => $ownerPrimary->id,
            'user_id' => $owner->id,
            'is_default' => 0,
        ]);

        $this->assertDatabaseHas('client_addresses', [
            'id' => $otherAddress->id,
            'user_id' => $other->id,
            'is_default' => 1,
        ]);
    }
}
