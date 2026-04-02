<?php

namespace Tests\Feature\Payments;

use App\Http\Controllers\Client\Payments\PaymentStartController;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\WayForPay\WayForPayCheckoutDataBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

class PaymentStartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_client_can_start_payment_and_get_wayforpay_redirect_view(): void
    {
        config()->set('payments.default_provider', 'wayforpay');
        config()->set('payments.wayforpay.enabled', true);
        config()->set('payments.wayforpay.pay_url', 'https://secure.wayforpay.com/pay');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Start, 1',
            'price' => 100,
        ]);

        $response = $this->actingAs($client, 'web')
            ->post(route('client.payments.start', $order));

        $response
            ->assertOk()
            ->assertSee('id="wayforpay-payment-form"', false)
            ->assertSee('action="https://secure.wayforpay.com/pay"', false);
    }

    public function test_payment_start_controller_invoke_returns_view_and_not_http_response_instance(): void
    {
        config()->set('payments.default_provider', 'wayforpay');
        config()->set('payments.wayforpay.enabled', true);
        config()->set('payments.wayforpay.pay_url', 'https://secure.wayforpay.com/pay');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Type, 1',
            'price' => 100,
        ]);

        $builder = Mockery::mock(WayForPayCheckoutDataBuilder::class);
        $builder->shouldReceive('build')
            ->once()
            ->with($order)
            ->andReturn([
                'merchantAccount' => 'poof_merchant',
                'orderReference' => (string) $order->id,
            ]);

        $this->actingAs($client, 'web');

        $request = Request::create(route('client.payments.start', $order), 'POST');

        $controller = app(PaymentStartController::class);
        $result = $controller($request, $order, $builder);

        $this->assertInstanceOf(View::class, $result);
    }
}
