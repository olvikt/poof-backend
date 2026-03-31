<?php

namespace Tests\Unit\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\WayForPay\WayForPayCheckoutDataBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WayForPayCheckoutDataBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_uses_dedicated_return_url_and_keeps_callback_url_separate(): void
    {
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');
        config()->set('payments.wayforpay.merchant_secret', 'secret');
        config()->set('payments.wayforpay.merchant_domain', 'app.poof.com.ua');
        config()->set('payments.wayforpay.service_url', 'https://api.poof.test/api/payments/wayforpay/callback');
        config()->set('payments.wayforpay.return_url', 'https://app.poof.test/payments/wayforpay/return');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Тестова, 2',
            'price' => 100,
        ]);

        $payload = app(WayForPayCheckoutDataBuilder::class)->build($order->fresh());

        $this->assertSame('https://api.poof.test/api/payments/wayforpay/callback', $payload['serviceUrl']);
        $this->assertSame('https://app.poof.test/payments/wayforpay/return', $payload['returnUrl']);
    }
}
