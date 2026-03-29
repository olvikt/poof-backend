<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class RouteLayerThinBoundaryArchitectureTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    private function normalizedFile(string $relativePath): string
    {
        $contents = file_get_contents($this->repoRoot.'/'.$relativePath);

        $this->assertNotFalse($contents);

        return preg_replace('/\s+/', ' ', (string) $contents) ?? '';
    }

    public function test_courier_lifecycle_web_routes_use_canonical_controller_entrypoints(): void
    {
        $webRoutes = $this->normalizedFile('routes/web.php');

        $this->assertStringContainsString("Route::post('/orders/{order}/accept', [CourierOrderLifecycleController::class, 'accept'])", $webRoutes);
        $this->assertStringContainsString("Route::post('/orders/{order}/start', [CourierOrderLifecycleController::class, 'start'])", $webRoutes);
        $this->assertStringContainsString("Route::post('/orders/{order}/complete', [CourierOrderLifecycleController::class, 'complete'])", $webRoutes);

        $this->assertStringNotContainsString("Route::post('/orders/{order}/accept', function", $webRoutes);
        $this->assertStringNotContainsString("Route::post('/orders/{order}/start', function", $webRoutes);
        $this->assertStringNotContainsString("Route::post('/orders/{order}/complete', function", $webRoutes);
    }

    public function test_profile_routes_delegate_to_profile_controller_without_inline_business_logic(): void
    {
        $webRoutes = $this->normalizedFile('routes/web.php');

        $this->assertStringContainsString("Route::post('/profile/address', [ProfileController::class, 'storeAddress'])", $webRoutes);
        $this->assertStringContainsString("Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])", $webRoutes);
        $this->assertStringContainsString("Route::post('/profile/update', [ProfileController::class, 'update'])", $webRoutes);

        $this->assertStringNotContainsString("Route::post('/profile/address', function", $webRoutes);
        $this->assertStringNotContainsString("Route::post('/profile/avatar', function", $webRoutes);
        $this->assertStringNotContainsString("Route::post('/profile/update', function", $webRoutes);
    }

    public function test_route_layer_does_not_write_courier_runtime_state_directly_for_lifecycle_routes(): void
    {
        $webRoutes = $this->normalizedFile('routes/web.php');
        $controller = $this->normalizedFile('app/Http/Controllers/Courier/CourierOrderLifecycleController.php');

        $this->assertStringNotContainsString('markBusy(', $webRoutes);
        $this->assertStringNotContainsString('markDelivering(', $webRoutes);
        $this->assertStringNotContainsString('markFree(', $webRoutes);
        $this->assertStringContainsString('app(AcceptOrderByCourierAction::class)->handle($order, $courier)', $controller);
        $this->assertStringContainsString('app(StartOrderByCourierAction::class)->handle($order, $courier)', $controller);
        $this->assertStringContainsString('app(CompleteOrderByCourierAction::class)->handle($order, $courier)', $controller);
    }
}
