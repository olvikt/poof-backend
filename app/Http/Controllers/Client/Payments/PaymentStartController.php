<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\WayForPay\WayForPayCheckoutDataBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentStartController extends Controller
{
    public function __invoke(Request $request, Order $order, WayForPayCheckoutDataBuilder $builder): View|RedirectResponse
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

        $this->storeReturnDiagnosticsBaseline($request, $order);

        $checkoutData = $builder->build($order);

        $response = response()->view('payments.wayforpay-redirect', [
            'payUrl' => (string) config('payments.wayforpay.pay_url'),
            'checkoutData' => $checkoutData,
        ]);

        Log::info('WayForPay payment start response diagnostics.', [
            'event' => 'wayforpay_payment_start_response',
            'order_id' => $order->id,
            'host' => $request->getHost(),
            'path' => '/'.$request->path(),
            'response_cookie_names' => $this->extractResponseCookieNames($response),
            'response_sets_session_cookie' => $this->responseSetsCookie($response, (string) config('session.cookie')),
            'pay_url' => (string) config('payments.wayforpay.pay_url'),
            'pay_url_host' => parse_url((string) config('payments.wayforpay.pay_url'), PHP_URL_HOST),
            'return_url' => (string) config('payments.wayforpay.return_url'),
            'return_url_host' => parse_url((string) config('payments.wayforpay.return_url'), PHP_URL_HOST),
            'app_url' => (string) config('app.url'),
            'app_url_host' => parse_url((string) config('app.url'), PHP_URL_HOST),
            'request_session_cookie_present' => $request->cookies->has((string) config('session.cookie')),
            'request_xsrf_cookie_present' => $request->cookies->has('XSRF-TOKEN'),
        ]);

        return $response;
    }

    private function storeReturnDiagnosticsBaseline(Request $request, Order $order): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();
        $sessionKeys = array_keys($session->all());
        sort($sessionKeys);

        $authKeyHints = array_values(array_filter(
            $sessionKeys,
            static fn (string $key): bool => str_contains($key, 'login_') || str_contains($key, '_token')
        ));

        $baseline = [
            'captured_at' => now()->toIso8601String(),
            'order_id' => $order->id,
            'session_id' => $session->getId(),
            'session_keys' => $sessionKeys,
            'auth_key_hints' => $authKeyHints,
            'default_guard' => (string) config('auth.defaults.guard', 'web'),
            'web_guard_user_id' => Auth::guard('web')->id(),
            'session_cookie' => (string) config('session.cookie'),
            'session_driver' => (string) config('session.driver'),
            'session_domain' => config('session.domain'),
            'session_secure_cookie' => (bool) config('session.secure'),
            'session_same_site' => (string) config('session.same_site', 'lax'),
            'session_lifetime' => (int) config('session.lifetime'),
        ];

        $session->put('wayforpay_return_baseline', $baseline);
        $session->save();

        Log::info('WayForPay pre-payment session baseline captured.', [
            'event' => 'wayforpay_pre_payment_session_baseline',
            'order_id' => $order->id,
            'host' => $request->getHost(),
            'path' => '/'.$request->path(),
            'web_guard_authenticated' => Auth::guard('web')->check(),
            'web_guard_user_id' => Auth::guard('web')->id(),
            'default_guard_authenticated' => auth()->check(),
            'default_guard_user_id' => auth()->id(),
            'baseline' => $baseline,
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractResponseCookieNames(Response $response): array
    {
        $cookies = $response->headers->getCookies();
        $names = [];

        foreach ($cookies as $cookie) {
            $names[] = $cookie->getName();
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    private function responseSetsCookie(Response $response, string $cookieName): bool
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return true;
            }
        }

        return false;
    }
}
