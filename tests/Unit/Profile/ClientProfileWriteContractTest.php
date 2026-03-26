<?php

namespace Tests\Unit\Profile;

use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProfileWriteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_from_client_ignores_non_boundary_attributes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $profile = ClientProfile::query()->create([
            'user_id' => $owner->id,
            'name' => 'Before',
            'bonuses' => 150,
            'push_notifications' => true,
            'email_notifications' => true,
        ]);

        $profile->updateFromClient([
            'name' => 'After',
            'push_notifications' => false,
            'email_notifications' => false,
            'bonuses' => 999,
            'user_id' => $other->id,
        ]);

        $profile->refresh();

        $this->assertSame($owner->id, $profile->user_id);
        $this->assertSame(150, $profile->bonuses);
        $this->assertSame('After', $profile->name);
        $this->assertFalse($profile->push_notifications);
        $this->assertFalse($profile->email_notifications);
    }
}
