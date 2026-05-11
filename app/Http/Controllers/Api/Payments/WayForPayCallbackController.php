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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WayForPayCallbackController extends Controller
{
    private const SUCCESS_STATUSES = [
        'Approved',
    ];

    public function __invoke(Request $request, WayForPaySignature $signature): JsonResponse
    {
        $payload = $this->extractPayload($request);

        Log::info('WayForPay callback received.', [
            'event' => 'wayforpay_callback_received',
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
            'transactionId' => ['nullable', 'string'],
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
                'event' => 'wayforpay_callback_invalid_payload',
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
                'event' => 'wayforpay_callback_invalid_signature',
                'source_ip' => $request->ip(),
                'path' => $request->path(),
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature.',
            ], 422);
        }

        $order = Order::query()->find($validated['orderReference']);

        if (! $order) {
            Log::warning('WayForPay callback rejected: order not found.', [
                'event' => 'wayforpay_callback_order_not_found',
                'source_ip' => $request->ip(),
                'path' => $request->path(),
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Order not found.',
            ], 404);
        }

        $providerTransactionId = $this->extractProviderTransactionId($validated);
        $economicsValidationError = $this->validateEconomics($order, $validated);

        if ($economicsValidationError !== null) {
            Log::warning('WayForPay callback rejected: economic mismatch.', [
                'event' => 'wayforpay_callback_economic_mismatch',
                'order_id' => $order->id,
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
                'reason' => $economicsValidationError,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Economic validation failed.',
            ], 422);
        }

        Log::info('WayForPay transaction status received.', [
            'event' => 'wayforpay_callback_transaction_status_received',
            'order_id' => $order->id,
            'order_reference' => $validated['orderReference'],
            'transaction_status' => $validated['transactionStatus'],
        ]);

        $isSuccessStatus = in_array($validated['transactionStatus'], self::SUCCESS_STATUSES, true);
        $alreadyPaid = false;
        $providerTransactionReused = false;
        $providerTransactionMismatch = false;

        DB::transaction(function () use ($order, $providerTransactionId, $validated, $isSuccessStatus, &$alreadyPaid, &$providerTransactionReused, &$providerTransactionMismatch): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $alreadyPaid = $lockedOrder->isPaid();

            if ($providerTransactionId !== null) {
                $reusedByAnotherOrder = Order::query()
                    ->where('payment_provider_transaction_id', $providerTransactionId)
                    ->whereKeyNot($lockedOrder->id)
                    ->exists();

                if ($reusedByAnotherOrder) {
                    $providerTransactionReused = true;
                    return;
                }

                if (
                    $lockedOrder->payment_provider_transaction_id !== null
                    && $lockedOrder->payment_provider_transaction_id !== $providerTransactionId
                ) {
                    $providerTransactionMismatch = true;
                    return;
                }

                if ($lockedOrder->payment_provider_transaction_id === null) {
                    $lockedOrder->forceFill([
                        'payment_provider_transaction_id' => $providerTransactionId,
                        'payment_provider_reference' => (string) $validated['orderReference'],
                    ])->save();
                }
            }

            if ($isSuccessStatus) {
                $lockedOrder->markAsPaid();
            }
        });

        if ($providerTransactionReused) {
            Log::warning('WayForPay callback rejected: provider transaction reused by another order.', [
                'event' => 'wayforpay_callback_provider_tx_reused',
                'order_id' => $order->id,
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'provider_transaction_reused',
            ], 422);
        }

        if ($providerTransactionMismatch) {
            Log::warning('WayForPay callback rejected: provider transaction mismatch for order.', [
                'event' => 'wayforpay_callback_provider_tx_mismatch',
                'order_id' => $order->id,
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Provider transaction mismatch.',
            ], 422);
        }

        $order->refresh();

        if ($isSuccessStatus && $alreadyPaid) {
            Log::info('payment_callback_replayed', [
                'order_id' => $order->id,
                'subscription_id' => $order->subscription_id !== null ? (int) $order->subscription_id : null,
                'status' => (string) $order->status,
                'reason' => 'order_already_paid',
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
                'counter' => 'payment_callback_replayed_total',
                'counter_increment' => 1,
            ]);
        }

        if (! $isSuccessStatus) {
            Log::warning('WayForPay callback received non-success transaction status.', [
                'event' => 'wayforpay_callback_non_success_status',
                'order_id' => $order->id,
                'order_reference' => $validated['orderReference'],
                'transaction_status' => $validated['transactionStatus'],
            ]);
        }

        Log::info('WayForPay callback processed successfully.', [
            'event' => 'wayforpay_callback_processed_successfully',
            'source_ip' => $request->ip(),
            'path' => $request->path(),
            'order_id' => $order->id,
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
        $jsonPayload = $request->json()->all();

        if (is_array($jsonPayload) && $jsonPayload !== []) {
            return $jsonPayload;
        }

        $payload = $request->all();

        if ($payload !== []) {
            return $this->normalizeFormPayload($payload);
        }

        $rawBody = trim((string) $request->getContent());

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $formDecoded = [];
        parse_str($rawBody, $formDecoded);

        if (is_array($formDecoded) && $formDecoded !== []) {
            return $this->normalizeFormPayload($formDecoded);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeFormPayload(array $payload): array
    {
        if (count($payload) !== 1) {
            return $payload;
        }

        $firstKey = (string) array_key_first($payload);
        $firstValue = $payload[$firstKey];
        $firstValueString = is_string($firstValue) ? trim($firstValue) : '';

        if ($firstValueString !== '' && Str::startsWith($firstValueString, '{')) {
            $decodedValue = json_decode($firstValueString, true);

            if (is_array($decodedValue)) {
                return $decodedValue;
            }
        }

        if (Str::startsWith($firstKey, '{')) {
            $decodedKey = json_decode($firstKey, true);

            if (is_array($decodedKey)) {
                return $decodedKey;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function extractProviderTransactionId(array $validated): ?string
    {
        $transactionId = $validated['transactionId'] ?? null;

        if (! is_string($transactionId) || trim($transactionId) === '') {
            return null;
        }

        return trim($transactionId);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validateEconomics(Order $order, array $validated): ?string
    {
        $configuredMerchantAccount = (string) config('payments.wayforpay.merchant_account');
        if ($validated['merchantAccount'] !== $configuredMerchantAccount) {
            return 'merchant_account_mismatch';
        }

        if ((string) $validated['orderReference'] !== (string) $order->id) {
            return 'order_reference_mismatch';
        }

        if ((string) $validated['currency'] !== (string) $order->currency) {
            return 'currency_mismatch';
        }

        $expectedAmount = $this->normalizeAmount($order->client_charge_amount ?? $order->price);
        $receivedAmount = $this->normalizeAmount($validated['amount']);

        if ($expectedAmount !== $receivedAmount) {
            return 'amount_mismatch';
        }

        return null;
    }

    private function normalizeAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
