<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_offers', function (Blueprint $table): void {
            $table->index(['courier_id', 'status', 'expires_at'], 'order_offers_courier_status_expires_idx');
            $table->index(['order_id', 'status', 'expires_at'], 'order_offers_order_status_expires_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['courier_id', 'status', 'accepted_at'], 'orders_courier_status_accepted_idx');
            $table->index(['status', 'courier_id', 'payment_status'], 'orders_status_courier_payment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_offers', function (Blueprint $table): void {
            $table->dropIndex('order_offers_courier_status_expires_idx');
            $table->dropIndex('order_offers_order_status_expires_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_courier_status_accepted_idx');
            $table->dropIndex('orders_status_courier_payment_idx');
        });
    }
};
