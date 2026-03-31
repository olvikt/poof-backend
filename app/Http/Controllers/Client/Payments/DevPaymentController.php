<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

class DevPaymentController extends Controller
{
    public function __invoke(Order $order): RedirectResponse
    {
        abort_if($order->client_id !== auth()->id(), 403);

        $devPaymentEnabled = app()->environment(['local', 'testing'])
            || (bool) config('payments.dev_fallback_enabled');

        abort_unless($devPaymentEnabled, 404);

        if (! $order->isPaid()) {
            $order->markAsPaid();
        }

        return redirect()
            ->route('client.orders')
            ->with('success', 'Оплату підтверджено. Ми шукаємо курʼєра.');
    }
}
