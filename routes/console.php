<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use App\Services\Dispatch\OfferDispatcher;
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

Artisan::command('orders:auto-expire {--limit=200}', function () {
    $limit = max(1, (int) $this->option('limit'));
    /** @var OrderAutoExpireService $service */
    $service = app(OrderAutoExpireService::class);
    $expired = $service->run($limit);

    $this->info(sprintf('Expired orders: %d', $expired));
})->purpose('Expire stale searching orders by order promise validity');

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
