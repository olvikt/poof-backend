<?php

namespace App\Jobs;

use App\Models\Courier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkInactiveCouriers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Courier::query()
            ->with('user')
            ->chunkById(200, function ($couriers): void {
                foreach ($couriers as $courier) {
                    $user = $courier->user;

                    if (! $user) {
                        continue;
                    }

                    // Self-heal: сначала всегда выравниваем runtime/business состояние.
                    $user->repairCourierRuntimeState();

                    $courier->refresh();

                    $isStaleOnline = $courier->status === Courier::STATUS_ONLINE
                        && (
                            $courier->last_location_at === null
                            || $courier->last_location_at->lte(now()->subSeconds(120))
                        );

                    if (! $isStaleOnline || $user->hasActiveCourierOrder()) {
                        continue;
                    }

                    $user->goOffline();
                }
            });
    }
}
