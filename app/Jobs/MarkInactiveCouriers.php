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
            ->where('status', '!=', Courier::STATUS_OFFLINE)
            ->where(function ($query) {
                $query->whereNull('last_location_at')
                    ->orWhere('last_location_at', '<=', now()->subSeconds(120));
            })
            ->whereHas('user', function ($userQuery) {
                $userQuery->where('is_busy', false)
                    ->whereDoesntHave('takenOrders', function ($orderQuery) {
                        $orderQuery->activeForCourier();
                    });
            })
            ->update([
                'status' => Courier::STATUS_OFFLINE,
            ]);
    }
}
