<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_earning_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('global_commission_rate_percent', 5, 2)->default(20.00);
            $table->timestamps();

            $table->check('global_commission_rate_percent >= 0 and global_commission_rate_percent <= 100');
        });

        Schema::create('courier_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('gross_amount');
            $table->decimal('commission_rate_percent', 5, 2);
            $table->unsignedInteger('commission_amount');
            $table->unsignedInteger('net_amount');
            $table->unsignedInteger('bonuses_amount')->default(0);
            $table->unsignedInteger('penalties_amount')->default(0);
            $table->integer('adjustments_amount')->default(0);
            $table->string('earning_status', 32)->default('settled');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['courier_id', 'earning_status']);
        });

        DB::table('courier_earning_settings')->insert([
            'global_commission_rate_percent' => 20.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_earnings');
        Schema::dropIfExists('courier_earning_settings');
    }
};
