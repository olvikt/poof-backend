<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use Livewire\Component;

class MyOrders extends Component
{
    public function render()
    {
        $orders = Order::activeForCourier()
    ->where('courier_id', auth()->id())
    ->get();

        return view('livewire.courier.my-orders', [
            'orders' => $orders,
        ])->layout('layouts.courier');
    }
}
