<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPaymentPublicIdSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_owner_route_works_with_public_id(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting(['client_id' => $client->id, 'status' => Order::STATUS_NEW, 'payment_status' => Order::PAY_PENDING]);

        $this->actingAs($client, 'web')
            ->get(route('client.payments.show', ['order' => $order->public_id]))
            ->assertOk();
    }

    public function test_user_cannot_access_another_user_order_by_uuid(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $attacker = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting(['client_id' => $owner->id, 'status' => Order::STATUS_NEW, 'payment_status' => Order::PAY_PENDING]);

        $this->actingAs($attacker, 'web')
            ->get(route('client.payments.show', ['order' => $order->public_id]))
            ->assertForbidden();
    }

    public function test_raw_id_link_does_not_disclose_foreign_order(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $attacker = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting(['client_id' => $owner->id, 'status' => Order::STATUS_NEW, 'payment_status' => Order::PAY_PENDING]);

        $this->actingAs($attacker, 'web')
            ->get('/client/payments/'.$order->id)
            ->assertNotFound();
    }

    public function test_owner_legacy_raw_id_get_redirects_to_public_id_url(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting(['client_id' => $owner->id, 'status' => Order::STATUS_NEW, 'payment_status' => Order::PAY_PENDING]);

        $response = $this->actingAs($owner, 'web')
            ->get('/client/payments/'.$order->id);

        $response->assertRedirect(route('client.payments.show', ['order' => $order->public_id]));
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/client/payments/'.$order->public_id, $location);
        $this->assertStringNotContainsString('/client/payments/'.$order->id, $location);
    }

    public function test_owner_legacy_raw_id_start_post_redirects_to_public_id_payment_show(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = Order::createForTesting(['client_id' => $owner->id, 'status' => Order::STATUS_NEW, 'payment_status' => Order::PAY_PENDING]);

        $response = $this->actingAs($owner, 'web')
            ->post('/client/payments/'.$order->id.'/start');

        $response->assertRedirect(route('client.payments.show', ['order' => $order->public_id]));
    }
}
