<?php

namespace Tests\Unit\Subscriptions;

use App\Models\BagPricing;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanEconomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_economy_formula_uses_plan_max_bags_retail_price(): void
    {
        BagPricing::query()->where('bags_count', 1)->update(['price' => 42]);
        BagPricing::query()->where('bags_count', 3)->update(['price' => 70]);

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

        $this->assertSame(70, $plan->referenceSinglePickupPriceForPlan());
        $this->assertSame(700, $plan->referenceMonthlyTotal());
        $this->assertSame(300, $plan->economyAmount());
        $this->assertSame(43, $plan->economyPercent());
    }

    public function test_seeded_plans_economy_percent_matches_max_bags_baseline(): void
    {
        $plans = SubscriptionPlan::query()->get()->keyBy('slug');

        $this->assertSame(43, $plans['every-3-days']->economyPercent());
        $this->assertSame(44, $plans['every-2-days']->economyPercent());
        $this->assertSame(46, $plans['daily']->economyPercent());
    }
}
