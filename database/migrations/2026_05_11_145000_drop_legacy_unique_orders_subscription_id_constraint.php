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
        $this->dropLegacyUniqueSubscriptionIdConstraint();
        $this->ensureNonUniqueSubscriptionIdIndex();
    }

    public function down(): void
    {
        // legacy unique constraint intentionally not restored
    }

    private function dropLegacyUniqueSubscriptionIdConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('orders')");

            foreach ($indexes as $index) {
                if ((int) ($index->unique ?? 0) !== 1) {
                    continue;
                }

                $indexName = (string) $index->name;
                $columns = DB::select("PRAGMA index_info('{$indexName}')");
                $columnNames = collect($columns)->pluck('name')->map(fn (mixed $name): string => (string) $name)->values()->all();

                if ($columnNames === ['subscription_id']) {
                    DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
                }
            }

            return;
        }

        if ($driver === 'pgsql') {
            $indexes = DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'orders'");

            foreach ($indexes as $index) {
                $name = (string) $index->indexname;
                $def = strtolower((string) $index->indexdef);

                if (! str_contains($def, 'unique index')) {
                    continue;
                }

                if (! str_contains($def, '(subscription_id)')) {
                    continue;
                }

                if (str_contains($def, ',')) {
                    continue;
                }

                DB::statement('DROP INDEX IF EXISTS "'.$name.'"');
            }

            return;
        }

        if ($driver === 'mysql') {
            $indexes = DB::select("SHOW INDEX FROM orders WHERE Non_unique = 0");
            $grouped = collect($indexes)->groupBy('Key_name');

            foreach ($grouped as $indexName => $rows) {
                $columns = $rows->sortBy('Seq_in_index')->pluck('Column_name')->map(fn (mixed $name): string => (string) $name)->values()->all();
                if ($columns === ['subscription_id']) {
                    DB::statement('ALTER TABLE orders DROP INDEX `'.$indexName.'`');
                }
            }

            return;
        }

        // Best-effort fallback for unknown drivers.
        try {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique('orders_subscription_id_unique');
            });
        } catch (\Throwable) {
            // ignore
        }
    }

    private function ensureNonUniqueSubscriptionIdIndex(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('orders')");
            $exists = collect($indexes)->contains(fn ($index): bool => (string) $index->name === 'orders_subscription_id_idx');

            if (! $exists) {
                DB::statement('CREATE INDEX orders_subscription_id_idx ON orders (subscription_id)');
            }

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS orders_subscription_id_idx ON orders (subscription_id)');

            return;
        }

        if ($driver === 'mysql') {
            $indexExists = DB::select("SHOW INDEX FROM orders WHERE Key_name = 'orders_subscription_id_idx'");
            if ($indexExists === []) {
                DB::statement('ALTER TABLE orders ADD INDEX orders_subscription_id_idx (subscription_id)');
            }

            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->index('subscription_id', 'orders_subscription_id_idx');
        });
    }
};
