<?php

declare(strict_types=1);

namespace App\Services\Payments\WayForPay;

use App\Models\Order;

class WayForPayCheckoutDataBuilder
{
    public function __construct(private readonly WayForPaySignature $signature)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Order $order): array
    {
        $merchantAccount = (string) config('payments.wayforpay.merchant_account');
        $merchantSecret = (string) config('payments.wayforpay.merchant_secret');
        $merchantDomainName = (string) config('payments.wayforpay.merchant_domain');
        $currency = (string) config('payments.wayforpay.currency', 'UAH');
        $language = (string) config('payments.wayforpay.language', 'UA');

        $orderReference = (string) $order->id;
        $orderDate = $order->created_at?->timestamp ?? now()->timestamp;
        $amount = number_format((float) $order->price, 2, '.', '');

        $productName = ['POOF Order #' . $order->id];
        $productPrice = [(string) $order->price];
        $productCount = ['1'];

        $fieldsForSignature = [
            $merchantAccount,
            $merchantDomainName,
            $orderReference,
            (string) $orderDate,
            $amount,
            $currency,
            ...$productName,
            ...$productCount,
            ...$productPrice,
        ];

        $merchantSignature = $this->signature->sign($fieldsForSignature, $merchantSecret);

        return [
            'merchantAccount' => $merchantAccount,
            'merchantAuthType' => 'SimpleSignature',
            'merchantDomainName' => $merchantDomainName,
            'merchantTransactionSecureType' => 'AUTO',
            'orderReference' => $orderReference,
            'orderDate' => $orderDate,
            'amount' => $amount,
            'currency' => $currency,
            'productName' => $productName,
            'productPrice' => $productPrice,
            'productCount' => $productCount,
            'serviceUrl' => (string) config('payments.wayforpay.service_url'),
            'returnUrl' => (string) config('payments.wayforpay.return_url'),
            'language' => $language,
            'merchantSignature' => $merchantSignature,
            'clientFirstName' => $order->client?->name,
            'clientEmail' => $order->client?->email,
        ];
    }
}
