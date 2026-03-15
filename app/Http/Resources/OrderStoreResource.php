<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'order' => [
                'id' => $this->id,
                'client_id' => $this->client_id,
                'status' => $this->status,
                'payment_status' => $this->payment_status,

                'type' => $this->type,
                'service' => $this->service,
                'bags_count' => $this->bags_count,
                'total_weight_kg' => (float) $this->total_weight_kg,

                'price' => (int) $this->price,
                'currency' => $this->currency,

                'address_id' => $this->address_id,
                'address_text' => $this->address_text,
                'lat' => $this->lat,
                'lng' => $this->lng,

                'scheduled_date' => optional($this->scheduled_date)->toDateString(),
                'time_from' => $this->time_from,
                'time_to' => $this->time_to,
                'comment' => $this->comment,

                'created_at' => optional($this->created_at)?->toISOString(),
                'updated_at' => optional($this->updated_at)?->toISOString(),
            ],
        ];
    }
}
