<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Livewire\Component;

class AvailableOrders extends Component
{
    public bool $online = false;

    /**
     * Активное замовлення кур'єра (accepted / in_progress).
     * Заповнюється на render(), щоб завжди бути актуальним.
     */
    public ?Order $activeOrder = null;

    protected $listeners = [
        'courier-online-toggled' => 'syncOnlineState',
        'order-updated'          => '$refresh',
    ];

    /* -------------------------------------------------
     | MOUNT
     | ------------------------------------------------- */
    public function mount(): void
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isCourier()) {
            $this->online = $user->isCourierOnline();
        }
    }

    /* -------------------------------------------------
     | SYNC ONLINE STATE (reactive)
     | ------------------------------------------------- */
    public function syncOnlineState(): void
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isCourier()) {
            $this->online = $user->isCourierOnline();
        }
    }

    /* -------------------------------------------------
     | ACTIVE ORDER (accepted / in_progress)
     | ------------------------------------------------- */
    protected function resolveActiveOrder(?User $courier): ?Order
    {
        if (! $courier instanceof User) {
            return null;
        }

        return Order::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_PROGRESS,
            ])
            ->latest('accepted_at')
            ->first();
    }

    /* -------------------------------------------------
     | RENDER
     | ------------------------------------------------- */
    public function render()
    {
        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            $this->activeOrder = null;

            return view('livewire.courier.available-orders', [
                'orders'       => collect(),
                'geoRequired'  => false,
                'online'       => false,
                'activeOrder'  => null,
            ])->layout('layouts.courier');
        }

        // 1) Активне замовлення (якщо є) — використаємо для UI-блоків знизу
        $this->activeOrder = $this->resolveActiveOrder($courier);

        // 2) Доступні оффери (pending) — показуємо як “вхідні” замовлення
        //    Якщо є активне замовлення — можемо все одно витягнути список,
        //    але UI нижче підкаже, що нові брати не можна.
        $orders = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->with('order')
            ->latest()
            ->get()
            ->pluck('order')
            ->filter();

        return view('livewire.courier.available-orders', [
            'orders'       => $orders,
            'geoRequired'  => false,
            'online'       => $this->online,
            'activeOrder'  => $this->activeOrder,
        ])->layout('layouts.courier');
    }
}
