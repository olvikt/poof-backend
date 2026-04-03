<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => 'План '.$this->faker->unique()->word(),
            'slug' => $this->faker->unique()->slug(),
            'frequency_type' => 'every_3_days',
            'pickups_per_month' => 10,
            'monthly_price' => 400,
            'max_bags' => 3,
            'max_weight_kg' => 18,
            'description' => 'Тестовий опис',
            'is_active' => true,
            'sort_order' => 10,
        ];
    }
}
