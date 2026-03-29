<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class OrderCreateHardeningArchitectureTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_order_create_component_uses_concern_based_runtime_boundaries(): void
    {
        $component = file_get_contents($this->repoRoot.'/app/Livewire/Client/OrderCreate.php');

        $this->assertNotFalse($component);
        $this->assertStringContainsString('use HandlesAddressSelection;', $component);
        $this->assertStringContainsString('use HandlesGeocodingMapSync;', $component);
        $this->assertStringContainsString('use HandlesScheduleSlots;', $component);
        $this->assertStringContainsString('use HandlesPricingTrialPolicy;', $component);
        $this->assertStringContainsString('use HandlesOrderSubmission;', $component);
    }
}
