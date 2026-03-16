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
        $inactiveCouriers = Courier::query()
            ->where('status', Courier::STATUS_ONLINE)
            ->where(function ($query) {
                $query->whereNull('last_location_at')
                    ->orWhere('last_location_at', '<=', now()->subSeconds(120));
            })
            ->with('user')
            ->get()
            ->filter(fn (Courier $courier) => $courier->user && ! $courier->user->isBusyForAccept());

        foreach ($inactiveCouriers as $courier) {
            $courier->user->goOffline();
        }
    }
}
