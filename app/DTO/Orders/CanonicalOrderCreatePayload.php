<?php

namespace App\DTO\Orders;

use App\Models\ClientAddress;
use App\Models\Order;

class CanonicalOrderCreatePayload
{
    private const CONTRACT_FIELDS = [
        'type',
        'service',
        'service_mode',
        'client_wait_preference',
        'bags_count',
        'total_weight_kg',
        'scheduled_date',
        'time_from',
        'time_to',
        'comment',
    ];

    public function __construct(private readonly array $attributes)
    {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(collect($validated)
            ->only(self::CONTRACT_FIELDS)
            ->all());
    }

    public function toOrderAttributes(int $clientId, ClientAddress $address, int $price): array
    {
        return [
            'client_id' => $clientId,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,

            'type' => $this->attributes['type'],
            'service' => $this->attributes['service'],
            'bags_count' => $this->attributes['bags_count'],
            'total_weight_kg' => $this->attributes['total_weight_kg'],

            'price' => $price,
            'currency' => 'UAH',

            'address_id' => $address->id,
            'address_text' => $address->address_text,
            'lat' => $address->lat,
            'lng' => $address->lng,

            'scheduled_date' => $this->attributes['scheduled_date'],
            'time_from' => $this->attributes['time_from'],
            'time_to' => $this->attributes['time_to'],
            'comment' => $this->attributes['comment'] ?? null,
            'service_mode' => $this->attributes['service_mode'] ?? null,
            'client_wait_preference' => $this->attributes['client_wait_preference'] ?? null,
        ];
    }

    public function bagsCount(): int
    {
        return (int) $this->attributes['bags_count'];
    }
}
