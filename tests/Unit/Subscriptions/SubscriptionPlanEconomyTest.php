<?php

namespace Tests\Unit\Subscriptions;

use App\Models\BagPricing;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanEconomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_economy_formula_uses_current_single_bag_retail_price(): void
    {
        BagPricing::query()->where('bags_count', 1)->update(['price' => 42]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Formula plan',
            'slug' => 'formula-plan',
            'frequency_type' => 'every_3_days',
            'pickups_per_month' => 10,
            'monthly_price' => 400,
            'max_bags' => 3,
            'max_weight_kg' => 18,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame(42, $plan->referenceSinglePickupPrice());
        $this->assertSame(420, $plan->referenceMonthlyTotal());
        $this->assertSame(20, $plan->economyAmount());
        $this->assertSame(5, $plan->economyPercent());
    }
}
