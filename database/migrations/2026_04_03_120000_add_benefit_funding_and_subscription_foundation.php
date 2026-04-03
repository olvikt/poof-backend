<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'client_charge_amount')) {
                $table->unsignedInteger('client_charge_amount')->default(0)->after('price');
            }

            if (! Schema::hasColumn('orders', 'courier_payout_amount')) {
                $table->unsignedInteger('courier_payout_amount')->default(0)->after('client_charge_amount');
            }

            if (! Schema::hasColumn('orders', 'system_subsidy_amount')) {
                $table->unsignedInteger('system_subsidy_amount')->default(0)->after('courier_payout_amount');
            }

            if (! Schema::hasColumn('orders', 'funding_source')) {
                $table->string('funding_source', 32)->default('client')->after('system_subsidy_amount');
            }

            if (! Schema::hasColumn('orders', 'benefit_type')) {
                $table->string('benefit_type', 64)->nullable()->after('funding_source');
            }

            if (! Schema::hasColumn('orders', 'origin')) {
                $table->string('origin', 32)->default('checkout')->after('benefit_type');
            }
        });

        Schema::create('client_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->string('frequency', 32); // daily|every_3_days
            $table->unsignedTinyInteger('bags_count')->default(1);
            $table->unsignedInteger('price_per_pickup');
            $table->unsignedInteger('base_price_per_pickup');
            $table->unsignedInteger('discount_percent')->default(0);
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->string('status', 32)->default('draft'); // draft|active|paused|cancelled
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'subscription_id')) {
                $table->foreignId('subscription_id')
                    ->nullable()
                    ->after('origin')
                    ->constrained('client_subscriptions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'subscription_id')) {
                $table->dropConstrainedForeignId('subscription_id');
            }

            $columns = [
                'client_charge_amount',
                'courier_payout_amount',
                'system_subsidy_amount',
                'funding_source',
                'benefit_type',
                'origin',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('client_subscriptions');
    }
};
