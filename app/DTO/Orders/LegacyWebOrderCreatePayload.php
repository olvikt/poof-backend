<?php

namespace App\DTO\Orders;

use App\Models\Order;

class LegacyWebOrderCreatePayload
{
    public function __construct(private readonly array $attributes)
    {
    }

    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public function toOrderAttributes(int $clientId): array
    {
        return [
            'client_id' => $clientId,
            'order_type' => Order::TYPE_ONE_TIME,
            'status' => Order::STATUS_NEW,
            'payment_status' => $this->attributes['is_trial'] ? Order::PAY_PAID : Order::PAY_PENDING,

            'address_id' => $this->attributes['address_id'],
            'address_text' => $this->attributes['address_text'],
            'lat' => $this->attributes['lat'],
            'lng' => $this->attributes['lng'],

            'entrance' => $this->attributes['entrance'],
            'floor' => $this->attributes['floor'],
            'apartment' => $this->attributes['apartment'],
            'intercom' => $this->attributes['intercom'],
            'comment' => $this->attributes['comment'],

            'scheduled_date' => $this->attributes['scheduled_date'],
            'scheduled_time_from' => $this->attributes['scheduled_time_from'],
            'scheduled_time_to' => $this->attributes['scheduled_time_to'],

            'handover_type' => $this->attributes['handover_type'],
            'bags_count' => $this->attributes['bags_count'],
            'price' => $this->attributes['price'],

            'promo_code' => $this->attributes['promo_code'],
            'is_trial' => $this->attributes['is_trial'],
            'trial_days' => $this->attributes['is_trial'] ? $this->attributes['trial_days'] : null,
        ];
    }
}
