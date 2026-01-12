<?php

namespace App\Livewire\Client;

use App\Models\Order;
use Livewire\Component;
use Illuminate\Support\Collection;

class OrdersList extends Component
{
    public function render()
    {
        $orders = Order::query()
            ->where('client_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.client.orders-list', [
            'activeOrders'  => $this->activeOrders($orders),
            'historyOrders' => $this->historyOrders($orders),
        ])->layout('layouts.client');
    }

    /* =========================================================
     |  CLIENT LOGIC
     | ========================================================= */

    protected function activeOrders(Collection $orders): Collection
    {
        return $orders->filter(fn (Order $order) =>
            ! in_array($order->status, [
                Order::STATUS_DONE,
                Order::STATUS_CANCELLED,
            ], true)
        );
    }

    protected function historyOrders(Collection $orders): Collection
    {
        return $orders->filter(fn (Order $order) =>
            in_array($order->status, [
                Order::STATUS_DONE,
                Order::STATUS_CANCELLED,
            ], true)
        );
    }
}