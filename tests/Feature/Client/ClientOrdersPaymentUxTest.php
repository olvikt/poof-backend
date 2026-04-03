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

    public function test_client_can_cancel_own_order_and_it_moves_from_active_to_history_with_cancelled_status(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Скасування, 1',
            'price' => 155,
        ]);

        $this->actingAs($client, 'web');

        Livewire::test(OrdersList::class)
            ->assertSee('Скасувати')
            ->call('cancelOrder', $order->id)
            ->assertSee("Замовлення #{$order->id} скасовано.")
            ->assertDontSee('вул. Скасування, 1')
            ->call('switchTab', 'history')
            ->assertSee('вул. Скасування, 1')
            ->assertSee('Скасовано');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CANCELLED,
        ]);
    }

    public function test_client_cannot_cancel_foreign_order(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $otherClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $foreignOrder = Order::createForTesting([
            'client_id' => $otherClient->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Чужа, 3',
            'price' => 188,
        ]);

        $this->actingAs($client, 'web');

        Livewire::test(OrdersList::class)
            ->call('cancelOrder', $foreignOrder->id)
            ->assertSee('Неможливо скасувати це замовлення.');

        $this->assertDatabaseHas('orders', [
            'id' => $foreignOrder->id,
            'status' => Order::STATUS_NEW,
        ]);
    }

    public function test_order_in_non_cancellable_status_is_not_cancelled_and_user_gets_message(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Виконання, 10',
            'price' => 260,
        ]);

        $this->actingAs($client, 'web');

        Livewire::test(OrdersList::class)
            ->call('cancelOrder', $order->id)
            ->assertSee('Це замовлення вже не можна скасувати.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_IN_PROGRESS,
        ]);
    }
}
