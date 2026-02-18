<?php

namespace App\Livewire\Courier;

use Livewire\Component;
use App\Models\OrderOffer;

class StackOfferPopup extends Component
{
    public ?OrderOffer $offer = null;

    protected $listeners = [
        'offer-created' => 'refreshOffer',
        'offer-expired' => 'refreshOffer',
        'offer-accepted' => 'refreshOffer',
    ];

    public function mount(): void
    {
        $this->refreshOffer();
    }

    public function refreshOffer(): void
    {
        $courier = auth()->user();

        if (! $courier) {
            $this->offer = null;
            return;
        }

        $this->offer = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->orderBy('created_at')
            ->first();
    }

    /* =========================================================
     | ACTIONS
     |=========================================================*/

    public function accept(): void
    {
        if (! $this->offer) return;

        $order = $this->offer->order;

        if (! $order || ! $order->acceptBy(auth()->user())) {
            return;
        }

        $this->offer->update([
            'status' => OrderOffer::STATUS_ACCEPTED,
        ]);

        $this->dispatch('offer-accepted');
        $this->refreshOffer();
    }

    public function decline(): void
    {
        if (! $this->offer) return;

        $this->offer->update([
            'status' => OrderOffer::STATUS_REJECTED,
        ]);

        $this->dispatch('offer-expired');
        $this->refreshOffer();
    }

    public function render()
    {
        if (! $this->offer) {
            // 🔑 НИЧЕГО НЕ РЕНДЕРИМ
            return view('livewire.courier.stack-offer-popup-empty');
        }

        return view('livewire.courier.stack-offer-popup');
    }
}
