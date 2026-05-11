<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use App\Services\Dispatch\OfferDispatcher;
use App\Services\Dispatch\PendingOfferSweeper;
use App\Services\Dispatch\DispatchDiagnosticsService;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Orders\OrderAutoExpireService;
use App\Jobs\MarkInactiveCouriers;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduler (Laravel 11 / 12)
|--------------------------------------------------------------------------
| POOF — Offer Dispatch Loop
|--------------------------------------------------------------------------
| Заказ крутится, пока:
| - status = searching
| - courier_id = null
| - нет живого pending
|
| Scheduler работает каждые 5 секунд.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Demo command (не влияет на dispatch)
|--------------------------------------------------------------------------
*/
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| 🔥 POOF — Offer Dispatch Loop (CORE ENGINE)
|--------------------------------------------------------------------------
|
| Поведение как в Uber / Bolt:
| - заказ в searching
| - нет живого pending
| - создаём новый offer
| - повторяем бесконечно
|
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    /** @var OfferDispatcher $dispatcher */
    $dispatcher = app(OfferDispatcher::class);

    $dispatcher->dispatchSearchingOrders(20);

    Cache::put(
        config('ops.scheduler_heartbeat_cache_key', 'ops:scheduler:last-tick-at'),
        now()->toIso8601String(),
        now()->addHours(6)
    );

})
->name('poof-dispatch-loop')
->description('POOF offer dispatch engine')
->everyFiveSeconds();


Schedule::job(new MarkInactiveCouriers())
    ->name('poof-couriers-mark-inactive')
    ->description('Mark couriers offline when location TTL is expired')
    ->everyMinute();

Schedule::call(function (): void {
    /** @var OrderAutoExpireService $service */
    $service = app(OrderAutoExpireService::class);
    $service->run(200);
})
->name('poof-order-auto-expire-loop')
->description('POOF order promise auto-expire engine')
->everyMinute();

Schedule::call(function (): void {
    /** @var PendingOfferSweeper $service */

    $service = app(PendingOfferSweeper::class);
    $service->run((int) config('courier_runtime.pending_offer_sweeper.limit', 200));
})
->name('poof-pending-offer-ttl-sweeper')
->description('Expire pending order offers by TTL in bounded batches')
->everyMinute();


Schedule::command('orders:auto-complete-awaiting-confirmation --limit=100')
    ->name('poof-completion-proof-auto-confirm')
    ->description('Auto-confirm due proof completion requests')
    ->everyMinute();

Schedule::command('subscriptions:generate-execution-orders --limit=100')
    ->name('poof-subscriptions-generate-execution-orders')
    ->description('Generate due recurring subscription execution orders')
    ->everyMinute();
Artisan::command('orders:auto-expire {--limit=200}', function () {
    $limit = max(1, (int) $this->option('limit'));
    /** @var OrderAutoExpireService $service */
    $service = app(OrderAutoExpireService::class);
    $expired = $service->run($limit);

    $this->info(sprintf('Expired orders: %d', $expired));
})->purpose('Expire stale searching orders by order promise validity');

Artisan::command('courier:sweep-pending-offers {--limit=200}', function () {
    $limit = max(1, (int) $this->option('limit'));
    /** @var PendingOfferSweeper $service */
    $service = app(PendingOfferSweeper::class);
    $expired = $service->run($limit);

    $this->line(json_encode([
        'expired_count' => $expired,
        'batch_limit' => $limit,
    ], JSON_UNESCAPED_SLASHES));
})->purpose('Expire stale pending order offers by TTL');

Artisan::command('courier:diagnose-searching-orders {--limit=100}', function () {
    $limit = max(1, (int) $this->option('limit'));
    /** @var DispatchDiagnosticsService $service */
    $service = app(DispatchDiagnosticsService::class);
    $result = $service->findStuckSearchingOrders($limit);

    $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
})->purpose('Detect anomalous searching orders for operator diagnostics');

