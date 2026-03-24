<?php

namespace Tests\Unit\Orders;

use App\Actions\Orders\Create\CreateCanonicalOrderAction;
use App\Actions\Orders\Create\CreateLegacyWebOrderAction;
use App\DTO\Orders\CanonicalOrderCreatePayload;
use App\DTO\Orders\LegacyWebOrderCreatePayload;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrderPayloadBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_payload_boundary_ignores_legacy_fields_when_creating_api_order(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'address_text' => 'вул. API, 1',
            'lat' => 48.45,
            'lng' => 35.03,
            'is_default' => true,
        ]);

        $payload = CanonicalOrderCreatePayload::fromValidated([
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 2,
            'total_weight_kg' => 3.5,
            'scheduled_date' => '2026-03-10',
            'time_from' => '10:00',
            'time_to' => '12:00',
            'comment' => 'API create',

            // legacy noise that should never leak into canonical create contract
            'scheduled_time_from' => '09:00',
            'handover_type' => Order::HANDOVER_HAND,
            'order_type' => 'subscription',
        ]);

        $order = app(CreateCanonicalOrderAction::class)->handle($client, $payload, $address);

        $this->assertSame('one_time', $order->type);
        $this->assertSame('10:00', $order->time_from);
        $this->assertNull($order->scheduled_time_from);
        $this->assertSame(Order::HANDOVER_DOOR, $order->handover_type);
        $this->assertSame(Order::TYPE_ONE_TIME, $order->order_type);
    }

    public function test_legacy_web_payload_boundary_keeps_legacy_fields_without_requiring_canonical_aliases(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = app(CreateLegacyWebOrderAction::class)->handle(
            clientId: (int) $client->id,
            payload: LegacyWebOrderCreatePayload::fromArray([
                'address_id' => null,
                'address_text' => 'вул. Legacy, 2',
                'lat' => 49.84,
                'lng' => 24.03,
                'entrance' => '2',
                'floor' => '5',
                'apartment' => '51',
                'intercom' => '123',
                'comment' => 'legacy submit',
                'scheduled_date' => '2026-03-11',
                'scheduled_time_from' => '12:00',
                'scheduled_time_to' => '14:00',
                'handover_type' => Order::HANDOVER_HAND,
                'bags_count' => 3,
                'price' => 150,
                'promo_code' => 'PROMO10',
                'is_trial' => false,
                'trial_days' => 3,
            ]),
        );

        $this->assertSame(Order::TYPE_ONE_TIME, $order->order_type);
        $this->assertSame('12:00', $order->scheduled_time_from);
        $this->assertNull($order->time_from);
        $this->assertSame(Order::HANDOVER_HAND, $order->handover_type);
        $this->assertSame('PROMO10', $order->promo_code);
    }
}
