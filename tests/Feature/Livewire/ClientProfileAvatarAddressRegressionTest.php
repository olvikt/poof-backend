<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\AddressForm;
use App\Livewire\Client\AvatarForm;
use App\Livewire\Client\ProfileForm;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClientProfileAvatarAddressRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_edit_updates_auth_user_and_dispatches_profile_saved_contract(): void
    {
        $user = User::factory()->create([
            'name' => 'Before Name',
            'email' => 'before@example.com',
            'phone' => '+380111111111',
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileForm::class)
            ->set('name', 'After Name')
            ->set('email', 'after@example.com')
            ->set('phone', '+380222222222')
            ->call('save')
            ->assertDispatched('sheet:close')
            ->assertDispatched('profile-saved');

        $user->refresh();

        $this->assertSame('After Name', $user->name);
        $this->assertSame('after@example.com', $user->email);
        $this->assertSame('+380222222222', $user->phone);
    }

    public function test_avatar_flow_persists_image_and_emits_avatar_saved_event(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AvatarForm::class)
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg', 120, 120))
            ->call('save')
            ->assertDispatched('avatar-saved')
            ->assertDispatched('sheet:close')
            ->assertSet('avatar', null);

        $user->refresh();

        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_address_create_and_edit_flow_keeps_single_address_record_contract(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(AddressForm::class)
            ->call('open')
            ->set('label', 'home')
            ->set('building_type', 'house')
            ->set('street', 'First Street')
            ->set('house', '10')
            ->set('city', 'Kyiv')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('addressPrecision', 'exact')
            ->call('save')
            ->assertDispatched('address-saved');

        $created = ClientAddress::query()->where('user_id', $user->id)->firstOrFail();

        $component
            ->call('open', $created->id)
            ->set('building_type', 'house')
            ->set('house', '11')
            ->set('lat', 50.46)
            ->set('lng', 30.53)
            ->set('addressPrecision', 'exact')
            ->call('save')
            ->assertDispatched('address-saved');

        $this->assertSame(1, ClientAddress::query()->where('user_id', $user->id)->count());

        $created->refresh();
        $this->assertSame('11', $created->house);
        $this->assertSame(50.46, (float) $created->lat);
        $this->assertSame(30.53, (float) $created->lng);
    }
}
