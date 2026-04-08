<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Courier\Profile\PersistCourierAvatarAction;
use App\Livewire\Courier\AvatarForm;
use App\Models\Courier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CourierAvatarFlowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_avatar_form_persists_avatar_via_action_boundary_and_closes_sheet(): void
    {
        Storage::fake('public');

        $courier = Courier::factory()->create();
        $this->actingAs($courier, 'web');

        $spy = Mockery::mock(PersistCourierAvatarAction::class)->makePartial();
        $spy->shouldReceive('execute')->once()->passthru();
        $this->app->instance(PersistCourierAvatarAction::class, $spy);

        Livewire::test(AvatarForm::class)
            ->set('avatar', UploadedFile::fake()->image('courier-avatar.jpg', 240, 240))
            ->call('save')
            ->assertDispatched('avatar-saved')
            ->assertDispatched('sheet:close', name: 'courierEditAvatar')
            ->assertSet('avatar', null);

        $courier->refresh();

        $this->assertNotNull($courier->avatar);
        Storage::disk('public')->assertExists($courier->avatar);
    }

    public function test_oversized_courier_avatar_is_rejected_with_validation_instead_of_persisting(): void
    {
        Storage::fake('public');

        $courier = Courier::factory()->create();
        $this->actingAs($courier, 'web');

        $spy = Mockery::mock(PersistCourierAvatarAction::class);
        $spy->shouldNotReceive('execute');
        $this->app->instance(PersistCourierAvatarAction::class, $spy);

        Livewire::test(AvatarForm::class)
            ->set('avatar', UploadedFile::fake()->image('too-large.jpg')->size(3072))
            ->call('save')
            ->assertHasErrors(['avatar' => ['max']]);

        $courier->refresh();

        $this->assertNull($courier->avatar);
    }
}
