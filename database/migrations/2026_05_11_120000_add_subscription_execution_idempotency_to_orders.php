<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'subscription_run_slot')) {
                $table->dateTime('subscription_run_slot')->nullable()->after('subscription_id');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['subscription_id', 'subscription_run_slot'], 'orders_subscription_slot_unique');
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS orders_one_pending_subscription_execution_idx ON orders (subscription_id) WHERE origin = 'subscription' AND payment_status = 'pending'");
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS orders_one_pending_subscription_execution_idx ON orders (subscription_id) WHERE origin = 'subscription' AND payment_status = 'pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS orders_one_pending_subscription_execution_idx');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_subscription_slot_unique');

            if (Schema::hasColumn('orders', 'subscription_run_slot')) {
                $table->dropColumn('subscription_run_slot');
            }
        });
    }
};
