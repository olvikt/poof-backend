<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\AddressForm;
use App\Livewire\Client\AvatarForm;
use App\Livewire\Client\ProfileForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UiRuntimeSaveFlowLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_validation_failure_logs_structured_boundary_without_sensitive_payload_dump(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Log::spy();

        Livewire::test(AddressForm::class)
            ->set('label', 'home')
            ->set('building_type', 'apartment')
            ->set('city', 'Kyiv')
            ->set('street', 'Main Street')
            ->set('house', '7A')
            ->call('save')
            ->assertHasErrors(['search']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $this->assertSame('ui_save_flow_failed', $message);
                $this->assertSame('address', $context['flow'] ?? null);
                $this->assertSame('before_persistence', $context['boundary'] ?? null);
                $this->assertArrayNotHasKey('payload', $context);

                return true;
            });
    }

    public function test_profile_and_avatar_success_paths_emit_start_and_success_markers(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);
        Log::spy();

        Livewire::test(ProfileForm::class)
            ->set('name', 'Operator Ready')
            ->set('email', 'operator@example.com')
            ->set('phone', '+380991112233')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(AvatarForm::class)
            ->set('avatar', UploadedFile::fake()->image('avatar.jpg', 120, 120))
            ->call('save')
            ->assertHasNoErrors();

        Log::shouldHaveReceived('info')
            ->with('ui_save_flow_started', \Mockery::on(fn (array $context): bool => in_array($context['flow'] ?? null, ['profile', 'avatar'], true)))
            ->twice();

        Log::shouldHaveReceived('info')
            ->with('ui_save_flow_succeeded', \Mockery::on(fn (array $context): bool => in_array($context['flow'] ?? null, ['profile', 'avatar'], true)))
            ->twice();
    }
}

