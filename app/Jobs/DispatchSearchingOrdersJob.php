<?php

namespace App\Jobs;

use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSearchingOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OfferDispatcher $dispatcher): void
    {
        $dispatcher->dispatchSearchingOrders(20);
    }
}

