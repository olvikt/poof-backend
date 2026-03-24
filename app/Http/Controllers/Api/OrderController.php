<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\Create\CreateCanonicalOrderAction;
use App\DTO\Orders\CanonicalOrderCreatePayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderStoreResource;
use App\Jobs\DispatchOrderJob;
use App\Models\ClientAddress;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request)
    {
        $client = auth()->user();
        $payload = CanonicalOrderCreatePayload::fromValidated($request->validated());

        $addressId = $request->validated('address_id');

        $address = $addressId
            ? ClientAddress::query()
                ->whereKey($addressId)
                ->where('user_id', $client->id)
                ->firstOrFail()
            : ClientAddress::query()
                ->where('user_id', $client->id)
                ->where('is_default', true)
                ->first();

        abort_if(! $address, 422, 'Адрес не знайдено');

        $order = app(CreateCanonicalOrderAction::class)->handle(
            client: $client,
            payload: $payload,
            address: $address,
        );

        DispatchOrderJob::dispatch($order);

        return (new OrderStoreResource($order))
            ->response()
            ->setStatusCode(201);
    }

}
