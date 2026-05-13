<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\OfferCard;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfferCardCountdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_countdown_contract_is_rendered_for_45_seconds_and_urgency_states(): void
    {
        $courier = $this->createCourier();
        $order = $this->createOrder();
        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 45);

        $this->actingAs($courier, 'web');

        Livewire::test(OfferCard::class)
            ->set('offer', $offer->fresh())
            ->assertSee('totalSeconds: 45', false)
            ->assertSee('leftSeconds &lt; 15', false)
            ->assertSee('leftSeconds &lt; 5', false)
            ->assertSee(':disabled="isExpired"', false);
    }

    public function test_expired_offer_cannot_be_accepted_and_load_offer_clears_card(): void
    {
        $courier = $this->createCourier();
        $order = $this->createOrder();

        $offer = OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(OfferCard::class)
            ->set('offer', $offer)
            ->call('accept')
            ->assertDispatched('notify', type: 'error', message: 'Не вдалося прийняти або офер прострочений')
            ->assertSet('offer', null);
    }

    public function test_offer_card_closes_when_offer_selected_elsewhere_or_expired(): void
    {
        $courier = $this->createCourier();
        $order = $this->createOrder();
        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 45);

        $this->actingAs($courier, 'web');

        $component = Livewire::test(OfferCard::class)
            ->set('offer', $offer->fresh());

        $offer->update(['status' => OrderOffer::STATUS_REJECTED, 'rejected_reason' => 'selected_elsewhere']);

        $component->call('loadOffer')
            ->assertSet('offer', null)
            ->assertSet('lastClosedReason', 'selected_elsewhere')
            ->assertDispatched('notify', type: 'info', message: 'Офер обрано іншим курʼєром');
    }

    private function createCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'session_state' => User::SESSION_READY,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }

    private function createOrder(): Order
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        return Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Таймерна, 1',
            'price' => 220,
        ]);
    }
}
