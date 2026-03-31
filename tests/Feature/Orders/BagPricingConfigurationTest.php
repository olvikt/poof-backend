<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Livewire\Client\OrderCreate;
use App\Models\BagPricing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BagPricingConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_for_new_order_is_calculated_from_database_tariff(): void
    {
        BagPricing::query()->where('bags_count', 3)->update(['price' => 88]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->call('selectBags', 3)
            ->assertSet('bags_count', 3)
            ->assertSet('price', 88);
    }

    public function test_tariff_change_affects_new_orders_and_does_not_reprice_existing_orders(): void
    {
        $oldOrder = Order::createForTesting([
            'client_id' => User::factory()->create()->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'bags_count' => 3,
            'price' => 70,
            'address_text' => 'вул. Стара, 1',
            'scheduled_date' => now()->toDateString(),
            'scheduled_time_from' => '10:00',
            'scheduled_time_to' => '12:00',
        ]);

        BagPricing::query()->where('bags_count', 3)->update(['price' => 95]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->call('selectBags', 3)
            ->assertSet('price', 95);

        $this->assertSame(70, $oldOrder->fresh()->price);
    }

    public function test_inactive_tariff_is_not_shown_for_client_order_create_flow(): void
    {
        BagPricing::query()->where('bags_count', 2)->update(['is_active' => false]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(OrderCreate::class);

        $pricing = $component->get('bagPricingOptions');

        $this->assertArrayNotHasKey(2, $pricing);
    }

    public function test_duplicate_bags_count_is_forbidden(): void
    {
        $this->expectException(QueryException::class);

        BagPricing::query()->create([
            'bags_count' => 1,
            'price' => 100,
            'is_active' => true,
            'sort_order' => 100,
        ]);
    }
}
