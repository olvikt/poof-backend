<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class OfferCard extends Component
{
    public ?OrderOffer $offer = null;

    protected $listeners = [
        'courier-online-toggled' => 'loadOffer',
    ];

    /* =========================================================
     | LOAD CURRENT ACTIVE OFFER (UBER STYLE)
     ========================================================= */

    public function loadOffer(): void
    {
        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourierOnline()) {
            $this->offer = null;
            return;
        }

        $this->offer = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->first();
    }

    /* =========================================================
     | ACCEPT OFFER
     ========================================================= */

public function accept(): void
{
    if (! $this->offer) {
        return;
    }

    $courier = auth()->user();

    $ok = DB::transaction(function () use ($courier) {

        $offer = OrderOffer::query()
            ->whereKey($this->offer->id)
            ->lockForUpdate()
            ->first();

        if (! $offer || $offer->status !== OrderOffer::STATUS_PENDING) {
            return false;
        }

        $order = Order::query()
            ->whereKey($offer->order_id)
            ->lockForUpdate()
            ->first();

        if (! $order || ! $order->canBeAccepted()) {
            return false;
        }

        $offer->update([
            'status' => OrderOffer::STATUS_ACCEPTED,
        ]);

        $accepted = $order->acceptBy($courier);

        if (! $accepted) {
            return false;
        }

        $this->dispatch('map:courier-update', [
            'courierLat' => $courier->last_lat,
            'courierLng' => $courier->last_lng,
            'orderLat'   => $order->lat,
            'orderLng'   => $order->lng,
        ]);

        return true;
    });

    if (! $ok) {
        $this->dispatch(
            'notify',
            type: 'error',
            message: 'Не вдалося прийняти'
        );
        return;
    }

    // ✅ ВАЖНО: редирект через Livewire v3 API
    $this->redirectRoute('courier.my-orders', navigate: true);
}

    /* =========================================================
     | REJECT OFFER
     ========================================================= */

    public function reject(): void
    {
        if (! $this->offer) {
            return;
        }

        OrderOffer::query()
            ->whereKey($this->offer->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->update([
                'status' => OrderOffer::STATUS_DECLINED,
            ]);

        $this->dispatch(
            'notify',
            type: 'info',
            message: 'Пропущено'
        );

        $this->offer = null;
    }

    /* =========================================================
     | DISTANCE TO ORDER (computed property)
     ========================================================= */

    public function getDistanceKmProperty(): ?float
    {
        $courier = auth()->user();

        if (
            !$this->offer ||
            !$this->offer->order ||
            !$courier?->last_lat ||
            !$courier?->last_lng ||
            !$this->offer->order->lat ||
            !$this->offer->order->lng
        ) {
            return null;
        }

        return round(
            $this->haversine(
                (float) $courier->last_lat,
                (float) $courier->last_lng,
                (float) $this->offer->order->lat,
                (float) $this->offer->order->lng
            ),
            2
        );
    }

    protected function haversine(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) *
             cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /* ========================================================= */

    public function render()
    {
        return view('livewire.courier.offer-card');
    }
}
