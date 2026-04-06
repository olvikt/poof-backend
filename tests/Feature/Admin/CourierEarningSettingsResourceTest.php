<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\CourierEarningSettingResource;
use App\Models\CourierEarningSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierEarningSettingsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_courier_commission_settings_resource(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $this->actingAs($client);
        $this->assertFalse(CourierEarningSettingResource::canViewAny());

        $this->actingAs($admin);
        $this->assertTrue(CourierEarningSettingResource::canViewAny());

        $setting = CourierEarningSetting::current();

        $setting->update([
            'global_commission_rate_percent' => 12.50,
        ]);

        $this->assertSame('12.50', $setting->fresh()->global_commission_rate_percent);
    }
}
