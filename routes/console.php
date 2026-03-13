<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Dispatch\OfferDispatcher;
use App\Jobs\MarkInactiveCouriers;

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

})
->name('poof-dispatch-loop')
->description('POOF offer dispatch engine')
->everyFiveSeconds();


Schedule::job(new MarkInactiveCouriers())
    ->name('poof-couriers-mark-inactive')
    ->description('Mark couriers offline when location TTL is expired')
    ->everyMinute();

