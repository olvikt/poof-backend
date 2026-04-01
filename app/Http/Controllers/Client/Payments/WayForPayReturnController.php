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
        $finalizeUrl = route('payments.wayforpay.return.finalize', [
            'next' => $destination,
            'orderReference' => $orderReference !== '' ? $orderReference : null,
            'payment' => $isApproved ? 'success' : 'failed',
        ]);

        Log::info('WayForPay return endpoint visited.', $this->buildDiagnosticsContext($request, [
            'event' => 'wayforpay_return_visited',
            'order_reference' => $orderReference !== '' ? $orderReference : null,
            'transaction_status' => $request->input('transactionStatus'),
            'payment_state' => $isApproved ? 'success' : 'failed',
            'destination' => $destination,
            'selected_redirect' => 'finalize_redirect',
            'selected_redirect_reason' => 'cross_site_return_requires_same_site_reentry_before_auth_check',
            'finalize_url' => $finalizeUrl,
        ]));

        return redirect($finalizeUrl);
    }

    public function finalize(Request $request): RedirectResponse
    {
        $destination = trim((string) $request->query('next', ''));

        if (! $this->isAllowedRedirectTarget($destination)) {
            $destination = route('client.orders');
        }

        if (auth()->check()) {
            Log::info('WayForPay return finalize resolved with active session.', $this->buildDiagnosticsContext($request, [
                'event' => 'wayforpay_return_finalize_authenticated',
                'selected_redirect' => $destination,
                'selected_redirect_reason' => 'session_restored_on_same_site_navigation',
            ]));

            return str_starts_with($destination, '/')
                ? redirect($destination)
                : redirect()->away($destination);
        }

        Log::warning('WayForPay return finalize handled without active session; redirecting to login.', $this->buildDiagnosticsContext($request, [
            'event' => 'wayforpay_return_finalize_without_session',
            'selected_redirect' => '/login',
            'selected_redirect_reason' => 'no_authenticated_user_after_same_site_reentry',
            'next' => $destination,
        ]));

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

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildDiagnosticsContext(Request $request, array $extra = []): array
    {
        $queryKeys = array_keys($request->query());
        sort($queryKeys);

        $context = [
            'method' => $request->method(),
            'host' => $request->getHost(),
            'path' => '/'.$request->path(),
            'query_keys' => $queryKeys,
            'has_session_cookie' => $request->cookies->has((string) config('session.cookie')),
            'has_xsrf_cookie' => $request->cookies->has('XSRF-TOKEN'),
            'session_id_present' => $request->hasSession() && $request->session()->getId() !== '',
            'is_authenticated' => auth()->check(),
            'user_id' => auth()->id(),
            'referer_present' => $request->headers->has('referer'),
            'origin_present' => $request->headers->has('origin'),
        ];

        return array_merge($context, $extra);
    }
}
