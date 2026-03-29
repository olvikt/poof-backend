<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRuntimeDiagnosticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_runtime_diagnostics_requires_admin_authentication(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/admin/runtime-diagnostics')->assertForbidden();
    }

    public function test_admin_runtime_diagnostics_returns_minimal_operator_snapshot(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin, 'web');

        $response = $this->getJson('/api/admin/runtime-diagnostics')->assertOk();

        $response->assertJsonStructure([
            'runtime_mode',
            'queue_driver',
            'cache_driver',
            'release_summary',
        ]);
    }
}

