<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\Lifecycle\AcceptOrderByCourierAction;
use App\Http\Controllers\Controller;
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

        $orders = $hasActiveOrder
            ? collect()
            : OrderOffer::query()
                ->alivePendingForCourierOrders((int) $courier->id)
                ->get();

        return response()->json([
            'orders' => $orders,
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
