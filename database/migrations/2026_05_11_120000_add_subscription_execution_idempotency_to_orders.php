<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const PENDING_GUARD_INDEX = 'orders_one_pending_subscription_execution_idx';

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
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            $this->assertNoDuplicatePendingSubscriptionOrders();

            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS ".self::PENDING_GUARD_INDEX." ON orders (subscription_id) WHERE origin = 'subscription' AND payment_status = 'pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS '.self::PENDING_GUARD_INDEX);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_subscription_slot_unique');

            if (Schema::hasColumn('orders', 'subscription_run_slot')) {
                $table->dropColumn('subscription_run_slot');
            }
        });
    }

    private function assertNoDuplicatePendingSubscriptionOrders(): void
    {
        $duplicates = DB::table('orders')
            ->select('subscription_id', DB::raw('COUNT(*) as duplicate_count'))
            ->where('origin', 'subscription')
            ->where('payment_status', 'pending')
            ->whereNotNull('subscription_id')
            ->groupBy('subscription_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('subscription_id')
            ->limit(25)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $summary = $duplicates
            ->map(fn ($row): string => sprintf('%d(x%d)', (int) $row->subscription_id, (int) $row->duplicate_count))
            ->implode(', ');

        throw new RuntimeException(
            'Cannot create pending-subscription unique guard index: duplicate pending subscription execution orders exist. '
            .'Resolve duplicates for subscription_id(s): '.$summary.'. '
            .'Run diagnostics from docs/subscription-execution-idempotency.md before retrying migration.'
        );
    }
};
