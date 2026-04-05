<?php

namespace Tests\Feature\Courier;

use App\Listeners\ResetCourierSessionOnLogin;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierPresenceDispatchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_reset_then_go_online_then_dispatch_creates_pending_offer(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        (new ResetCourierSessionOnLogin())->handle(new Login('web', $courier, false));

        $courier->refresh();
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_online);

        $courier->goOnline();
        $courier->refresh();

        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_online);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'price' => 250,
            'address_text' => 'вул. Поштова, 1',
            'lat' => 50.451,
            'lng' => 30.524,
        ]);

        app(OfferDispatcher::class)->dispatchSearchingOrders(10);

        $offer = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->first();

        $this->assertNotNull($offer);
        $this->assertNotNull($offer->expires_at);
    }
}
