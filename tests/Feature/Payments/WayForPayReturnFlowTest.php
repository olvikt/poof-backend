<?php

namespace Tests\Feature\Payments;

use App\Http\Controllers\Client\Payments\WayForPayReturnController;
use App\Events\OrderCreated;
use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\WayForPay\WayForPaySignature;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Event;
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
        $this->assertStringContainsString('/payments/wayforpay/return/finalize?', parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY));
        $this->assertStringContainsString('next=%2Fclient%2Forders%3Fpayment%3Dsuccess%26source%3Dwayforpay_return%26order%3D42', $location);
        $this->assertFalse($this->responseSetsCookie($response, config('session.cookie')));
    }

    public function test_stateless_post_return_without_session_cookie_does_not_500_and_redirects_to_finalize_without_setting_cookies(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $response = $this->withCookie(config('session.cookie'), '')
            ->post('/payments/wayforpay/return', [
                'transactionStatus' => 'Approved',
                'orderReference' => '54',
            ]);

        $response->assertStatus(302);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertStringStartsWith(
            '/payments/wayforpay/return/finalize?',
            (string) $response->headers->get('Location')
        );
        $this->assertFalse($this->responseSetsCookie($response, config('session.cookie')));
        $this->assertFalse($this->responseSetsCookie($response, 'XSRF-TOKEN'));
    }

    public function test_wayforpay_return_route_excludes_session_and_csrf_middlewares_for_stateless_compatibility(): void
    {
        $route = app('router')->getRoutes()->getByName('payments.wayforpay.return');

        $this->assertNotNull($route);

        $routeMiddleware = $route->gatherMiddleware();

        $this->assertNotContains(StartSession::class, $routeMiddleware);
        $this->assertNotContains(VerifyCsrfToken::class, $routeMiddleware);
    }

    public function test_wayforpay_return_accepts_get_and_redirects(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');
        config()->set('payments.wayforpay.declined_url', '/client/orders');

        $response = $this->get('/payments/wayforpay/return?transactionStatus=Declined');

        $response->assertStatus(302);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/payments/wayforpay/return/finalize?', parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY));
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
        $this->assertStringContainsString('/payments/wayforpay/return/finalize?', parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY));
        $this->assertFalse(str_starts_with($location, '/login'));
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

        $this->assertFinalizeRedirectUrl($finalizeUrl);

        $this->actingAs($client)->get($finalizeUrl)
            ->assertRedirectContains('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);

        $this->get('/client/orders?payment=success&source=wayforpay_return&order='.$order->id)
            ->assertOk()
            ->assertSee('Оплату успішно підтверджено', false)
            ->assertSee('#'.$order->id, false);
    }


    public function test_subscription_payment_success_redirects_to_subscriptions_page(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_CHECKOUT,
            'address_text' => 'вул. Subscription Return, 1',
            'price' => 100,
        ]);

        $finalizeUrl = (string) $this->actingAs($client)->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->headers->get('Location');

        $this->actingAs($client)->get($finalizeUrl)
            ->assertRedirect('/client/subscriptions?payment=success&source=wayforpay_return&order='.$order->id);
    }

    public function test_one_time_payment_success_redirects_to_orders_page(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_ONE_TIME,
            'origin' => Order::ORIGIN_CHECKOUT,
            'address_text' => 'вул. One Time Return, 1',
            'price' => 100,
        ]);

        $finalizeUrl = (string) $this->actingAs($client)->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->headers->get('Location');

        $this->actingAs($client)->get($finalizeUrl)
            ->assertRedirectContains('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);
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
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

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
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

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
        $payload['transactionId'] = 'wfp-tx-1';
        $payload['merchantSignature'] = $this->signPayload($payload, $secret);

        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertOk();
        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertOk();

        $this->assertSame(Order::PAY_PAID, $order->fresh()->payment_status);
        $this->assertSame('wfp-tx-1', $order->fresh()->payment_provider_transaction_id);
    }

    public function test_callback_with_valid_signature_but_wrong_amount_is_rejected_and_order_stays_pending(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Amount, 1',
            'price' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $payload['amount'] = '101';
        $payload['merchantSignature'] = $this->signPayload($payload, $secret);

        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertStatus(422);
        $this->assertSame(Order::PAY_PENDING, $order->fresh()->payment_status);
    }

    public function test_callback_with_valid_signature_but_wrong_currency_is_rejected(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Currency, 1',
            'price' => 100,
            'currency' => 'UAH',
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $payload['currency'] = 'USD';
        $payload['merchantSignature'] = $this->signPayload($payload, $secret);

        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertStatus(422);
    }

    public function test_callback_with_valid_signature_but_wrong_merchant_account_is_rejected(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Merchant, 1',
            'price' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $payload['merchantAccount'] = 'other_merchant';
        $payload['merchantSignature'] = $this->signPayload($payload, $secret);

        $this->postJson('/api/payments/wayforpay/callback', $payload)->assertStatus(422);
    }

    public function test_callback_rejects_reused_transaction_id_for_different_order(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $orderA = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Order A, 1',
            'price' => 100,
        ]);
        $orderB = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Order B, 1',
            'price' => 100,
        ]);

        $payloadA = $this->buildSignedPayload((string) $orderA->id, $secret, 'Approved');
        $payloadA['transactionId'] = 'shared-tx-1';
        $payloadA['merchantSignature'] = $this->signPayload($payloadA, $secret);
        $this->postJson('/api/payments/wayforpay/callback', $payloadA)->assertOk();

        $payloadB = $this->buildSignedPayload((string) $orderB->id, $secret, 'Approved');
        $payloadB['transactionId'] = 'shared-tx-1';
        $payloadB['merchantSignature'] = $this->signPayload($payloadB, $secret);

        $this->postJson('/api/payments/wayforpay/callback', $payloadB)
            ->assertStatus(422)
            ->assertJsonPath('message', 'provider_transaction_reused');

        $this->assertSame(Order::PAY_PENDING, $orderB->fresh()->payment_status);
    }

    public function test_callback_rejects_different_transaction_id_for_already_linked_order(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Linked, 1',
            'price' => 100,
        ]);

        $first = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $first['transactionId'] = 'linked-tx-1';
        $first['merchantSignature'] = $this->signPayload($first, $secret);
        $this->postJson('/api/payments/wayforpay/callback', $first)->assertOk();

        $second = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $second['transactionId'] = 'linked-tx-2';
        $second['merchantSignature'] = $this->signPayload($second, $secret);
        $this->postJson('/api/payments/wayforpay/callback', $second)->assertStatus(422);
    }

    public function test_declined_callback_does_not_bind_tx_id_and_later_approved_with_new_tx_is_accepted(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Retry, 1',
            'price' => 100,
        ]);

        $declined = $this->buildSignedPayload((string) $order->id, $secret, 'Declined');
        $declined['transactionId'] = 'declined-tx-1';
        $declined['merchantSignature'] = $this->signPayload($declined, $secret);
        $this->postJson('/api/payments/wayforpay/callback', $declined)->assertOk();

        $order->refresh();
        $this->assertSame(Order::PAY_PENDING, $order->payment_status);
        $this->assertNull($order->payment_provider_transaction_id);

        $approved = $this->buildSignedPayload((string) $order->id, $secret, 'Approved');
        $approved['transactionId'] = 'approved-tx-2';
        $approved['merchantSignature'] = $this->signPayload($approved, $secret);
        $this->postJson('/api/payments/wayforpay/callback', $approved)->assertOk();

        $order->refresh();
        $this->assertSame(Order::PAY_PAID, $order->payment_status);
        $this->assertSame('approved-tx-2', $order->payment_provider_transaction_id);
    }

    public function test_callback_amount_validation_uses_checkout_payload_amount_when_price_differs_from_client_charge_amount(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);
        config()->set('payments.wayforpay.merchant_account', 'poof_merchant');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Amount Source, 1',
            'price' => 100,
            'client_charge_amount' => 80,
        ]);

        $payload = $this->buildSignedPayload((string) $order->id, $secret, 'Approved', '100');
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
        $this->assertFinalizeRedirectUrl($finalizeUrl);

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
        $this->assertFinalizeRedirectUrl($finalizeUrl);

        $this->actingAs($client, 'web')
            ->get($finalizeUrl)
            ->assertRedirectContains('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);
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
            ->post(route('client.payments.start', ['order' => $order->public_id]))
            ->assertOk();

        $returnResponse = $this->post('/payments/wayforpay/return', [
            'transactionStatus' => 'Approved',
            'orderReference' => (string) $order->id,
        ])->assertStatus(302);

        $this->assertFalse($this->responseSetsCookie($returnResponse, config('session.cookie')));

        $finalizeUrl = (string) $returnResponse->headers->get('Location');

        $this->get($finalizeUrl)
            ->assertRedirectContains('/client/orders?payment=success&source=wayforpay_return&order='.$order->id);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'WayForPay return finalize resolved with active session.'
                && ($context['event'] ?? null) === 'wayforpay_return_finalize_authenticated'
                && ($context['session_baseline_available'] ?? false) === true
                && ($context['session_id_changed_since_pre_payment'] ?? null) === false
                && ($context['web_guard_authenticated'] ?? false) === true
                && ($context['response_sets_session_cookie'] ?? true) === false;
        });
    }

    public function test_finalize_fallback_to_login_only_for_real_unauthenticated_session(): void
    {
        config()->set('payments.wayforpay.approved_url', '/client/orders');

        $response = $this->get('/payments/wayforpay/return/finalize?next=%2Fclient%2Forders%3Fpayment%3Dsuccess');

        $response
            ->assertRedirect('/login?next=%2Fclient%2Forders%3Fpayment%3Dsuccess&source=wayforpay_return')
            ->assertCookie(WayForPayReturnController::LOGIN_FALLBACK_NEXT_COOKIE, '/client/orders?payment=success');
    }

    /**
     * @return array<string, string>
     */

    public function test_callback_for_paid_subscription_checkout_repairs_missing_execution_order_and_dispatches_order_created(): void
    {
        $secret = 'test-secret';
        config()->set('payments.wayforpay.merchant_secret', $secret);

        Event::fake([OrderCreated::class]);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $address = ClientAddress::query()->create([
            'client_id' => $client->id,
            'user_id' => $client->id,
            'label' => 'Дім',
            'address_text' => 'вул. Відновлення, 10',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'is_default' => true,
        ]);

        $plan = SubscriptionPlan::factory()->create([
            'is_active' => true,
                    ]);

        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
                        'next_run_at' => now(),
        ]));

        $checkout = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_CHECKOUT,
            'subscription_id' => $subscription->id,
            'address_text' => 'вул. Відновлення, 10',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'price' => 100,
            'client_charge_amount' => 100,
        ]);

        $payload = $this->buildSignedPayload((string) $checkout->id, $secret, 'Approved');

        $this->postJson('/api/payments/wayforpay/callback', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'accept');

        $execution = Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->latest('id')
            ->first();

        $this->assertNotNull($execution);
        $this->assertSame(Order::STATUS_DONE, $checkout->fresh()->status);
        $this->assertSame(Order::PAY_PAID, $execution->payment_status);
        $this->assertSame(Order::STATUS_SEARCHING, $execution->status);

        Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event): bool => $event->order->id === $execution->id);
    }


    private function assertFinalizeRedirectUrl(string $url): void
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        $this->assertSame('/payments/wayforpay/return/finalize', $path);
        $this->assertNotSame('', $query);
    }

    private function buildSignedPayload(string $orderReference, string $secret, string $transactionStatus, string $amount = '100'): array
    {
        $payload = [
            'merchantAccount' => (string) config('payments.wayforpay.merchant_account', 'poof_merchant'),
            'orderReference' => $orderReference,
            'amount' => $amount,
            'currency' => 'UAH',
            'authCode' => '123456',
            'cardPan' => '411111******1111',
            'transactionStatus' => $transactionStatus,
            'reasonCode' => '1100',
        ];

        $payload['merchantSignature'] = $this->signPayload($payload, $secret);

        return $payload;
    }

    private function signPayload(array $payload, string $secret): string
    {
        return app(WayForPaySignature::class)->sign([
            (string) $payload['merchantAccount'],
            (string) $payload['orderReference'],
            (string) $payload['amount'],
            (string) $payload['currency'],
            (string) ($payload['authCode'] ?? ''),
            (string) ($payload['cardPan'] ?? ''),
            (string) $payload['transactionStatus'],
            (string) ($payload['reasonCode'] ?? ''),
        ], $secret);
    }

    private function responseSetsCookie(\Illuminate\Testing\TestResponse $response, string $cookieName): bool
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return true;
            }
        }

        return false;
    }
}
