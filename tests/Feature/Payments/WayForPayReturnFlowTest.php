<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\WayForPay\WayForPaySignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WayForPayReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_wayforpay_return_accepts_post_and_redirects_without_405(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $response = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
        ]);

        $response
            ->assertStatus(302)
            ->assertRedirect('/client/orders?payment=success&source=wayforpay_return');
    }

    public function test_wayforpay_return_accepts_get_and_redirects(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $response = $this->get('/payments/wayforpay/return?transactionStatus=Declined');

        $response
            ->assertStatus(302)
            ->assertRedirect('/client/orders?payment=failed&source=wayforpay_return');
    }

    public function test_wayforpay_return_does_not_mark_order_as_paid(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Тестова, 1',
            'price' => 100,
        ]);

        $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->assertStatus(302);

        $this->assertSame(Order::PAY_PENDING, $order->fresh()->payment_status);
    }

    public function test_callback_marks_order_as_paid_and_is_separate_from_return(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Callback, 1',
            'price' => 100,
        ]);

        $payload = [
            'merchantAccount' => 'poof_merchant',
            'orderReference' => (string) $order->id,
            'amount' => '100',
            'currency' => 'UAH',
            'authCode' => '123456',
            'cardPan' => '411111******1111',
            'transactionStatus' => 'Approved',
            'reasonCode' => '1100',
        ];

        $payload['merchantSignature'] = app(WayForPaySignature::class)->sign([
            $payload['merchantAccount'],
            $payload['orderReference'],
            (string) $payload['amount'],
            $payload['currency'],
            (string) $payload['authCode'],
            (string) $payload['cardPan'],
            $payload['transactionStatus'],
            (string) $payload['reasonCode'],
        ], $secret);

        $this->postJson('/api/payments/wayforpay/callback', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'accept');

        $this->assertSame(Order::PAY_PAID, $order->fresh()->payment_status);
    }
}
