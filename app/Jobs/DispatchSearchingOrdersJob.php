<?php

namespace App\Jobs;

use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSearchingOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DispatchTriggerService $triggerService): void
    {
        $triggerService->triggerQueueBatch(
            DispatchTriggerPolicy::SOURCE_SCHEDULER,
            (int) config('dispatch.radius_km', 20),
        );
    }
}

