<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiRouteMiddlewareContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0:string, 1:string}> */
    public static function adminApiEndpointsProvider(): array
    {
        return [
            ['GET', '/api/admin/map-data'],
            ['GET', '/api/admin/runtime-diagnostics'],
            ['GET', '/api/admin/completion-disputes'],
            ['GET', '/api/admin/completion-disputes/1'],
            ['POST', '/api/admin/completion-disputes/1/under-review'],
            ['POST', '/api/admin/completion-disputes/1/resolve-confirmed'],
            ['POST', '/api/admin/completion-disputes/1/resolve-rejected'],
        ];
    }

    /** @dataProvider adminApiEndpointsProvider */
    public function test_guest_gets_401_on_all_admin_api_endpoints(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    /** @dataProvider adminApiEndpointsProvider */
    public function test_authenticated_non_admin_gets_403_on_all_admin_api_endpoints(string $method, string $uri): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->json($method, $uri)
            ->assertForbidden();
    }

    /** @dataProvider adminApiEndpointsProvider */
    public function test_admin_is_allowed_through_route_middleware_boundary(string $method, string $uri): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->json($method, $uri);

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
    }
}
