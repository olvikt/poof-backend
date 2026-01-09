<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\ClientAddress;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request)
    {
        $client = auth()->user();

        // 🔹 Адрес: выбранный или default
        $address = $request->address_id
            ? ClientAddress::where('id', $request->address_id)
                ->where('user_id', $client->id)
                ->firstOrFail()
            : $client->defaultAddress;

        abort_if(! $address, 422, 'Адрес не знайдено');

        $order = Order::create([
            'client_id'       => $client->id,
            'status'          => 'new',
            'type'            => $request->type,
            'service'         => $request->service,

            'bags_count'      => $request->bags_count,
            'total_weight_kg' => $request->total_weight_kg,

            'price'           => $this->calculatePrice($request->bags_count),
            'currency'        => 'UAH',

            'address_id'      => $address->id,
            'address'         => $address->address_text,
            'lat'             => $address->lat,
            'lng'             => $address->lng,

            'scheduled_date'  => $request->scheduled_date,
            'time_from'       => $request->time_from,
            'time_to'         => $request->time_to,

            'comment'         => $request->comment,
        ]);

        return response()->json([
            'success' => true,
            'order' => $order,
        ], 201);
    }

    protected function calculatePrice(int $bags): int
    {
        // MVP-логика, потом вынесем в сервис
        return 100 + ($bags - 1) * 25;
    }
}
