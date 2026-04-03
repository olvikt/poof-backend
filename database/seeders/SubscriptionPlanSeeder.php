<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::query()->upsert([
            [
                'name' => '1 раз в 3 дні',
                'slug' => 'every-3-days',
                'frequency_type' => 'every_3_days',
                'pickups_per_month' => 10,
                'monthly_price' => 400,
                'max_bags' => 3,
                'max_weight_kg' => 18,
                'description' => 'Регулярний винос для стабільного ритму.',
                'is_active' => true,
                'sort_order' => 10,
                'updated_at' => now(),
                'created_at' => now(),
            ],
            [
                'name' => '1 раз в 2 дні',
                'slug' => 'every-2-days',
                'frequency_type' => 'every_2_days',
                'pickups_per_month' => 15,
                'monthly_price' => 585,
                'max_bags' => 3,
                'max_weight_kg' => 18,
                'description' => 'Комфортний регулярний винос майже через день.',
                'is_active' => true,
                'sort_order' => 20,
                'updated_at' => now(),
                'created_at' => now(),
            ],
            [
                'name' => 'Щодня',
                'slug' => 'daily',
                'frequency_type' => 'daily',
                'pickups_per_month' => 30,
                'monthly_price' => 1140,
                'max_bags' => 3,
                'max_weight_kg' => 18,
                'description' => 'Максимальний комфорт для щоденного виносу.',
                'is_active' => true,
                'sort_order' => 30,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        ], ['slug'], [
            'name', 'frequency_type', 'pickups_per_month', 'monthly_price', 'max_bags', 'max_weight_kg', 'description', 'is_active', 'sort_order', 'updated_at',
        ]);
    }
}
