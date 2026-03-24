<?php

namespace Tests\Unit\Orders;

use App\Actions\Orders\Create\CreateCanonicalOrderAction;
use App\Actions\Orders\Create\CreateLegacyWebOrderAction;
use App\DTO\Orders\CanonicalOrderCreatePayload;
use App\DTO\Orders\LegacyWebOrderCreatePayload;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrderPayloadBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_payload_boundary_sets_canonical_defaults_and_does_not_expose_legacy_columns(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'address_text' => 'вул. Canonical, 10',
            'lat' => 50.45,
            'lng' => 30.52,
            'is_default' => true,
        ]);

        $payload = CanonicalOrderCreatePayload::fromValidated([
            'type' => Order::TYPE_SUBSCRIPTION,
            'service' => 'trash_removal',
            'bags_count' => 3,
            'total_weight_kg' => 9.4,
            'scheduled_date' => '2026-03-12',
            'time_from' => '14:00',
            'time_to' => '16:00',
            'comment' => 'canonical defaults',
        ]);

        $order = app(CreateCanonicalOrderAction::class)->handle($client, $payload, $address);

        $this->assertSame(Order::STATUS_NEW, $order->status);
        $this->assertSame(Order::PAY_PENDING, $order->payment_status);
        $this->assertSame('UAH', $order->currency);
        $this->assertSame(150, $order->price);

        $this->assertSame('14:00', $order->time_from);
        $this->assertSame('16:00', $order->time_to);
        $this->assertNull($order->scheduled_time_from);
        $this->assertNull($order->scheduled_time_to);
        $this->assertSame(Order::TYPE_ONE_TIME, $order->order_type);
    }

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

    public function test_create_contracts_match_explicit_runtime_contract_boundaries(): void
    {
        $canonicalPayload = CanonicalOrderCreatePayload::fromValidated([
            'type' => Order::TYPE_ONE_TIME,
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.5,
            'scheduled_date' => '2026-03-12',
            'time_from' => '08:00',
            'time_to' => '10:00',
            'comment' => 'canonical contract keys',
            // should be ignored by canonical contract
            'scheduled_time_from' => '10:00',
            'order_type' => 'subscription',
        ]);

        $canonicalAddress = ClientAddress::query()->create([
            'user_id' => User::factory()->create([
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
            ])->id,
            'address_text' => 'вул. Contract, 1',
            'lat' => 48.45,
            'lng' => 35.03,
            'is_default' => true,
        ]);

        $canonicalKeys = array_keys($canonicalPayload->toOrderAttributes(1, $canonicalAddress, 100));
        $legacyKeys = array_keys(LegacyWebOrderCreatePayload::fromArray([
            'address_id' => null,
            'address_text' => 'вул. Contract, 2',
            'lat' => 49.84,
            'lng' => 24.03,
            'entrance' => '1',
            'floor' => '2',
            'apartment' => '3',
            'intercom' => '4',
            'comment' => 'legacy contract keys',
            'scheduled_date' => '2026-03-13',
            'scheduled_time_from' => '12:00',
            'scheduled_time_to' => '14:00',
            'handover_type' => Order::HANDOVER_DOOR,
            'bags_count' => 2,
            'price' => 120,
            'promo_code' => null,
            'is_trial' => false,
            'trial_days' => 1,
        ])->toOrderAttributes(1));

        $this->assertEqualsCanonicalizing(Order::CANONICAL_CREATE_COLUMNS, $canonicalKeys);
        $this->assertEqualsCanonicalizing(Order::LEGACY_WEB_CREATE_COLUMNS, $legacyKeys);

        $this->assertContains('time_from', $canonicalKeys);
        $this->assertNotContains('scheduled_time_from', $canonicalKeys);

        $this->assertContains('scheduled_time_from', $legacyKeys);
        $this->assertNotContains('time_from', $legacyKeys);
    }

    public function test_direct_order_create_is_blocked_by_runtime_mass_assignment_policy(): void
    {
        $this->expectException(MassAssignmentException::class);

        Order::query()->create([
            'client_id' => 1,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'price' => 500,
        ]);
    }

    public function test_canonical_runtime_contract_rejects_unapproved_columns(): void
    {
        $this->expectException(MassAssignmentException::class);

        Order::createFromCanonicalContract([
            'client_id' => 1,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'type' => Order::TYPE_ONE_TIME,
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.0,
            'price' => 100,
            'currency' => 'UAH',
            'address_id' => null,
            'address_text' => 'test',
            'lat' => 50.0,
            'lng' => 30.0,
            'scheduled_date' => '2026-03-12',
            'time_from' => '08:00',
            'time_to' => '10:00',
            'comment' => null,
            'courier_id' => 999, // explicitly forbidden for create contract
        ]);
    }

    public function test_testing_create_contract_rejects_unknown_columns_like_legacy_address_alias(): void
    {
        $this->expectException(MassAssignmentException::class);

        Order::createForTesting([
            'client_id' => 1,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Валідна, 1',
            'address' => 'вул. Невалідна, 1',
            'price' => 100,
        ]);
    }
}
