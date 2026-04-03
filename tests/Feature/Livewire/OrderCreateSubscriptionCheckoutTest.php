<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\OrderCreate;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateSubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_modal_shows_only_active_subscription_plans_in_sort_order_with_required_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        SubscriptionPlan::query()->where('slug', 'daily')->update(['is_active' => false]);

        Livewire::test(OrderCreate::class)
            ->call('openSubscriptionModal')
            ->assertSee('1 раз в 3 дні')
            ->assertSee('1 раз в 2 дні')
            ->assertDontSee('Щодня')
            ->assertSee('грн / міс')
            ->assertSee('виносів на місяць')
            ->assertSee('за винос')
            ->assertSee('Економія')
            ->assertSee('До 3 пакетів (18 кг) за один винос');
    }

    public function test_selecting_subscription_plan_sets_total_and_bag_changes_do_not_affect_subscription_total(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = SubscriptionPlan::query()->where('slug', 'every-3-days')->firstOrFail();

        Livewire::test(OrderCreate::class)
            ->call('selectSubscriptionPlan', $plan->id)
            ->assertSet('price', 400)
            ->call('selectBags', 3)
            ->assertSet('bags_count', 3)
            ->assertSet('price', 400)
            ->assertSee('Підписка: фінальна місячна ціна вже врахована у «До оплати».');
    }
}
