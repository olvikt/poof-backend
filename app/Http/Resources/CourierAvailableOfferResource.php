<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderOffer */
class CourierAvailableOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->order;

        return [
            'offer_id' => (int) $this->id,
            'order_id' => $order?->id,
            'order_public_id' => $order?->public_id,
            'pickup' => [
                'address_text' => $order?->address_text,
            ],
            'delivery' => null,
            'price' => [
                'courier_payout_amount' => $order?->courier_payout_amount !== null ? (int) $order->courier_payout_amount : null,
                'order_price_amount' => $order?->price !== null ? (int) $order->price : null,
            ],
            'offer_status' => (string) $this->status,
            'offer_expires_at' => optional($this->expires_at)?->toISOString(),
            'service' => [
                'service_mode' => $order?->service_mode,
                'window_from_at' => optional($order?->window_from_at)?->toISOString(),
                'window_to_at' => optional($order?->window_to_at)?->toISOString(),
            ],
        ];
    }
}
