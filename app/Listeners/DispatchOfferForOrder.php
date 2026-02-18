<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Support\Facades\Event;

class DispatchOfferForOrder
{
    /**
     * Реакция на создание заказа:
     * запускаем систему офферов и фиксируем сессию курьера
     */
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        // 🔒 Защита: заказ уже не в поиске
        if ($order->status !== Order::STATUS_SEARCHING) {
            return;
        }

        /** @var OfferDispatcher $dispatcher */
        $dispatcher = app(OfferDispatcher::class);

        $offer = $dispatcher->dispatchForOrder($order);

        // ❌ Никто не найден — выходим
        if (! $offer) {
            return;
        }

        // ✅ Зафиксировать состояние курьера
        $courier = $offer->courier;

        if ($courier) {
            $courier->forceFill([
                'session_state' => 'HAS_OFFER',
            ])->save();
        }

        // 📣 Сообщаем системе / UI
        event('courier.offer.created', [
            'courier_id' => $offer->courier_id,
            'offer_id'   => $offer->id,
        ]);
    }
}
