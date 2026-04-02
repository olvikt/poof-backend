<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\OrdersList;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientOrdersPaymentUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_payment_context_renders_modal_with_correct_order_number_and_can_be_dismissed(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Модальна, 12',
            'price' => 199,
        ]);

        $this->actingAs($client, 'web');

        Livewire::withQueryParams([
            'payment' => 'success',
            'order' => (string) $order->id,
        ])->test(OrdersList::class)
            ->assertSee('Оплату успішно підтверджено')
            ->assertSee('Замовлення #'.$order->id.' успішно оплачено.')
            ->call('dismissPaymentSuccessModal')
            ->assertDontSee('Замовлення #'.$order->id.' успішно оплачено.');
    }

    public function test_order_card_shows_order_number_next_to_payment_status_for_paid_and_pending_orders(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $paidOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Оплачена, 1',
            'price' => 210,
        ]);

        $pendingOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Очікування, 2',
            'price' => 320,
        ]);

        $this->actingAs($client, 'web');

        Livewire::test(OrdersList::class)
            ->assertSee('Замовлення #'.$paidOrder->id.' оплачено!')
            ->assertSee('Замовлення #'.$pendingOrder->id.' · Очікує оплату');
    }

    public function test_failed_payment_context_still_renders_failed_notification_and_does_not_render_success_modal(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Невдала, 5',
            'price' => 99,
        ]);

        $response = $this->actingAs($client, 'web')
            ->get('/client/orders?payment=failed&source=wayforpay_return&order=123');

        $response->assertOk()
            ->assertSee('Платіж не був підтверджений. Спробуйте ще раз.', false)
            ->assertDontSee('Оплату успішно підтверджено', false);
    }
}
