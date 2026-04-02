<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\OrderCreate;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateSaveAddressPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_address_prompts_to_save_and_can_be_saved_before_checkout_continues(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(OrderCreate::class)
            ->set('street', 'Січових Стрільців')
            ->set('house', '10')
            ->set('city', 'Київ')
            ->set('address_text', 'Січових Стрільців 10')
            ->set('lat', 50.4505)
            ->set('lng', 30.5234)
            ->set('coordsFromAddressBook', true)
            ->set('address_precision', 'exact')
            ->call('submit')
            ->assertSet('showSaveAddressConfirmModal', true);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('client_addresses', 0);

        $component
            ->call('confirmSaveAddressAndContinue')
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('client_addresses', 1);
    }

    public function test_new_address_can_be_skipped_and_checkout_continues_without_saving(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->set('street', 'Набережна')
            ->set('house', '5')
            ->set('city', 'Київ')
            ->set('address_text', 'Набережна 5')
            ->set('lat', 50.4511)
            ->set('lng', 30.5201)
            ->set('coordsFromAddressBook', true)
            ->set('address_precision', 'exact')
            ->call('submit')
            ->assertSet('showSaveAddressConfirmModal', true)
            ->call('declineSaveAddressAndContinue')
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('client_addresses', 0);
    }

    public function test_saved_address_flow_does_not_show_save_confirm_and_does_not_duplicate_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $address = ClientAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'home',
            'building_type' => 'apartment',
            'address_text' => 'Володимирська 12, Київ',
            'city' => 'Київ',
            'street' => 'Володимирська',
            'house' => '12',
            'lat' => 50.4545,
            'lng' => 30.5165,
        ]);

        Livewire::test(OrderCreate::class)
            ->call('selectAddress', $address->id)
            ->call('submit')
            ->assertSet('showSaveAddressConfirmModal', false)
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseCount('client_addresses', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'address_id' => $address->id,
        ]);
    }

    public function test_manual_duplicate_address_skips_confirm_and_emits_duplicate_message(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ClientAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'home',
            'building_type' => 'apartment',
            'address_text' => 'Хрещатик 1, Київ',
            'city' => 'Київ',
            'street' => 'Хрещатик',
            'house' => '1',
            'lat' => 50.4501,
            'lng' => 30.5237,
        ]);

        Livewire::test(OrderCreate::class)
            ->set('street', '  хрещатик ')
            ->set('house', '1')
            ->set('city', 'Київ')
            ->set('address_text', 'хрещатик 1')
            ->set('lat', 50.4501)
            ->set('lng', 30.5237)
            ->set('coordsFromAddressBook', true)
            ->set('address_precision', 'exact')
            ->call('submit')
            ->assertSet('showSaveAddressConfirmModal', false)
            ->assertSet('showPaymentModal', true)
            ->assertDispatched('notify');

        $this->assertDatabaseCount('client_addresses', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_geolocation_map_selection_can_still_reach_save_prompt(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->set('street', 'Леонтовича')
            ->set('house', '8')
            ->set('city', 'Київ')
            ->set('address_text', 'Леонтовича 8')
            ->call('setLocation', 50.4477, 30.5191)
            ->set('coordsFromAddressBook', false)
            ->set('address_precision', 'exact')
            ->call('submit')
            ->assertSet('showSaveAddressConfirmModal', true)
            ->assertSet('showPaymentModal', false);
    }
}
