<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

class LivewireFullPageShellDecompositionArchitectureTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoRoot = dirname(__DIR__, 3);
    }

    public function test_address_form_is_split_into_explicit_concerns(): void
    {
        $component = file_get_contents($this->repoRoot.'/app/Livewire/Client/AddressForm.php');

        $this->assertNotFalse($component);
        $this->assertStringContainsString('use HandlesAddressSearchUi;', $component);
        $this->assertStringContainsString('use HandlesAddressPointResolution;', $component);
        $this->assertStringContainsString('use HandlesAddressPersistence;', $component);

        $this->assertFileExists($this->repoRoot.'/app/Livewire/Client/AddressForm/Concerns/HandlesAddressSearchUi.php');
        $this->assertFileExists($this->repoRoot.'/app/Livewire/Client/AddressForm/Concerns/HandlesAddressPointResolution.php');
        $this->assertFileExists($this->repoRoot.'/app/Livewire/Client/AddressForm/Concerns/HandlesAddressPersistence.php');
    }

    public function test_courier_full_page_components_delegate_navigation_runtime_policy(): void
    {
        $myOrders = file_get_contents($this->repoRoot.'/app/Livewire/Courier/MyOrders.php');
        $availableOrders = file_get_contents($this->repoRoot.'/app/Livewire/Courier/AvailableOrders.php');

        $this->assertNotFalse($myOrders);
        $this->assertNotFalse($availableOrders);

        $this->assertStringContainsString('CourierNavigationRuntime', $myOrders);
        $this->assertStringContainsString('navigationRuntime()', $myOrders);
        $this->assertStringContainsString('CourierNavigationRuntime', $availableOrders);
        $this->assertStringContainsString('navigationRuntime()', $availableOrders);

        $this->assertFileExists($this->repoRoot.'/app/Support/Courier/CourierNavigationRuntime.php');
    }
}
