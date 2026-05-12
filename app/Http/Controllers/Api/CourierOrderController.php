<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\Lifecycle\AcceptOrderByCourierAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourierAvailableOfferResource;
use App\Models\CourierOrderInterest;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\Courier\CourierPresenceService;
use Illuminate\Http\JsonResponse;

class CourierOrderController extends Controller
{
    /**
     * Курьер видит список доступных заказов
     */
    public function available()
    {
        $courier = auth()->user();

        abort_if(! $courier || ! $courier->isCourier(), 403);

        $runtime = app(CourierPresenceService::class)->snapshot($courier) ?? [];
        $hasActiveOrder = (bool) ($runtime['has_active_order'] ?? false);

        $defaultLimit = 20;
        $maxLimit = 50;
        $rawLimit = request()->query('limit');
        $parsedLimit = is_numeric($rawLimit) ? (int) $rawLimit : $defaultLimit;
        $limit = max(1, min($parsedLimit, $maxLimit));

        $offers = $hasActiveOrder
            ? collect()
            : OrderOffer::query()
                ->alivePendingForCourierOrders((int) $courier->id)
                ->limit($limit)
                ->get();

        return response()->json([
            'orders' => CourierAvailableOfferResource::collection($offers)->resolve(),
            'pagination' => [
                'limit' => $limit,
                'max_limit' => $maxLimit,
                'count' => $offers->count(),
            ],
        ]);
    }

    /**
     * Курьер принимает заказ по offer_id (canonical public contract).
     */
    public function acceptOffer(OrderOffer $offer): JsonResponse
    {
        $courier = auth()->user();

        abort_if(! $courier || ! $courier->isCourier(), 403);

        $offer = OrderOffer::query()
            ->whereKey($offer->getKey())
            ->where('courier_id', (int) $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereHas('order', function ($query): void {
                $query->where('status', Order::STATUS_SEARCHING)
                    ->where('payment_status', Order::PAY_PAID);
            })
            ->first();

        if (! $offer || ! app(AcceptOrderByCourierAction::class)->handle($offer->order, $courier)) {
            return response()->json([
                'success' => false,
                'message' => 'Неможливо прийняти замовлення',
            ], 409);
        }

        OrderOffer::query()
            ->whereKey($offer->getKey())
            ->where('status', OrderOffer::STATUS_PENDING)
            ->update(['status' => OrderOffer::STATUS_ACCEPTED]);

        return response()->json([
            'success' => true,
            'order' => $offer->order->fresh(),
        ]);
    }

    /**
     * Курьер принимает заказ
     */
    public function accept(Order $order)
    {
        $courier = auth()->user();

        abort_if(! $courier || ! $courier->isCourier(), 403);

        if (! app(AcceptOrderByCourierAction::class)->handle($order, $courier)) {
            return response()->json([
                'success' => false,
                'message' => 'Неможливо прийняти замовлення',
            ], 409);
        }

        return response()->json([
            'success' => true,
            'order' => $order->fresh(),
        ]);
    }

    public function expressInterest(Order $order): JsonResponse
    {
        $courier = auth()->user();
        abort_if(! $courier || ! $courier->isCourier(), 403);

        if ($order->status !== Order::STATUS_SEARCHING || $order->payment_status !== Order::PAY_PAID || $order->courier_id !== null) {
            return response()->json(['success' => false, 'message' => 'Order not eligible for interest'], 422);
        }

        $interest = CourierOrderInterest::query()->firstOrCreate(
            ['order_id' => $order->id, 'courier_id' => $courier->id],
            [
                'status' => CourierOrderInterest::STATUS_INTERESTED,
                'expressed_at' => now(),
                'courier_lat' => $courier->last_lat,
                'courier_lng' => $courier->last_lng,
            ]
        );

        return response()->json(['success' => true, 'interest' => $interest]);
    }

    public function withdrawInterest(Order $order): JsonResponse
    {
        $courier = auth()->user();
        abort_if(! $courier || ! $courier->isCourier(), 403);

        CourierOrderInterest::query()
            ->where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->update([
                'status' => CourierOrderInterest::STATUS_WITHDRAWN,
                'rejected_reason' => 'withdrawn_by_courier',
            ]);

        return response()->json(['success' => true]);
    }
}
