<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use Livewire\Component;

class AvailableOrders extends Component
{
    protected $listeners = ['order-created' => '$refresh'];

    public function render()
    {
		logger()->info('Courier poll tick');
		
        $orders = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('courier_id')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.courier.available-orders', [
            'orders' => $orders,
        ])->layout('layouts.courier');
    }
}