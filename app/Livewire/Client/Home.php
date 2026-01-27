<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\Order;
use Illuminate\Support\Collection;

class Home extends Component
{
    public Collection $oneTimeOrders;
    public Collection $subscriptionOrders;

    public array $slides = [];

    public function mount(): void
    {
        $this->oneTimeOrders = collect();
        $this->subscriptionOrders = collect();

        $this->slides = [
            ['image' => asset('images/slides/slide-1.png')],
            ['image' => asset('images/slides/slide-2.png')],
            ['image' => asset('images/slides/slide-3.png')],
            ['image' => asset('images/slides/slide-4.png')],
        ];

        $orders = Order::query()
            ->where('client_id', auth()->id())
            ->whereIn('status', [
                'new',
                'searching',
                'accepted',
                'in_progress',
            ])
            ->orderByRaw("
                COALESCE(scheduled_date, DATE(created_at)) ASC,
                COALESCE(scheduled_time_from, '00:00') ASC
            ")
            ->get();

        $this->oneTimeOrders = $orders
            ->where('type', 'one_time')
            ->values();

        $this->subscriptionOrders = $orders
            ->where('type', 'subscription')
            ->values();
    }

    public function render()
    {
        return view('livewire.client.home')
            ->layout('layouts.client');
    }
}