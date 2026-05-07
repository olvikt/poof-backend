<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\OrderOffer;
use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;
use Illuminate\Support\Facades\Log;

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
            if ($order && $order->isDispatchDeferred()) {
                Log::info('execution_order_dispatch_deferred', [
                    'order_id' => (int) $order->id,
                    'subscription_id' => $order->subscription_id !== null ? (int) $order->subscription_id : null,
                    'dispatch_available_at' => $order->dispatch_available_at?->toIso8601String(),
                ]);
            }

            return;
        }

        $hasPendingOffer = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->exists();

        if ($hasPendingOffer) {
            Log::debug('dispatch_skipped', [
                'order_id' => (int) $order->id,
                'subscription_id' => $order->subscription_id !== null ? (int) $order->subscription_id : null,
                'status' => (string) $order->status,
                'reason' => 'already_dispatched_pending_offer_exists',
                'trigger_source' => DispatchTriggerPolicy::SOURCE_ORDER_CREATED,
            ]);

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
