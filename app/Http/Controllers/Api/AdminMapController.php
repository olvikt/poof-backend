<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminMapController extends Controller
{
    public function index(): JsonResponse
    {
        abort_if(! auth()->user()?->isAdmin(), 403);

        $couriers = Courier::query()
            ->activeOnMap()
            ->whereHas('user', function ($query) {
                $query->where('role', User::ROLE_COURIER)
                    ->whereNotNull('last_lat')
                    ->whereNotNull('last_lng');
            })
            ->with('user:id,name,last_lat,last_lng')
            ->get(['id', 'user_id', 'status'])
            ->map(fn (Courier $courier) => [
                'id' => $courier->id,
                'name' => $courier->user?->name,
                'lat' => (float) $courier->user?->last_lat,
                'lng' => (float) $courier->user?->last_lng,
                'status' => $courier->status,
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
