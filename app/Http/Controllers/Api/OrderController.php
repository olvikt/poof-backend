<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderStoreResource;
use App\Jobs\DispatchOrderJob;
use App\Models\ClientAddress;
use App\Models\Order;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request)
    {
        $client = auth()->user();
        $payload = $request->validated();

        $address = isset($payload['address_id'])
            ? ClientAddress::query()
                ->whereKey($payload['address_id'])
                ->where('user_id', $client->id)
                ->firstOrFail()
            : ClientAddress::query()
                ->where('user_id', $client->id)
                ->where('is_default', true)
                ->first();

        abort_if(! $address, 422, 'Адрес не знайдено');

        $order = Order::create([
            'client_id'       => $client->id,
            'status'          => Order::STATUS_NEW,
            'payment_status'  => Order::PAY_PENDING,

            'type'            => $payload['type'],
            'service'         => $payload['service'],
            'bags_count'      => $payload['bags_count'],
            'total_weight_kg' => $payload['total_weight_kg'],

            'price'           => $this->calculatePrice($payload['bags_count']),
            'currency'        => 'UAH',

            'address_id'      => $address->id,
            'address_text'    => $address->address_text,
            'lat'             => $address->lat,
            'lng'             => $address->lng,

            'scheduled_date'  => $payload['scheduled_date'],
            'time_from'       => $payload['time_from'],
            'time_to'         => $payload['time_to'],
            'comment'         => $payload['comment'] ?? null,
        ]);

        DispatchOrderJob::dispatch($order);

        return (new OrderStoreResource($order))
            ->response()
            ->setStatusCode(201);
    }

    protected function calculatePrice(int $bags): int
    {
        return 100 + ($bags - 1) * 25;
    }
}
