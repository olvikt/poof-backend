<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WayForPayReturnController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $transactionStatus = strtolower((string) $request->input('transactionStatus', ''));

        $isApproved = $transactionStatus === 'approved';

        $target = $isApproved
            ? (string) config('payments.wayforpay.approved_url')
            : (string) config('payments.wayforpay.declined_url');

        if (! $this->isAllowedRedirectTarget($target)) {
            $target = route('client.orders');
        }

        $separator = str_contains($target, '?') ? '&' : '?';

        $destination = $target.$separator.http_build_query([
            'payment' => $isApproved ? 'success' : 'failed',
            'source' => 'wayforpay_return',
        ]);

        return str_starts_with($destination, '/')
            ? redirect($destination)
            : redirect()->away($destination);
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
