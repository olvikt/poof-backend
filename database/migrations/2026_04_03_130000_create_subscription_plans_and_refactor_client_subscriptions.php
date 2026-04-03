<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('frequency_type', 32);
            $table->unsignedSmallInteger('pickups_per_month');
            $table->unsignedInteger('monthly_price');
            $table->unsignedTinyInteger('max_bags')->default(3);
            $table->unsignedSmallInteger('max_weight_kg')->default(18);
            $table->text('description')->nullable();
            $table->string('ui_badge', 64)->nullable();
            $table->string('ui_subtitle', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('client_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_subscriptions', 'subscription_plan_id')) {
                $table->foreignId('subscription_plan_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('subscription_plans')
                    ->nullOnDelete();
            }
        });

        foreach (['frequency', 'bags_count', 'price_per_pickup', 'base_price_per_pickup', 'discount_percent'] as $legacyColumn) {
            if (Schema::hasColumn('client_subscriptions', $legacyColumn)) {
                Schema::table('client_subscriptions', function (Blueprint $table) use ($legacyColumn): void {
                    $table->dropColumn($legacyColumn);
                });
            }
        }

        DB::table('subscription_plans')->upsert([
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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
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
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['slug'], [
            'name', 'frequency_type', 'pickups_per_month', 'monthly_price', 'max_bags', 'max_weight_kg', 'description', 'is_active', 'sort_order', 'updated_at',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('client_subscriptions', 'frequency')) {
            Schema::table('client_subscriptions', function (Blueprint $table): void {
                $table->string('frequency', 32)->default('daily');
                $table->unsignedTinyInteger('bags_count')->default(1);
                $table->unsignedInteger('price_per_pickup')->default(0);
                $table->unsignedInteger('base_price_per_pickup')->default(0);
                $table->unsignedInteger('discount_percent')->default(0);
            });
        }

        Schema::table('client_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('client_subscriptions', 'subscription_plan_id')) {
                $table->dropConstrainedForeignId('subscription_plan_id');
            }
        });

        Schema::dropIfExists('subscription_plans');
    }
};
