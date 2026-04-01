<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WayForPayReturnController extends Controller
{
    public const LOGIN_FALLBACK_NEXT_COOKIE = 'poof_payment_return_next';

    public function __invoke(Request $request): RedirectResponse
    {
        $transactionStatus = strtolower((string) $request->input('transactionStatus', ''));
        $orderReference = trim((string) $request->input('orderReference', ''));

        $isApproved = $transactionStatus === 'approved';

        $target = $isApproved
            ? (string) config('payments.wayforpay.approved_url')
            : (string) config('payments.wayforpay.declined_url');

        if (! $this->isAllowedRedirectTarget($target)) {
            $target = route('client.orders');
        }

        $destination = $this->appendPaymentStateToTarget($target, $isApproved, $orderReference);

        Log::info('WayForPay return endpoint visited.', [
            'event' => 'wayforpay_return_visited',
            'path' => $request->path(),
            'method' => $request->method(),
            'order_reference' => $orderReference,
            'transaction_status' => $request->input('transactionStatus'),
            'is_authenticated' => auth()->check(),
        ]);

        if (! auth()->check() && str_starts_with($destination, '/client/')) {
            Log::warning('WayForPay return handled without active session; redirecting to login.', [
                'event' => 'wayforpay_return_without_session',
                'order_reference' => $orderReference,
                'transaction_status' => $request->input('transactionStatus'),
                'next' => $destination,
            ]);

            return redirect('/login?'.http_build_query([
                'next' => $destination,
                'source' => 'wayforpay_return',
            ]))->cookie(cookie(
                self::LOGIN_FALLBACK_NEXT_COOKIE,
                $destination,
                15,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        }

        return str_starts_with($destination, '/')
            ? redirect($destination)
            : redirect()->away($destination);
    }

    private function appendPaymentStateToTarget(string $target, bool $isApproved, string $orderReference): string
    {
        $separator = str_contains($target, '?') ? '&' : '?';

        $query = [
            'payment' => $isApproved ? 'success' : 'failed',
            'source' => 'wayforpay_return',
        ];

        if ($orderReference !== '' && ctype_digit($orderReference)) {
            $query['order'] = $orderReference;
        }

        return $target.$separator.http_build_query($query);
    }

    private function isAllowedRedirectTarget(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            && in_array($parts['scheme'], ['http', 'https'], true);
    }
}
