<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\WayForPay\WayForPayCheckoutDataBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentStartController extends Controller
{
    public function __invoke(Order $order, WayForPayCheckoutDataBuilder $builder): View|RedirectResponse
    {
        abort_if($order->client_id !== auth()->id(), 403);

        if ($order->isPaid()) {
            return redirect()->route('client.orders');
        }

        $provider = (string) config('payments.default_provider');

        if ($provider !== 'wayforpay' || ! config('payments.wayforpay.enabled')) {
            if ((bool) config('payments.dev_fallback_enabled')) {
                return redirect()
                    ->route('client.payments.show', $order)
                    ->with('warning', 'WayForPay тимчасово недоступний. Використайте dev-оплату.');
            }

            return redirect()
                ->route('client.payments.show', $order)
                ->withErrors(['payment' => 'Платіжний провайдер тимчасово недоступний.']);
        }

        $checkoutData = $builder->build($order);

        return view('payments.wayforpay-redirect', [
            'payUrl' => (string) config('payments.wayforpay.pay_url'),
            'checkoutData' => $checkoutData,
        ]);
    }
}
