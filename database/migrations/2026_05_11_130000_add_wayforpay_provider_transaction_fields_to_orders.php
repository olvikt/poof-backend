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
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_provider_transaction_id')) {
                $table->string('payment_provider_transaction_id', 128)->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'payment_provider_reference')) {
                $table->string('payment_provider_reference', 128)->nullable()->after('payment_provider_transaction_id');
            }

        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX orders_provider_tx_unique_not_null ON orders (payment_provider_transaction_id) WHERE payment_provider_transaction_id IS NOT NULL');
        } else {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('payment_provider_transaction_id', 'orders_provider_tx_unique_not_null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['pgsql', 'sqlite'], true)) {
                DB::statement('DROP INDEX IF EXISTS orders_provider_tx_unique_not_null');
            } else {
                $table->dropUnique('orders_provider_tx_unique_not_null');
            }

            if (Schema::hasColumn('orders', 'payment_provider_reference')) {
                $table->dropColumn('payment_provider_reference');
            }

            if (Schema::hasColumn('orders', 'payment_provider_transaction_id')) {
                $table->dropColumn('payment_provider_transaction_id');
            }
        });
    }
};
