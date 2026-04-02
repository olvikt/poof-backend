<?php

namespace Tests\Feature\Payments;

use App\Http\Controllers\Client\Payments\WayForPayReturnController;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\WayForPay\WayForPaySignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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
            'orderReference' => '42',
        ]);

        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('/payments/wayforpay/return/finalize?', $location);
        $this->assertStringContainsString('next=%2Fclient%2Forders%3Fpayment%3Dsuccess%26source%3Dwayforpay_return%26order%3D42', $location);
    }

    public function test_wayforpay_return_accepts_get_and_redirects(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $response = $this->get('/payments/wayforpay/return?transactionStatus=Declined');

        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('/payments/wayforpay/return/finalize?', $location);
        $this->assertStringContainsString('next=%2Fclient%2Forders%3Fpayment%3Dfailed%26source%3Dwayforpay_return', $location);
    }

    public function test_wayforpay_return_for_authenticated_user_redirects_to_orders_without_login(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $user = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => '77',
        ]);

        $response->assertStatus(302);
        $this->assertStringStartsWith(
            '/payments/wayforpay/return/finalize?',
            (string) $response->headers->get('Location')
        );
    }

    public function test_cross_site_style_post_return_does_not_force_immediate_login_redirect(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');

        $response = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => '99',
        ]);

        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('/payments/wayforpay/return/finalize?', $location);
        $this->assertStringNotStartsWith('/login', $location);
    }

    public function test_authenticated_client_success_return_stays_in_session_and_sees_payment_success_context(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Success Path, 1',
            'price' => 100,
        ]);

        $returnResponse = $this->actingAs($client)->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ]);

        $returnResponse->assertStatus(302);
        $finalizeUrl = (string) $returnResponse->headers->get('Location');

        $this->assertStringStartsWith('/payments/wayforpay/return/finalize?', $finalizeUrl);

        $this->actingAs($client)->get($finalizeUrl)
            ->assertRedirect('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);

        $this->get('/client/orders?payment=success&source=wayforpay_return&order='.$order->id)
            ->assertOk()
            ->assertSee('Оплату успішно підтверджено', false)
            ->assertSee('#'.$order->id, false);
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

    public function test_callback_accepts_form_urlencoded_payload(): void
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
            'address_text' => 'вул. Form, 1',
            'price' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');

        $this->post('/api/payments/wayforpay/callback', $payload, [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ])->assertOk();

        $this->assertSame(Order::PAY_PAID, $order->fresh()->payment_status);
    }

    public function test_callback_accepts_json_as_single_form_urlencoded_key(): void
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
            'address_text' => 'вул. Weird Form, 1',
            'price' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $body = rawurlencode(json_encode($payload, JSON_THROW_ON_ERROR)).'=';

        $this->call(
            'POST',
            '/api/payments/wayforpay/callback',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            $body
        )->assertOk();

        $this->assertSame(Order::PAY_PAID, $order->fresh()->payment_status);
    }

    public function test_callback_with_missing_required_fields_returns_json_422_not_redirect(): void
    {
        $response = $this->post('/api/payments/wayforpay/callback', []);

        $response
            ->assertStatus(422)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_callback_with_invalid_signature_returns_json_422(): void
    {
        config()->set('payments.wayforpay.merchant_secret', 'test-secret');

        $response = $this->postJson('/api/payments/wayforpay/callback', [
            'merchantAccount' => 'poof_merchant',
            'orderReference' => '999',
            'amount' => '100',
            'currency' => 'UAH',
            'authCode' => '123456',
            'cardPan' => '411111******1111',
            'transactionStatus' => 'Approved',
            'reasonCode' => '1100',
            'merchantSignature' => 'invalid-signature',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_callback_with_unknown_order_reference_returns_json_404(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);

        $payload = [
            'merchantAccount' => 'poof_merchant',
            'orderReference' => '999999',
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
            ->assertStatus(404)
            ->assertJsonPath('status', 'error');
    }

    public function test_callback_with_refunded_status_does_not_mark_order_as_paid(): void
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
            'address_text' => 'вул. Refunded, 1',
            'price' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Refunded');

        $this->postJson('/api/payments/wayforpay/callback', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'accept');

        $this->assertSame(Order::PAY_PENDING, $order->fresh()->payment_status);
    }

    public function test_callback_duplicate_approved_is_idempotent(): void
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
            'address_text' => 'вул. Duplicate, 1',
            'price' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');

        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertOk();
        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertOk();

        $this->assertSame(Order::PAY_PAID, $order->fresh()->payment_status);
    }

    public function test_wayforpay_return_without_session_is_logged_as_cross_site_reentry_path(): void
    {
        Log::spy();

        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $response = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => '12345',
        ])->assertStatus(302);

        $this->assertStringStartsWith(
            '/payments/wayforpay/return/finalize?',
            (string) $response->headers->get('Location')
        );

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'WayForPay return endpoint visited.'
                && ($context['event'] ?? null) === 'wayforpay_return_visited'
                && ($context['order_reference'] ?? null) === '12345';
        });
    }

    public function test_session_loss_after_return_redirects_to_login_and_then_back_to_order_after_login(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'password' => bcrypt('top-secret-pass'),
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Recovery, 1',
            'price' => 100,
        ]);

        $next = '/client/orders?payment=success&source=wayforpay_return&order='.$order->id;

        $returnResponse = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->assertStatus(302);

        $finalizeUrl = (string) $returnResponse->headers->get('Location');
        $this->assertStringStartsWith('/payments/wayforpay/return/finalize?', $finalizeUrl);

        $this->get($finalizeUrl)
            ->assertRedirect('/login?next='.urlencode($next).'&source=wayforpay_return')
            ->assertCookie(WayForPayReturnController::LOGIN_FALLBACK_NEXT_COOKIE, $next);

        $this->post('/login', [
            'login' => $client->email,
            'password' => 'top-secret-pass',
        ])->assertRedirect($next);
    }

    public function test_finalize_uses_web_guard_even_when_default_guard_is_not_web(): void
    {
        config()->set('auth.defaults.guard', 'api');
        config()->set('payments.wayforpay.approved_url', '/client/orders');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Guard, 1',
            'price' => 100,
        ]);

        $returnResponse = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->assertStatus(302);

        $finalizeUrl = (string) $returnResponse->headers->get('Location');
        $this->assertStringStartsWith('/payments/wayforpay/return/finalize?', $finalizeUrl);

        $this->actingAs($client, 'web')
            ->get($finalizeUrl)
            ->assertRedirect('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);
    }

    public function test_session_continuity_is_logged_and_preserved_from_payment_start_to_finalize(): void
    {
        Log::spy();
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.default_provider', 'wayforpay');
        config()->set('payments.wayforpay.enabled', true);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Baseline, 1',
            'price' => 100,
        ]);

        $this->actingAs($client, 'web')
            ->post(route('client.payments.start', $order))
            ->assertOk();

        $returnResponse = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->assertStatus(302);

        $finalizeUrl = (string) $returnResponse->headers->get('Location');

        $this->get($finalizeUrl)
            ->assertRedirect('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'WayForPay return finalize resolved with active session.'
                && ($context['event'] ?? null) === 'wayforpay_return_finalize_authenticated'
                && ($context['session_baseline_available'] ?? false) === true
                && ($context['session_id_changed_since_pre_payment'] ?? null) === false
                && ($context['web_guard_authenticated'] ?? false) === true;
        });
    }

    /**
     * @return array<string, string>
     */
    private function buildSignedPayload(string $orderReference, string $secret, string $transactionStatus): array
    {
        $payload = [
            'merchantAccount' => 'poof_merchant',
            'orderReference' => $orderReference,
            'amount' => '100',
            'currency' => 'UAH',
            'authCode' => '123456',
            'cardPan' => '411111******1111',
            'transactionStatus' => $transactionStatus,
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

        return $payload;
    }
}
