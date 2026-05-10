<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\OrderCreate;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateAddressValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_order_requires_apartment_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->set('street', 'Хрещатик')
            ->set('house', '1')
            ->set('address_text', 'Хрещатик 1')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('is_private_house', false)
            ->set('entrance', null)
            ->set('floor', null)
            ->set('apartment', null)
            ->call('submit')
            ->assertHasErrors(['entrance' => ['required'], 'floor' => ['required'], 'apartment' => ['required']])
            ->assertSee('Поле підʼїзд обовʼязкове для заповнення.')
            ->assertSee('Поле поверх обовʼязкове для заповнення.')
            ->assertSee('Поле квартира обовʼязкове для заповнення.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_private_house_skips_apartment_details_and_saved_address_toggles_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $address = ClientAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'home',
            'title' => 'Дім',
            'building_type' => 'house',
            'address_text' => 'Садова 7',
            'street' => 'Садова',
            'house' => '7',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        Livewire::test(OrderCreate::class)
            ->call('selectAddress', $address->id)
            ->assertSet('is_private_house', true)
            ->set('scheduled_time_from', '10:00')
            ->call('submit');

        $this->assertDatabaseHas('orders', [
            'client_id' => $user->id,
            'building_type' => 'house',
            'entrance' => null,
            'floor' => null,
            'apartment' => null,
            'intercom' => null,
        ]);
    }

    public function test_intercom_is_optional_for_apartment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->set('street', 'Хрещатик')
            ->set('house', '1')
            ->set('address_text', 'Хрещатик 1')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('is_private_house', false)
            ->set('entrance', '1')
            ->set('floor', '2')
            ->set('apartment', '12')
            ->set('intercom', null)
            ->set('scheduled_time_from', '10:00')
            ->call('submit')
            ->assertHasNoErrors(['intercom']);

        $this->assertDatabaseHas('orders', [
            'client_id' => $user->id,
            'building_type' => 'apartment',
            'entrance' => '1',
            'floor' => '2',
            'apartment' => '12',
            'intercom' => null,
        ]);
    }
}
