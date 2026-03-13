<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminMapController extends Controller
{
    public function index(): JsonResponse
    {
        $couriers = User::query()
            ->where('role', User::ROLE_COURIER)
            ->where('is_online', 1)
            ->whereNotNull('last_lat')
            ->whereNotNull('last_lng')
            ->get([
                'id',
                'name',
                'last_lat',
                'last_lng',
            ])
            ->map(fn (User $courier) => [
                'id' => $courier->id,
                'name' => $courier->name,
                'lat' => (float) $courier->last_lat,
                'lng' => (float) $courier->last_lng,
                'vehicle_type' => null,
            ])
            ->values();

        $orders = Order::query()
            ->whereIn('status', ['new', 'searching', 'accepted', 'in_progress'])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get([
                'id',
                'lat',
                'lng',
                'status',
                'price',
                'created_at',
            ])
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'lat' => (float) $order->lat,
                'lng' => (float) $order->lng,
                'status' => $order->status,
                'price' => $order->price,
                'created_at' => optional($order->created_at)->toISOString(),
            ])
            ->values();

        return response()->json([
            'couriers' => $couriers,
            'orders' => $orders,
        ]);
    }
}
