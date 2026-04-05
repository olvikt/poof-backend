<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('dispatch_attempts')->default(0)->after('completed_at');
            $table->timestamp('last_dispatch_attempt_at')->nullable()->after('dispatch_attempts');
            $table->timestamp('next_dispatch_at')->nullable()->after('last_dispatch_attempt_at');

            $table->index(
                ['status', 'courier_id', 'payment_status', 'next_dispatch_at', 'id'],
                'orders_dispatch_queue_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_dispatch_queue_idx');
            $table->dropColumn([
                'dispatch_attempts',
                'last_dispatch_attempt_at',
                'next_dispatch_at',
            ]);
        });
    }
};
