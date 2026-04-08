<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Courier\Payout\CreateCourierWithdrawalRequestAction;
use App\Actions\Courier\Payout\SaveCourierPayoutRequisitesAction;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\CourierPayoutRequisite;
use App\Models\CourierWithdrawalRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CourierWalletPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_can_access_wallet_page(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->get(route('courier.wallet'))
            ->assertOk()
            ->assertSee('Courier wallet')
            ->assertSee('Запросити вивід')
            ->assertSee('payout requisites');
    }

    public function test_client_cannot_access_wallet_page(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $this->actingAs($client, 'web')
            ->get(route('courier.wallet'))
            ->assertForbidden();
    }

    public function test_wallet_summary_uses_canonical_earnings_source_and_history_contract(): void
    {
        $courier = $this->createCourier();
        $this->createSettledLedgerEntry($courier, 500, 50, 450);

        $response = $this->actingAs($courier, 'web')->get(route('courier.wallet'));

        $response->assertOk();
        $response->assertSee('500,00 ₴', false);
        $response->assertSee('450,00 ₴', false);
        $response->assertSee('Completed orders');
        $response->assertSee('#', false);
    }

    public function test_minimum_withdrawal_and_insufficient_balance_are_blocked_and_valid_request_persists(): void
    {
        config()->set('courier_payout.minimum_withdrawal_amount', 500);

        $courier = $this->createCourier();
        $this->createSettledLedgerEntry($courier, 700, 100, 600);

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.withdrawals.request'), ['amount' => 300])
            ->assertSessionHasErrors('amount');

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.withdrawals.request'), ['amount' => 650])
            ->assertSessionHasErrors('amount');

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.withdrawals.request'), ['amount' => 550, 'notes' => 'wallet flow'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('courier_withdrawal_requests', [
            'courier_id' => $courier->id,
            'amount' => 550,
            'status' => CourierWithdrawalRequest::STATUS_REQUESTED,
        ]);
    }

    public function test_blocking_pending_request_behavior_works(): void
    {
        config()->set('courier_payout.minimum_withdrawal_amount', 100);
        $courier = $this->createCourier();
        $this->createSettledLedgerEntry($courier, 1000, 100, 900);

        CourierWithdrawalRequest::query()->create([
            'courier_id' => $courier->id,
            'amount' => 400,
            'status' => CourierWithdrawalRequest::STATUS_REQUESTED,
        ]);

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.withdrawals.request'), ['amount' => 200])
            ->assertSessionHasErrors('amount');
    }

    public function test_requisites_save_update_flow_and_safe_rendering_work(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.requisites.save'), [
                'card_holder_name' => 'Ivan Courier',
                'card_number' => '4444 3333 2222 1111',
                'bank_name' => 'Mono',
                'notes' => 'Main card',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('courier_payout_requisites', [
            'courier_id' => $courier->id,
            'masked_card_number' => '**** **** **** 1111',
            'bank_name' => 'Mono',
        ]);

        $response = $this->actingAs($courier, 'web')->get(route('courier.wallet'));
        $response->assertSee('**** **** **** 1111');
        $response->assertDontSee('4444 3333 2222 1111');
    }

    public function test_wallet_write_side_delegates_to_explicit_actions(): void
    {
        $courier = $this->createCourier();

        $withdrawalAction = Mockery::mock(CreateCourierWithdrawalRequestAction::class);
        $withdrawalAction->shouldReceive('execute')->once()->andReturn(
            CourierWithdrawalRequest::query()->create([
                'courier_id' => $courier->id,
                'amount' => 100,
                'status' => CourierWithdrawalRequest::STATUS_REQUESTED,
            ])
        );
        $this->app->instance(CreateCourierWithdrawalRequestAction::class, $withdrawalAction);

        $requisitesAction = Mockery::mock(SaveCourierPayoutRequisitesAction::class);
        $requisitesAction->shouldReceive('execute')->once()->andReturn(
            CourierPayoutRequisite::query()->create([
                'courier_id' => $courier->id,
                'card_holder_name' => 'Name',
                'card_number_encrypted' => '4242424242424242',
                'masked_card_number' => '**** **** **** 4242',
            ])
        );
        $this->app->instance(SaveCourierPayoutRequisitesAction::class, $requisitesAction);

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.withdrawals.request'), ['amount' => 100])
            ->assertSessionHasNoErrors();

        $this->actingAs($courier, 'web')
            ->post(route('courier.wallet.requisites.save'), [
                'card_holder_name' => 'Name',
                'card_number' => '4242 4242 4242 4242',
            ])
            ->assertSessionHasNoErrors();
    }

    private function createCourier(array $overrides = []): User
    {
        $courier = User::factory()->create(array_merge([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'phone' => '+380500000001',
            'residence_address' => 'м. Київ, вул. Базова, 1',
        ], $overrides));

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
            'rating' => 4.8,
            'completed_orders' => 0,
            'transport_type' => 'bike',
            'is_verified' => false,
        ]);

        return $courier;
    }

    private function createSettledLedgerEntry(User $courier, int $gross, int $commission, int $net): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => $gross,
            'address_text' => 'test',
        ]);

        CourierEarning::query()->create([
            'courier_id' => $courier->id,
            'order_id' => $order->id,
            'gross_amount' => $gross,
            'commission_rate_percent' => '20.00',
            'commission_amount' => $commission,
            'net_amount' => $net,
            'bonuses_amount' => 0,
            'penalties_amount' => 0,
            'adjustments_amount' => 0,
            'earning_status' => CourierEarning::STATUS_SETTLED,
            'settled_at' => now(),
        ]);
    }
}
