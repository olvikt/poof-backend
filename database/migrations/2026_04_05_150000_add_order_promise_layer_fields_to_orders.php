<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('service_mode', 32)->nullable()->after('service');
            $table->timestamp('window_from_at')->nullable()->after('service_mode');
            $table->timestamp('window_to_at')->nullable()->after('window_from_at');
            $table->timestamp('valid_until_at')->nullable()->after('window_to_at');
            $table->timestamp('expired_at')->nullable()->after('valid_until_at');
            $table->string('expired_reason', 128)->nullable()->after('expired_at');
            $table->string('client_wait_preference', 64)->nullable()->after('expired_reason');
            $table->string('promise_policy_version', 32)->nullable()->after('client_wait_preference');

            $table->index(['status', 'payment_status', 'courier_id', 'expired_at', 'valid_until_at'], 'orders_dispatch_validity_idx');
        });

        $now = now();
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
            ->chunkById(500, function ($orders) use ($preferredGraceHours): void {
                foreach ($orders as $order) {
                    $fromTime = $order->time_from ?? $order->scheduled_time_from;
                    $toTime = $order->time_to ?? $order->scheduled_time_to ?? $fromTime;

                    if (! $fromTime) {
                        continue;
                    }

                    $windowFrom = Carbon::parse(sprintf('%s %s', (string) $order->scheduled_date, (string) $fromTime));
                    $windowTo = Carbon::parse(sprintf('%s %s', (string) $order->scheduled_date, (string) $toTime));

                    if ($windowTo->lessThanOrEqualTo($windowFrom)) {
                        $windowTo = $windowFrom->copy()->addHours(2);
                    }

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'window_from_at' => $windowFrom,
                            'window_to_at' => $windowTo,
                            'valid_until_at' => $windowTo->copy()->addHours($preferredGraceHours),
                        ]);
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
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_dispatch_validity_idx');
            $table->dropColumn([
                'service_mode',
                'window_from_at',
                'window_to_at',
                'valid_until_at',
                'expired_at',
                'expired_reason',
                'client_wait_preference',
                'promise_policy_version',
            ]);
        });
    }
};
