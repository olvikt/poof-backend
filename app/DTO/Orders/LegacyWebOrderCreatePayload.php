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
            'order_type' => $this->attributes['order_type'] ?? Order::TYPE_ONE_TIME,
            'client_id' => $clientId,
            'status' => Order::STATUS_NEW,
            'payment_status' => $this->attributes['payment_status'] ?? ($this->attributes['is_trial'] ? Order::PAY_PAID : Order::PAY_PENDING),

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
            'service_mode' => $this->attributes['service_mode'] ?? null,
            'window_from_at' => $this->attributes['window_from_at'] ?? null,
            'window_to_at' => $this->attributes['window_to_at'] ?? null,
            'valid_until_at' => $this->attributes['valid_until_at'] ?? null,
            'client_wait_preference' => $this->attributes['client_wait_preference'] ?? null,
            'promise_policy_version' => $this->attributes['promise_policy_version'] ?? null,

            'handover_type' => $this->attributes['handover_type'],
            'completion_policy' => $this->attributes['completion_policy'] ?? Order::COMPLETION_POLICY_NONE,
            'bags_count' => $this->attributes['bags_count'],
            'price' => $this->attributes['price'],
            'client_charge_amount' => $this->attributes['client_charge_amount'] ?? (int) $this->attributes['price'],
            'courier_payout_amount' => $this->attributes['courier_payout_amount'] ?? (int) $this->attributes['price'],
            'system_subsidy_amount' => $this->attributes['system_subsidy_amount'] ?? 0,
            'funding_source' => $this->attributes['funding_source'] ?? Order::FUNDING_CLIENT,
            'benefit_type' => $this->attributes['benefit_type'] ?? null,
            'origin' => $this->attributes['origin'] ?? Order::ORIGIN_CHECKOUT,
            'subscription_id' => $this->attributes['subscription_id'] ?? null,

            'promo_code' => $this->attributes['promo_code'],
            'is_trial' => $this->attributes['is_trial'],
            'trial_days' => $this->attributes['is_trial'] ? $this->attributes['trial_days'] : null,
        ];
    }
}
