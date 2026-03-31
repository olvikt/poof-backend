<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\WayForPay\WayForPaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WayForPayCallbackController extends Controller
{
    private const SUCCESS_STATUSES = [
        'Approved',
    ];

    public function __invoke(Request $request, WayForPaySignature $signature): JsonResponse
    {
        $payload = $request->validate([
            'merchantAccount' => ['required', 'string'],
            'orderReference' => ['required', 'string'],
            'amount' => ['required'],
            'currency' => ['required', 'string'],
            'authCode' => ['nullable', 'string'],
            'cardPan' => ['nullable', 'string'],
            'transactionStatus' => ['required', 'string'],
            'reasonCode' => ['nullable'],
            'merchantSignature' => ['required', 'string'],
        ]);

        $secret = (string) config('payments.wayforpay.merchant_secret');
        $signatureValid = $signature->verify([
            $payload['merchantAccount'],
            $payload['orderReference'],
            (string) $payload['amount'],
            $payload['currency'],
            (string) ($payload['authCode'] ?? ''),
            (string) ($payload['cardPan'] ?? ''),
            $payload['transactionStatus'],
            (string) ($payload['reasonCode'] ?? ''),
        ], $secret, $payload['merchantSignature']);

        if (! $signatureValid) {
            Log::warning('WayForPay callback rejected: invalid signature.', [
                'order_reference' => $payload['orderReference'],
            ]);

            return response()->json(['status' => 'error'], 422);
        }

        $order = Order::query()->find($payload['orderReference']);

        if (! $order) {
            Log::warning('WayForPay callback rejected: order not found.', [
                'order_reference' => $payload['orderReference'],
            ]);

            return response()->json(['status' => 'error'], 404);
        }

        if (in_array($payload['transactionStatus'], self::SUCCESS_STATUSES, true) && ! $order->isPaid()) {
            $order->markAsPaid();
        }

        return $this->acknowledge($payload['orderReference'], $signature, $secret);
    }

    private function acknowledge(string $orderReference, WayForPaySignature $signature, string $secret): JsonResponse
    {
        $time = (string) now()->timestamp;
        $status = 'accept';

        $responseSignature = $signature->sign([
            $orderReference,
            $status,
            $time,
        ], $secret);

        return response()->json([
            'orderReference' => $orderReference,
            'status' => $status,
            'time' => $time,
            'signature' => $responseSignature,
        ]);
    }
}
