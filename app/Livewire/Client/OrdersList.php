<?php

namespace App\Livewire\Client;

use App\Models\Order;
use Livewire\Component;
use Illuminate\Support\Collection;

class OrdersList extends Component
{
    /** UI */
    public string $tab = 'active'; // active | history

    /** Data */
    public Collection $activeOrders;
    public Collection $historyOrders;

    public function mount(): void
    {
        $this->loadOrders();
    }

    protected function loadOrders(): void
    {
        $userId = auth()->id();

        // Активные заказы
        $this->activeOrders = Order::query()
            ->where('client_id', $userId)
            ->whereNotIn('status', [
                Order::STATUS_DONE,
                Order::STATUS_CANCELLED,
            ])
            ->orderByDesc('created_at')
            ->get();

        // История заказов
        $this->historyOrders = Order::query()
            ->where('client_id', $userId)
            ->whereIn('status', [
                Order::STATUS_DONE,
                Order::STATUS_CANCELLED,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /* =========================================================
     |  ACTIONS
     | ========================================================= */

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['active', 'history'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    /**
     * 🔁 Повтор заказа из истории
     */
    public function repeatOrder(int $orderId): void
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('client_id', auth()->id())
            ->firstOrFail();

        $this->redirectRoute('client.order.create', [
            'address_id' => $order->address_id,
            'repeat'     => $order->id,
        ]);
    }

    public function render()
    {
        return view('livewire.client.orders-list', [
            'activeOrders'  => $this->activeOrders,
            'historyOrders' => $this->historyOrders,
            'tab'           => $this->tab,
        ])->layout('layouts.client');
    }
}
