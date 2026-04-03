<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_subscription_plan_records_and_non_admin_cannot_access_resource(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $this->actingAs($client);
        $this->assertFalse(SubscriptionPlanResource::canViewAny());

        $this->actingAs($admin);
        $this->assertTrue(SubscriptionPlanResource::canViewAny());
        $this->assertTrue(SubscriptionPlanResource::canCreate());

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Тест план',
            'slug' => 'test-plan',
            'frequency_type' => 'every_5_days',
            'pickups_per_month' => 6,
            'monthly_price' => 300,
            'max_bags' => 3,
            'max_weight_kg' => 18,
            'description' => 'Тестовий план',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $plan->update([
            'monthly_price' => 280,
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $plan->refresh();

        $this->assertSame(280, $plan->monthly_price);
        $this->assertFalse($plan->is_active);
        $this->assertSame(5, $plan->sort_order);
    }
}
