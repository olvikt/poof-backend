<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class CreateOrderBoundaryArchitectureTest extends TestCase
{
    private function normalizedFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_api_controller_delegates_create_to_canonical_payload_and_action_boundary(): void
    {
        $source = $this->normalizedFile('app/Http/Controllers/Api/OrderController.php');

        $this->assertStringContainsString('CanonicalOrderCreatePayload::fromValidated($request->validated())', $source);
        $this->assertStringContainsString('app(CreateCanonicalOrderAction::class)->handle(', $source);

        $this->assertStringNotContainsString('Order::query()->create(', $source);
    }

    public function test_livewire_create_delegates_to_legacy_payload_and_action_boundary_without_canonical_aliases(): void
    {
        $source = $this->normalizedFile('app/Livewire/Client/OrderCreate.php');

        $this->assertStringContainsString('LegacyWebOrderCreatePayload::fromArray([', $source);
        $this->assertStringContainsString('app(CreateLegacyWebOrderAction::class)->handle(', $source);

        $this->assertStringNotContainsString('CanonicalOrderCreatePayload', $source);
        $this->assertStringNotContainsString('CreateCanonicalOrderAction', $source);
    }

    public function test_create_actions_keep_mapping_inside_payload_boundary(): void
    {
        $canonicalAction = $this->normalizedFile('app/Actions/Orders/Create/CreateCanonicalOrderAction.php');
        $legacyAction = $this->normalizedFile('app/Actions/Orders/Create/CreateLegacyWebOrderAction.php');

        $this->assertStringContainsString('Order::query()->create( $payload->toOrderAttributes(', $canonicalAction);
        $this->assertStringContainsString('price: $this->calculatePrice($payload->bagsCount())', $canonicalAction);

        $this->assertStringContainsString('Order::query()->create($payload->toOrderAttributes($clientId))', $legacyAction);
    }
}