Artisan::command('courier:why-order-not-dispatched {orderId}', function (int $orderId) {
    /** @var Order|null $order */
    $order = Order::query()->find($orderId);
    if (! $order) {
        $this->error("Order {$orderId} not found.");
        return self::FAILURE;
    }

    /** @var DispatchDiagnosticsService $service */
    $service = app(DispatchDiagnosticsService::class);
    $diagnostics = $service->diagnoseOrder($order);

    $this->line(json_encode($diagnostics, JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
})->purpose('Operator diagnostic: explain why order is not dispatched');


Artisan::command('poof:diagnose-dispatch {order_id}', function (int $order_id) {
    /** @var Order|null $order */
    $order = Order::query()->find($order_id);
    if (! $order) {
        $this->error("Order {$order_id} not found.");
        return self::FAILURE;
    }

    /** @var DispatchDiagnosticsService $service */
    $service = app(DispatchDiagnosticsService::class);
    $diagnostics = $service->diagnoseOrder($order);

    $this->line(json_encode($diagnostics, JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
})->purpose('Operator diagnostic: dispatch gating and candidate breakdown by order_id');

Artisan::command('courier:why-courier-not-candidate {orderId} {courierId}', function (int $orderId, int $courierId) {
    /** @var Order|null $order */
    $order = Order::query()->find($orderId);
    /** @var User|null $courier */
    $courier = User::query()->find($courierId);

    if (! $order || ! $courier) {
        $this->error('Order or courier not found.');
        return self::FAILURE;
    }

    /** @var DispatchDiagnosticsService $service */
    $service = app(DispatchDiagnosticsService::class);
    $diagnostics = $service->diagnoseCourierForOrder($order, $courier);

    $this->line(json_encode($diagnostics, JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
})->purpose('Operator diagnostic: explain why courier is not a dispatch candidate');

Artisan::command('poof:diagnose-courier-dispatch {--date=} {--courier-id=}', function () {
    $date = (string) ($this->option('date') ?: now()->toDateString());
    $courierId = (int) ($this->option('courier-id') ?: 0);
    $now = now();

    $orders = Order::query()
        ->whereDate('scheduled_date', $date)
        ->orderBy('id')
        ->get();

    $rows = $orders->map(function (Order $order) use ($courierId, $now): array {
        $alivePendingOffersCount = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->count();

        $hasOfferForCourier = $courierId > 0
            ? OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('courier_id', $courierId)
                ->exists()
            : false;

        $hasAlivePendingOfferForCourier = $courierId > 0
            ? OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('courier_id', $courierId)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', $now)
                ->exists()
            : false;

        $isDispatchDeferred = $order->isDispatchDeferred();
        $isPromiseExpired = $order->isPromiseExpired();
        $isDispatchable = $order->isDispatchableForOfferPipeline();

        $reasons = [];

        if ($order->status !== Order::STATUS_SEARCHING) {
            $reasons[] = 'not_dispatchable_status';
        }
        if ($order->payment_status !== Order::PAY_PAID) {
            $reasons[] = 'not_paid';
        }
        if ($order->courier_id !== null) {
            $reasons[] = 'courier_already_assigned';
        }
        if ($order->next_dispatch_at !== null && $order->next_dispatch_at->isFuture()) {
            $reasons[] = 'next_dispatch_backoff_until_future';
        }
        if ($isDispatchDeferred) {
            $reasons[] = 'dispatch_deferred_future_window';
        }
        if ($isPromiseExpired || $order->expired_at !== null) {
            $reasons[] = 'order_promise_expired';
        }
        if ($alivePendingOffersCount > 0) {
            $reasons[] = 'no_offer_for_courier';
        }
        if ($isDispatchable && $alivePendingOffersCount === 0) {
            $reasons[] = 'no_alive_pending_offer';
        }
        if ($reasons === []) {
            $reasons[] = 'other';
        }

        return [
            'id' => $order->id,
            'order_type' => $order->order_type,
            'origin' => $order->origin,
            'subscription_id' => $order->subscription_id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'courier_id' => $order->courier_id,
            'dispatch_available_at' => $order->dispatch_available_at?->toIso8601String(),
            'next_dispatch_at' => $order->next_dispatch_at?->toIso8601String(),
            'valid_until_at' => $order->valid_until_at?->toIso8601String(),
            'expired_at' => $order->expired_at?->toIso8601String(),
            'is_dispatch_deferred' => $isDispatchDeferred,
            'is_promise_expired' => $isPromiseExpired,
            'is_dispatchable_for_offer_pipeline' => $isDispatchable,
            'alive_pending_offers_count' => $alivePendingOffersCount,
            'has_offer_for_courier_id' => $hasOfferForCourier,
            'has_alive_pending_offer_for_courier_id' => $hasAlivePendingOfferForCourier,
            'dispatch_window_opens_at' => $isDispatchDeferred ? $order->dispatch_available_at?->toIso8601String() : null,
            'reasons' => $reasons,
        ];
    })->values()->all();

    $categorizedCounts = [
        'dispatchable' => 0,
        'deferred' => 0,
        'stuck_without_offers' => 0,
    ];
    foreach ($rows as $row) {
        if (($row['is_dispatchable_for_offer_pipeline'] ?? false) === true) {
            $categorizedCounts['dispatchable']++;
        }
        if (in_array('dispatch_deferred_future_window', (array) ($row['reasons'] ?? []), true)) {
            $categorizedCounts['deferred']++;
        }
        if (in_array('no_alive_pending_offer', (array) ($row['reasons'] ?? []), true)) {
            $categorizedCounts['stuck_without_offers']++;
        }
    }

    $failedJobsCount = DB::table('failed_jobs')->count();
    $delayedJobsCount = DB::table('jobs')->where('available_at', '>', now()->timestamp)->count();
    $dispatcherJobsCount = DB::table('jobs')->where('payload', 'like', '%DispatchOfferForOrder%')->count();

    $driver = DB::connection()->getDriverName();
    $dbNowQuery = match ($driver) {
        'sqlite' => "select datetime('now') as now",
        'pgsql' => 'select now() as now',
        default => 'select now() as now',
    };

    $dbNow = null;

    try {
        $dbNow = DB::selectOne($dbNowQuery)?->now;
    } catch (\Throwable) {
        $dbNow = null;
    }

    $this->line(json_encode([
        'date' => $date,
        'courier_id' => $courierId > 0 ? $courierId : null,
        'now' => $now->toIso8601String(),
        'orders_count' => count($rows),
        'summary' => $categorizedCounts,
        'timezone' => [
            'app_timezone' => config('app.timezone'),
            'php_timezone' => date_default_timezone_get(),
            'db_now' => $dbNow,
            'carbon_now' => now()->toIso8601String(),
        ],
        'queue' => [
            'failed_jobs' => $failedJobsCount,
            'delayed_jobs' => $delayedJobsCount,
            'dispatcher_jobs' => $dispatcherJobsCount,
        ],
        'orders' => $rows,
    ], JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
})->purpose('Diagnose why orders are not visible to courier by date');

Artisan::command('ops:contract:scheduler {--max-age-seconds=120}', function () {
    $key = config('ops.scheduler_heartbeat_cache_key', 'ops:scheduler:last-tick-at');
    $maxAgeSeconds = max(1, (int) $this->option('max-age-seconds'));
    $lastTickAt = (string) Cache::get($key, '');
    $ageSeconds = null;

    if ($lastTickAt !== '') {
        try {
            $ageSeconds = \Illuminate\Support\Carbon::parse($lastTickAt)->floatDiffInSeconds(now(), false);
        } catch (\Throwable) {
            $ageSeconds = null;
        }
    }

    $ok = is_numeric($ageSeconds) && $ageSeconds >= 0 && $ageSeconds <= $maxAgeSeconds;

    $this->line(json_encode([
        'status' => $ok ? 'ok' : 'stale',
        'key' => $key,
        'last_tick_at' => $lastTickAt !== '' ? $lastTickAt : null,
        'age_seconds' => $ageSeconds,
        'max_age_seconds' => $maxAgeSeconds,
    ], JSON_UNESCAPED_SLASHES));

    return $ok ? self::SUCCESS : self::FAILURE;
})->purpose('Machine-friendly scheduler heartbeat contract for operators');

Artisan::command('ops:contract:workers {--program-prefix=poof-worker:} {--supervisorctl=supervisorctl}', function () {
    $supervisorctl = (string) $this->option('supervisorctl');
    $programPrefix = (string) $this->option('program-prefix');
    $process = new Process([$supervisorctl, 'status']);
    $process->setTimeout(5);

    try {
        $process->run();
    } catch (\Throwable) {
        $this->line(json_encode([
            'status' => 'degraded',
            'reason' => 'supervisorctl_unavailable',
            'program_prefix' => $programPrefix,
        ], JSON_UNESCAPED_SLASHES));

        return 2;
    }

    if (! $process->isSuccessful()) {
        $this->line(json_encode([
            'status' => 'degraded',
            'reason' => 'supervisorctl_status_failed',
            'program_prefix' => $programPrefix,
            'exit_code' => $process->getExitCode(),
        ], JSON_UNESCAPED_SLASHES));

        return 2;
    }

    $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];
    $workers = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || ! str_starts_with($line, $programPrefix)) {
            continue;
        }

        if (preg_match('/^(?<name>\S+)\s+(?<state>[A-Z_]+)/', $line, $matches) === 1) {
            $workers[] = [
                'name' => $matches['name'],
                'state' => $matches['state'],
            ];
        }
    }

    if ($workers === []) {
        $this->line(json_encode([
            'status' => 'fail',
            'reason' => 'no_matching_workers',
            'program_prefix' => $programPrefix,
        ], JSON_UNESCAPED_SLASHES));

        return self::FAILURE;
    }

    $failingWorkers = array_values(array_filter($workers, static fn (array $worker): bool => $worker['state'] !== 'RUNNING'));
    $ok = $failingWorkers === [];

    $this->line(json_encode([
        'status' => $ok ? 'ok' : 'fail',
        'program_prefix' => $programPrefix,
        'total_workers' => count($workers),
        'running_workers' => count($workers) - count($failingWorkers),
        'failing_workers' => $failingWorkers,
    ], JSON_UNESCAPED_SLASHES));

    return $ok ? self::SUCCESS : self::FAILURE;
})->purpose('Machine-friendly supervisor worker contract for operators');
