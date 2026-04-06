<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;

class DispatchOfferForOrder
{
    /**
     * Реакция на создание заказа:
     * запускаем систему офферов и фиксируем сессию курьера
     */
    public function handle(OrderCreated $event): void
    {
        $order = $event->order->fresh();

        if (! $order || ! $order->isDispatchableForOfferPipeline()) {
            return;
        }

        /** @var DispatchTriggerService $triggerService */
        $triggerService = app(DispatchTriggerService::class);

        $offer = $triggerService->triggerForOrder($order, DispatchTriggerPolicy::SOURCE_ORDER_CREATED);

        // ❌ Никто не найден — выходим
        if (! $offer) {
            return;
        }

        // 📣 Сообщаем системе / UI
        event('courier.offer.created', [
            'courier_id' => $offer->courier_id,
            'offer_id'   => $offer->id,
        ]);
    }
}
