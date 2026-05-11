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
            ->assertSet('bags_count', 1)
            ->assertSet('price', 400)
            ->assertSee('Підписка: фінальна місячна ціна вже врахована у «До оплати».');
    }

    public function test_regular_mode_renders_clickable_bag_button_and_updates_bags_and_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSeeHtml('wire:click="selectBags(1)"')
            ->assertSeeHtml('type="button"')
            ->call('selectBags', 3)
            ->assertSet('bags_count', 3)
            ->assertSet('price', 209);
    }

    public function test_default_state_has_regular_order_active_and_subscription_inactive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSet('selected_subscription_plan_id', null)
            ->assertSee('Тип замовлення')
            ->assertSee('Разовий винос')
            ->assertSee('Підписка на місяць')
            ->assertDontSee('Оплата лише за цей винос')
            ->assertDontSee('Регулярні виноси вигідніше');
    }



    public function test_order_type_block_is_rendered_above_handover_block(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSeeInOrder(['Тип замовлення', 'Передача']);
    }

    public function test_date_block_is_rendered_before_time_block(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSeeInOrder(['Дата', 'Час', 'Обраний інтервал']);
    }

    public function test_order_type_badges_and_icon_markers_are_visible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSee('Разово')
            ->assertSee('До 20% вигоди')
            ->assertSee('Без контакту')
            ->assertSee('Особисто')
            ->assertSeeHtml('data-e2e="order-type-subscription"')
            ->assertSeeHtml('data-e2e="icon-calendar"')
            ->assertSeeHtml('data-e2e="trial-promo-card"')
            ->assertSeeHtml('data-e2e="icon-gift"')
            ->assertSeeHtml('data-e2e="bag-icons"')
            ->assertDontSee('🚪')
            ->assertDontSee('🤝');
    }

    public function test_subscription_selected_disables_bag_selection_and_removes_active_bag_highlight(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = SubscriptionPlan::query()->where('slug', 'every-3-days')->firstOrFail();

        Livewire::test(OrderCreate::class)
            ->call('selectSubscriptionPlan', $plan->id)
            ->assertSet('selected_subscription_plan_id', $plan->id)
            ->assertSee('Недоступно')
            ->assertSeeHtml('cursor-not-allowed')
            ->assertSeeHtml('wire:click="selectBags(1)"')
            ->assertSeeHtml('disabled');
    }

    public function test_subscription_card_has_active_border_state_when_subscription_selected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = SubscriptionPlan::query()->where('slug', 'every-3-days')->firstOrFail();

        Livewire::test(OrderCreate::class)
            ->call('selectSubscriptionPlan', $plan->id)
            ->assertSeeHtml('border-yellow-400 ring-1 ring-yellow-400/30 bg-neutral-900/90 text-white');
    }

    public function test_switching_back_to_regular_order_reenables_bags_and_recalculates_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = SubscriptionPlan::query()->where('slug', 'every-3-days')->firstOrFail();

        Livewire::test(OrderCreate::class)
            ->call('selectSubscriptionPlan', $plan->id)
            ->assertSet('price', 400)
            ->call('selectRegularOrder')
            ->assertSet('selected_subscription_plan_id', null)
            ->call('selectBags', 3)
            ->assertSet('bags_count', 3)
            ->assertSet('price', 209);
    }

    public function test_select_trial_clears_subscription_and_keeps_regular_order_flow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = SubscriptionPlan::query()->where('slug', 'every-3-days')->firstOrFail();

        Livewire::test(OrderCreate::class)
            ->call('selectSubscriptionPlan', $plan->id)
            ->assertSet('selected_subscription_plan_id', $plan->id)
            ->call('selectTrial', 1)
            ->assertSet('selected_subscription_plan_id', null)
            ->assertSet('is_trial', true)
            ->assertSet('price', 0)
            ->call('selectBags', 2)
            ->assertSet('is_trial', false)
            ->assertSet('bags_count', 2)
            ->assertSet('price', 159);
    }

    public function test_selecting_subscription_after_trial_clears_trial_state_and_disables_bags(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = SubscriptionPlan::query()->where('slug', 'every-3-days')->firstOrFail();

        Livewire::test(OrderCreate::class)
            ->call('selectTrial', 1)
            ->assertSet('is_trial', true)
            ->assertSet('price', 0)
            ->call('selectSubscriptionPlan', $plan->id)
            ->assertSet('is_trial', false)
            ->assertSet('trial_days', null)
            ->assertSet('selected_subscription_plan_id', $plan->id)
            ->assertSet('price', 400)
            ->assertSee('Недоступно')
            ->assertSeeHtml('disabled')
            ->assertDontSee('Відмовитись від тесту');
    }

    public function test_subscription_modal_shows_correct_prices_pickups_approx_and_saving_percent_for_all_default_plans(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->call('openSubscriptionModal')
            ->assertSee('400 грн / міс')
            ->assertSee('10 виносів на місяць')
            ->assertSee('≈ 40 грн за винос')
            ->assertSee('Економія 43% від разових (до 3 пак.)')
            ->assertSee('585 грн / міс')
            ->assertSee('15 виносів на місяць')
            ->assertSee('≈ 39 грн за винос')
            ->assertSee('Економія 44% від разових (до 3 пак.)')
            ->assertSee('1140 грн / міс')
            ->assertSee('30 виносів на місяць')
            ->assertSee('≈ 38 грн за винос')
            ->assertSee('Економія 46% від разових (до 3 пак.)');
    }

}
