<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentPageController extends Controller
{
    public function __invoke(Order $order): View|RedirectResponse
    {
        abort_if($order->client_id !== auth()->id(), 403);

        if ($order->isPaid()) {
            return redirect()
                ->route('client.orders')
                ->with('success', 'Замовлення вже оплачено.');
        }

        return view('payments.pay', [
            'order' => $order,
            'paymentProvider' => config('payments.default_provider'),
            'devFallbackEnabled' => (bool) config('payments.dev_fallback_enabled'),
        ]);
    }
}
