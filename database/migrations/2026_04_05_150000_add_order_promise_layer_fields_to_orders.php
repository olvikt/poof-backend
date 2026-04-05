<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Orders\LegacyScheduleNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->addMissingSchema();

        $now = now();
        $legacyScheduleNormalizer = app(LegacyScheduleNormalizer::class);
        $defaultWaitPreference = (string) config('order_promise.default_wait_preference', 'auto_cancel_if_not_found');
        $policyVersion = (string) config('order_promise.policy_version', 'v1');
        $preferredGraceHours = max(0, (int) config('order_promise.preferred_window_grace_hours', 2));
        $asapHours = max(1, (int) config('order_promise.asap_validity_hours', 4));

        DB::table('orders')
            ->whereNull('service_mode')
            ->update([
                'service_mode' => 'preferred_window',
                'client_wait_preference' => $defaultWaitPreference,
                'promise_policy_version' => $policyVersion,
            ]);

        DB::table('orders')
            ->whereNull('window_from_at')
            ->whereNotNull('scheduled_date')
            ->where(function ($query): void {
                $query->whereNotNull('time_from')->orWhereNotNull('scheduled_time_from');
            })
            ->orderBy('id')
            ->chunkById(500, function ($orders) use ($legacyScheduleNormalizer, $preferredGraceHours): void {
                foreach ($orders as $order) {
                    try {
                        $fromTime = $order->time_from ?? $order->scheduled_time_from;
                        $toTime = $order->time_to ?? $order->scheduled_time_to ?? $fromTime;

                        [$windowFrom, $windowTo] = $legacyScheduleNormalizer->resolveWindowFromLegacy(
                            isset($order->scheduled_date) ? (string) $order->scheduled_date : null,
                            isset($fromTime) ? (string) $fromTime : null,
                            isset($toTime) ? (string) $toTime : null,
                            2,
                        );

                        if (! $windowFrom || ! $windowTo) {
                            continue;
                        }

                        DB::table('orders')
                            ->where('id', $order->id)
                            ->update([
                                'window_from_at' => $windowFrom,
                                'window_to_at' => $windowTo,
                                'valid_until_at' => $windowTo->copy()->addHours($preferredGraceHours),
                            ]);
                    } catch (\Throwable $exception) {
                        Log::warning('Order promise backfill skipped order due to malformed legacy schedule.', [
                            'order_id' => $order->id ?? null,
                            'scheduled_date' => $order->scheduled_date ?? null,
                            'time_from' => $order->time_from ?? $order->scheduled_time_from ?? null,
                            'time_to' => $order->time_to ?? $order->scheduled_time_to ?? null,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        DB::table('orders')
            ->whereNull('valid_until_at')
            ->whereIn('status', ['new', 'searching', 'accepted', 'in_progress'])
            ->update([
                'service_mode' => DB::raw("COALESCE(service_mode, 'asap')"),
                'valid_until_at' => $now->copy()->addHours($asapHours),
            ]);
    }

    public function down(): void
    {
        if ($this->hasIndex('orders', 'orders_dispatch_validity_idx')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex('orders_dispatch_validity_idx');
            });
        }

        $columnsToDrop = array_values(array_filter([
            'service_mode',
            'window_from_at',
            'window_to_at',
            'valid_until_at',
            'expired_at',
            'expired_reason',
            'client_wait_preference',
            'promise_policy_version',
        ], fn (string $column): bool => Schema::hasColumn('orders', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function addMissingSchema(): void
    {
        $this->addColumnIfMissing('service_mode', function (Blueprint $table): void {
            $table->string('service_mode', 32)->nullable()->after('service');
        });

        $this->addColumnIfMissing('window_from_at', function (Blueprint $table): void {
            $table->timestamp('window_from_at')->nullable()->after('service_mode');
        });

        $this->addColumnIfMissing('window_to_at', function (Blueprint $table): void {
            $table->timestamp('window_to_at')->nullable()->after('window_from_at');
        });

        $this->addColumnIfMissing('valid_until_at', function (Blueprint $table): void {
            $table->timestamp('valid_until_at')->nullable()->after('window_to_at');
        });

        $this->addColumnIfMissing('expired_at', function (Blueprint $table): void {
            $table->timestamp('expired_at')->nullable()->after('valid_until_at');
        });

        $this->addColumnIfMissing('expired_reason', function (Blueprint $table): void {
            $table->string('expired_reason', 128)->nullable()->after('expired_at');
        });

        $this->addColumnIfMissing('client_wait_preference', function (Blueprint $table): void {
            $table->string('client_wait_preference', 64)->nullable()->after('expired_reason');
        });

        $this->addColumnIfMissing('promise_policy_version', function (Blueprint $table): void {
            $table->string('promise_policy_version', 32)->nullable()->after('client_wait_preference');
        });

        if (! $this->hasIndex('orders', 'orders_dispatch_validity_idx')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index(['status', 'payment_status', 'courier_id', 'expired_at', 'valid_until_at'], 'orders_dispatch_validity_idx');
            });
        }
    }

    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if (Schema::hasColumn('orders', $column)) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $sqliteIndex) {
                if (($sqliteIndex->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $database = Schema::getConnection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$database, $table, $index]
            );

            return $row !== null;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ? LIMIT 1',
                [$table, $index]
            );

            return $row !== null;
        }

        if ($driver === 'sqlsrv') {
            $row = DB::selectOne(
                'SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
                [$table, $index]
            );

            return $row !== null;
        }

        return false;
    }
};
