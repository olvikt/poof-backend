<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\OrderCompletionDispute;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminApiRouteMiddlewareContractTest extends TestCase
{
    use RefreshDatabase;

    public static function adminApiEndpointsProvider(): array
    {
        return [
            ['GET', '/api/admin/map-data'],
            ['GET', '/api/admin/runtime-diagnostics'],
            ['GET', '/api/admin/completion-disputes'],
            ['GET', '/api/admin/completion-disputes/{dispute}'],
            ['POST', '/api/admin/completion-disputes/{dispute}/under-review'],
            ['POST', '/api/admin/completion-disputes/{dispute}/resolve-confirmed'],
            ['POST', '/api/admin/completion-disputes/{dispute}/resolve-rejected'],
        ];
    }

    #[DataProvider('adminApiEndpointsProvider')]
    public function test_guest_gets_401_on_all_admin_api_endpoints(string $method, string $uri): void
    {
        $this->json($method, $this->resolveUri($uri))->assertUnauthorized();
    }

    #[DataProvider('adminApiEndpointsProvider')]
    public function test_authenticated_non_admin_gets_403_on_all_admin_api_endpoints(string $method, string $uri): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->json($method, $this->resolveUri($uri))
            ->assertForbidden();
    }

    #[DataProvider('adminApiEndpointsProvider')]
    public function test_admin_is_allowed_through_route_middleware_boundary(string $method, string $uri): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')->json($method, $this->resolveUri($uri));

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
    }

    private function resolveUri(string $uri): string
    {
        if (! str_contains($uri, '{dispute}')) {
            return $uri;
        }

        $request = OrderCompletionRequest::unguarded(fn () => OrderCompletionRequest::query()->create([
            'order_id' => null,
            'courier_id' => null,
            'status' => OrderCompletionRequest::STATUS_DISPUTED,
        ]));

        $dispute = OrderCompletionDispute::unguarded(fn () => OrderCompletionDispute::query()->create([
            'completion_request_id' => $request->id,
            'reason' => 'test',
            'status' => OrderCompletionDispute::STATUS_OPEN,
        ]));

        return str_replace('{dispute}', (string) $dispute->id, $uri);
    }
}
