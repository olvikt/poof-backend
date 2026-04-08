<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Courier\Profile\PersistCourierAvatarAction;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\CourierWithdrawalRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CourierProfileCabinetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_can_access_courier_profile_page(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('Налаштування та верифікація');
        $response->assertSee('Гаманець / Баланс');
        $response->assertSee('Підтримка');
        $response->assertSee('Вийти з акаунту');
    }

    public function test_client_cannot_access_courier_profile_page(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $this->actingAs($client, 'web')
            ->get(route('courier.profile'))
            ->assertForbidden();
    }

    public function test_profile_update_writes_required_fields_for_courier(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.update'), [
                'name' => 'Courier Updated',
                'phone' => '+380501234567',
                'email' => 'courier-updated@example.com',
                'residence_city' => 'Київ',
                'residence_address_line' => 'вул. Профільна, 1',
            ])
            ->assertSessionHasNoErrors();

        $courier->refresh();

        $this->assertSame('Courier Updated', $courier->name);
        $this->assertSame('Київ, вул. Профільна, 1', $courier->residence_address);
        $this->assertSame('basic_profile_complete', $courier->courier_verification_status);
    }

    public function test_avatar_update_uses_canonical_controller_action_boundary(): void
    {
        Storage::fake('public');

        $courier = $this->createCourier();

        $spy = Mockery::mock(PersistCourierAvatarAction::class)->makePartial();
        $spy->shouldReceive('execute')->once()->passthru();
        $this->app->instance(PersistCourierAvatarAction::class, $spy);

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $courier->refresh();

        $this->assertNotNull($courier->avatar);
        Storage::disk('public')->assertExists($courier->avatar);
    }

    public function test_profile_render_does_not_depend_on_runtime_mirror_flags(): void
    {
        $courier = $this->createCourier([
            'is_online' => false,
            'is_busy' => true,
            'session_state' => User::SESSION_OFFLINE,
            'name' => 'Mirror Resistant Courier',
        ]);

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('Mirror Resistant Courier');
        $response->assertSee('Рейтинг');
    }

    public function test_balance_summary_uses_ledger_truth_for_profile_block(): void
    {
        $courier = $this->createCourier();
        $this->createSettledLedgerEntry($courier, 400, 80, 320);

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('400,00 ₴', false);
        $response->assertSee('320,00 ₴', false);
    }

    public function test_rating_details_contract_is_rendered_with_explainability_factors(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('Докладніше');
        $response->assertSee('Оцінки клієнтів');
        $response->assertSee('Що покращує рейтинг:');
    }

    public function test_withdrawal_request_contract_validates_minimum_and_persists_request(): void
    {
        config()->set('courier_payout.minimum_withdrawal_amount', 500);

        $courier = $this->createCourier();
        $this->createSettledLedgerEntry($courier, 700, 100, 600);

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.withdrawal.request'), [
                'amount' => 300,
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.withdrawal.request'), [
                'amount' => 550,
                'notes' => 'Phase1 request',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('courier_withdrawal_requests', [
            'courier_id' => $courier->id,
            'amount' => 550,
            'status' => CourierWithdrawalRequest::STATUS_REQUESTED,
        ]);
    }

    public function test_courier_keeps_logout_action_reachable_after_popup_removal(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->from(route('courier.profile'))
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertGuest('web');
    }


    public function test_avatar_edit_bottom_sheet_uses_livewire_form_contract(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('livewire:courier.avatar-form', false);
        $response->assertDontSee("route('courier.profile.avatar.update')", false);
    }


    public function test_avatar_surface_listens_for_avatar_saved_event_for_reactive_refresh(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('x-on:avatar-saved.window', false);
        $response->assertSee(':src="src"', false);
    }

    public function test_avatar_edit_is_exposed_via_clickable_avatar_affordance(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('sheet:open');
        $response->assertSee('courierEditAvatar');
        $response->assertDontSee('>Змінити<', false);
    }

    public function test_edit_profile_text_button_is_replaced_by_pencil_icon_affordance(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertDontSee('Редагувати профіль');
        $response->assertSee('aria-label="Редагувати профіль"', false);
    }

    public function test_rating_and_finance_blocks_render_in_single_row_layout_contract(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('grid grid-cols-2 gap-3', false);
    }

    public function test_profile_edit_form_contains_city_select_field(): void
    {
        $courier = $this->createCourier();

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('name="residence_city"', false);
        $response->assertSee('<option value="Київ"', false);
    }

    public function test_legacy_residence_address_prefix_is_not_duplicated_after_profile_update(): void
    {
        $courier = $this->createCourier([
            'residence_address' => 'м. Київ, вул. Базова, 1',
        ]);

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.update'), [
                'name' => 'Courier Updated',
                'phone' => '+380501234567',
                'email' => 'courier-updated@example.com',
                'residence_city' => 'Київ',
                'residence_address_line' => 'вул. Базова, 1',
            ])
            ->assertSessionHasNoErrors();

        $courier->refresh();

        $this->assertSame('Київ, вул. Базова, 1', $courier->residence_address);
    }

    public function test_composer_strips_legacy_city_prefix_from_address_line_to_avoid_duplication(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.update'), [
                'name' => 'Courier Updated',
                'phone' => '+380501234567',
                'email' => 'courier-updated@example.com',
                'residence_city' => 'Київ',
                'residence_address_line' => 'м. Київ, вул. Польова, 9',
            ])
            ->assertSessionHasNoErrors();

        $courier->refresh();

        $this->assertSame('Київ, вул. Польова, 9', $courier->residence_address);
    }

    public function test_profile_update_rejects_composed_residence_address_longer_than_db_limit(): void
    {
        $courier = $this->createCourier();
        $line = str_repeat('а', 496);

        $this->actingAs($courier, 'web')
            ->post(route('courier.profile.update'), [
                'name' => 'Courier Updated',
                'phone' => '+380501234567',
                'email' => 'courier-updated@example.com',
                'residence_city' => 'Київ',
                'residence_address_line' => $line,
            ])
            ->assertSessionHasErrors('residence_address_line');
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
