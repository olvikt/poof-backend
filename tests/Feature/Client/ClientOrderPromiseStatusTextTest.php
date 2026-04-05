<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Livewire\Client\OrdersList;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ClientOrderPromiseStatusTextTest extends TestCase
{
    public function test_client_orders_list_reflects_expired_reason_text(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'valid_until_at' => now()->subMinutes(5),
            'expired_at' => now()->subMinutes(4),
            'expired_reason' => Order::EXPIRED_REASON_COURIER_NOT_FOUND_WITHIN_WINDOW,
            'address_text' => 'вул. Клієнтська, 3',
            'price' => 111,
        ]);

        Livewire::actingAs($client)
            ->test(OrdersList::class)
            ->call('switchTab', 'history')
            ->assertSee('Замовлення скасовано, бо не вдалося знайти курʼєра вчасно')
            ->assertSee('Не вдалося знайти курʼєра у бажаний інтервал.');
    }
}
