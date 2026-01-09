<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;

class OrdersMap extends Widget
{
    protected static string $view = 'filament.widgets.orders-map';

    protected static ?int $sort = 1;

    /**
     * ✅ Filament v3: columnSpan ТОЛЬКО через public method
     */
    public function getColumnSpan(): string|int|array
    {
        return 'full';
    }

    protected function getViewData(): array
    {
        return [
            'orders' => Order::query()
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->get()
                ->map(fn (Order $order) => [
                    'id'      => $order->id,
                    'lat'     => $order->lat,
                    'lng'     => $order->lng,
                    'status'  => $order->status,
                    'address' => $order->address,
                    'price'   => $order->price,
                    'editUrl' => route(
                        'filament.admin.resources.orders.edit',
                        $order
                    ),
                ]),
        ];
    }
}


