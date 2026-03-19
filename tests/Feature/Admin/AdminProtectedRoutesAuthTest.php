<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProtectedRoutesAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_web_protected_routes(): void
    {
        $this->get('/api/admin/map-data')->assertRedirect('/login');
        $this->get('/dashboard/map')->assertRedirect('/login');
    }

    public function test_guest_json_calls_are_unauthorized_for_admin_map_data(): void
    {
        $this->getJson('/api/admin/map-data')->assertUnauthorized();
        $this->getJson('/dashboard/map')->assertUnauthorized();
    }

    public function test_admin_can_access_admin_routes_via_web_guard(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->getJson('/api/admin/map-data')
            ->assertOk()
            ->assertJsonStructure(['couriers', 'orders']);

        $this->actingAs($admin, 'web')
            ->get('/dashboard/map')
            ->assertOk();
    }

    public function test_non_admin_authenticated_user_is_forbidden_from_admin_routes(): void
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);

        $this->actingAs($courier, 'web')
            ->getJson('/api/admin/map-data')
            ->assertForbidden();

        $this->actingAs($courier, 'web')
            ->get('/dashboard/map')
            ->assertForbidden();
    }
}
