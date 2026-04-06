<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Orders\Lifecycle\CompleteOrderByCourierAction;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\CourierEarningSetting;
use App\Models\Order;
use App\Models\User;
use App\Services\Courier\Earnings\CourierBalanceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierEarningsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_and_paid_order_creates_settled_courier_earning_with_commission_formula(): void
    {
        CourierEarningSetting::query()->update(['global_commission_rate_percent' => 15.50]);

        [$courier, $order] = $this->createAcceptedPaidOrder();

        $order->forceFill([
            'status' => Order::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ])->save();

        app(CompleteOrderByCourierAction::class)->handle($order->fresh(), $courier);

        $earning = CourierEarning::query()->where('order_id', $order->id)->first();

        $this->assertNotNull($earning);
        $this->assertSame(200, $earning->gross_amount);
        $this->assertSame('15.50', $earning->commission_rate_percent);
        $this->assertSame(31, $earning->commission_amount);
        $this->assertSame(169, $earning->net_amount);
        $this->assertSame(CourierEarning::STATUS_SETTLED, $earning->earning_status);
    }

    public function test_same_order_cannot_produce_duplicate_settlement_entry(): void
    {
        [$courier, $order] = $this->createAcceptedPaidOrder();

        $order->forceFill([
            'status' => Order::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ])->save();

        $action = app(CompleteOrderByCourierAction::class);

        $this->assertTrue($action->handle($order->fresh(), $courier));
        $this->assertFalse($action->handle($order->fresh(), $courier));

        $this->assertSame(1, CourierEarning::query()->where('order_id', $order->id)->count());
    }

    public function test_changing_commission_affects_only_future_settlements(): void
    {
        [$courier, $orderOne] = $this->createAcceptedPaidOrder(price: 100);
        $orderOne->forceFill(['status' => Order::STATUS_IN_PROGRESS, 'started_at' => now()])->save();

        CourierEarningSetting::query()->update(['global_commission_rate_percent' => 10.00]);
        app(CompleteOrderByCourierAction::class)->handle($orderOne->fresh(), $courier);

        [$sameCourier, $orderTwo] = $this->createAcceptedPaidOrder(courier: $courier, price: 100);
        $orderTwo->forceFill(['status' => Order::STATUS_IN_PROGRESS, 'started_at' => now()])->save();

        CourierEarningSetting::query()->update(['global_commission_rate_percent' => 25.00]);
        app(CompleteOrderByCourierAction::class)->handle($orderTwo->fresh(), $sameCourier);

        $first = CourierEarning::query()->where('order_id', $orderOne->id)->firstOrFail();
        $second = CourierEarning::query()->where('order_id', $orderTwo->id)->firstOrFail();

        $this->assertSame('10.00', $first->commission_rate_percent);
        $this->assertSame('25.00', $second->commission_rate_percent);
    }

    public function test_non_completed_or_unpaid_order_does_not_enter_courier_balance(): void
    {
        [$courier, $order] = $this->createAcceptedPaidOrder();

        $order->forceFill([
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PENDING,
        ])->save();

        app(CompleteOrderByCourierAction::class)->handle($order->fresh(), $courier);

        $summary = app(CourierBalanceSummaryService::class)->forCourier($courier);

        $this->assertSame(0, $summary['completed_orders_count']);
        $this->assertSame('0,00 ₴', $summary['balance_formatted']);
    }

    public function test_header_online_toggle_uses_balance_read_model_summary(): void
    {
        [$courier, $order] = $this->createAcceptedPaidOrder(price: 300);
        $order->forceFill(['status' => Order::STATUS_IN_PROGRESS, 'started_at' => now()])->save();
        CourierEarningSetting::query()->update(['global_commission_rate_percent' => 20.00]);
        app(CompleteOrderByCourierAction::class)->handle($order->fresh(), $courier);

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSee('Баланс:', false)
            ->assertSee('240,00 ₴', false)
            ->assertSet('balanceSummary.completed_orders_count', 1)
            ->assertSet('balanceSummary.courier_net_balance', 240);
    }

    public function test_commission_setting_model_requires_rate_between_zero_and_hundred(): void
    {
        $validator = validator(
            ['global_commission_rate_percent' => 120],
            ['global_commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:100']]
        );

        $this->assertTrue($validator->fails());

        $this->assertDatabaseHas('courier_earning_settings', [
            'id' => CourierEarningSetting::current()->id,
        ]);
    }

    /** @return array{0:User,1:Order} */
    private function createAcceptedPaidOrder(?User $courier = null, int $price = 200): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier ??= User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_ASSIGNED,
        ]);

        Courier::query()->firstOrCreate(
            ['user_id' => $courier->id],
            ['status' => Courier::STATUS_ASSIGNED]
        );

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'accepted_at' => now(),
            'address_text' => 'вул. Балансна, 10',
            'price' => $price,
        ]);

        return [$courier, $order];
    }
}
