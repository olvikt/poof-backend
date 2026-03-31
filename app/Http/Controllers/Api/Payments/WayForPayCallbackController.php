<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\WayForPay\WayForPaySignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WayForPayCallbackController extends Controller
{
    private const SUCCESS_STATUSES = [
        'Approved',
    ];

    public function __invoke(Request $request, WayForPaySignature $signature): JsonResponse
    {
        $payload = $this->extractPayload($request);

        Log::info('WayForPay callback received.', [
            'source_ip' => $request->ip(),
            'path' => $request->path(),
            'content_type' => (string) $request->header('Content-Type', ''),
            'payload_keys' => array_keys($payload),
        ]);

        $validator = Validator::make($payload, [
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

        if ($validator->fails()) {
            $missingFields = [];

            foreach ($validator->errors()->messages() as $field => $messages) {
                if (in_array('The '.$field.' field is required.', $messages, true)) {
                    $missingFields[] = $field;
                }
            }

            Log::warning('WayForPay callback rejected: invalid payload.', [
                'source_ip' => $request->ip(),
                'path' => $request->path(),
                'content_type' => (string) $request->header('Content-Type', ''),
                'payload_keys' => array_keys($payload),
                'missing_required_fields' => $missingFields,
                'validation_errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $secret = (string) config('payments.wayforpay.merchant_secret');
        $signatureValid = $signature->verify([
            $validated['merchantAccount'],
            $validated['orderReference'],
            (string) $validated['amount'],
            $validated['currency'],
            (string) ($validated['authCode'] ?? ''),
            (string) ($validated['cardPan'] ?? ''),
            $validated['transactionStatus'],
            (string) ($validated['reasonCode'] ?? ''),
        ], $secret, $validated['merchantSignature']);

        if (! $signatureValid) {
            Log::warning('WayForPay callback rejected: invalid signature.', [
                'source_ip' => $request->ip(),
                'path' => $request->path(),
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
            ]);

            return response()->json(['status' => 'error'], 422);
        }

        $order = Order::query()->find($validated['orderReference']);

        if (! $order) {
            Log::warning('WayForPay callback rejected: order not found.', [
                'source_ip' => $request->ip(),
                'path' => $request->path(),
                'order_reference' => $validated['orderReference'],
            ]);

            return response()->json(['status' => 'error'], 404);
        }

        if (in_array($validated['transactionStatus'], self::SUCCESS_STATUSES, true) && ! $order->isPaid()) {
            $order->markAsPaid();
        }

        Log::info('WayForPay callback processed successfully.', [
            'source_ip' => $request->ip(),
            'path' => $request->path(),
            'order_reference' => $validated['orderReference'],
            'transaction_status' => $validated['transactionStatus'],
            'order_marked_paid' => $order->isPaid(),
        ]);

        return $this->acknowledge($validated['orderReference'], $signature, $secret);
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

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        $payload = $request->all();

        if ($payload !== []) {
            return $payload;
        }

        $rawBody = trim((string) $request->getContent());

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }
}
