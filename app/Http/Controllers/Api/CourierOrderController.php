<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CourierOrderController extends Controller
{
    /**
     * Курьер видит список доступных заказов
     */
    public function available()
    {
        $courier = auth()->user();

        abort_if(! $courier || ! $courier->isCourier(), 403);

        $orders = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->whereNull('courier_id')
            ->orderBy('created_at')
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

        return DB::transaction(function () use ($order, $courier) {

            // 🔒 защита от гонок
            $order->refresh();

            if ($order->status !== Order::STATUS_SEARCHING || $order->courier_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Замовлення вже взято',
                ], 409);
            }

            $order->update([
                'status'     => Order::STATUS_ACCEPTED,
                'courier_id' => $courier->id,
            ]);

            $courier->update(['is_busy' => true]);
            $courier->courierProfile()->update(['status' => Courier::STATUS_ASSIGNED]);

            return response()->json([
                'success' => true,
                'order'   => $order,
            ]);
        });
    }
}
