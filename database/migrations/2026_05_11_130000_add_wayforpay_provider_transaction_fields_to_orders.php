<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_provider_transaction_id')) {
                $table->string('payment_provider_transaction_id', 128)->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'payment_provider_reference')) {
                $table->string('payment_provider_reference', 128)->nullable()->after('payment_provider_transaction_id');
            }

            $table->unique(['id', 'payment_provider_transaction_id'], 'orders_id_provider_tx_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_id_provider_tx_unique');

            if (Schema::hasColumn('orders', 'payment_provider_reference')) {
                $table->dropColumn('payment_provider_reference');
            }

            if (Schema::hasColumn('orders', 'payment_provider_transaction_id')) {
                $table->dropColumn('payment_provider_transaction_id');
            }
        });
    }
};
