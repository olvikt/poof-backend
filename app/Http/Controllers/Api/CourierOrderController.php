<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\Lifecycle\AcceptOrderByCourierAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourierAvailableOfferResource;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\Courier\CourierPresenceService;

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
}
