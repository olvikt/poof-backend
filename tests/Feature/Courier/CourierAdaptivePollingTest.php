<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OfferCard;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierAdaptivePollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_card_polling_is_fast_only_for_online_non_busy_courier(): void
    {
        $courier = $this->createCourier(online: true);

        $this->actingAs($courier, 'web');

        Livewire::test(OfferCard::class)
            ->assertSee('wire:poll.2s="loadOffer"', false);

        Order::createForTesting([
            'client_id' => User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true])->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Active order',
            'price' => 100,
            'accepted_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        Livewire::test(OfferCard::class)
            ->assertSee('wire:poll.12s="loadOffer"', false);
    }

    public function test_available_orders_polling_adapts_by_online_and_active_order_state(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier(online: true);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSee('wire:poll.6s', false);

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Busy order',
            'price' => 100,
            'accepted_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        Livewire::test(AvailableOrders::class)
            ->assertSee('wire:poll.20s', false);
    }

    public function test_my_orders_polling_is_slower_when_idle(): void
    {
        $courier = $this->createCourier(online: true);
        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->assertSee('wire:poll.20s', false);

        Order::createForTesting([
            'client_id' => User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true])->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Active order',
            'price' => 100,
            'accepted_at' => now()->subMinute(),
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        Livewire::test(MyOrders::class)
            ->assertSee('wire:poll.6s', false);
    }

    private function createCourier(bool $online): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => $online,
            'is_busy' => false,
            'session_state' => $online ? User::SESSION_READY : User::SESSION_OFFLINE,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => $online ? Courier::STATUS_ONLINE : Courier::STATUS_OFFLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }
}
