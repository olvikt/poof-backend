<?php

namespace Tests\Feature\Courier;

use Tests\TestCase;

class AcceptFlowArchitectureRegressionTest extends TestCase
{
    private function normalizedFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_web_and_api_accept_entry_points_delegate_to_domain_accept_by_method(): void
    {
        $webRoutes = $this->normalizedFile('routes/web.php');
        $apiController = $this->normalizedFile('app/Http/Controllers/Api/CourierOrderController.php');

        $this->assertStringContainsString('->acceptBy(auth()->user())', $webRoutes);
        $this->assertStringContainsString('->acceptBy($courier)', $apiController);
    }

    public function test_livewire_accept_entry_points_delegate_to_canonical_accept_methods_without_manual_locks(): void
    {
        $offerCard = $this->normalizedFile('app/Livewire/Courier/OfferCard.php');
        $stackOfferPopup = $this->normalizedFile('app/Livewire/Courier/StackOfferPopup.php');
        $orderOffer = $this->normalizedFile('app/Models/OrderOffer.php');

        $this->assertStringContainsString('->acceptBy($courier)', $offerCard);
        $this->assertStringContainsString('->acceptBy(auth()->user())', $stackOfferPopup);
        $this->assertStringContainsString('order->acceptBy($courier)', $orderOffer);

        $this->assertStringNotContainsString('lockForUpdate', $offerCard);
        $this->assertStringNotContainsString('lockForUpdate', $stackOfferPopup);
        $this->assertStringNotContainsString("'status' => OrderOffer::STATUS_ACCEPTED", $offerCard);
        $this->assertStringNotContainsString("'status' => OrderOffer::STATUS_ACCEPTED", $stackOfferPopup);
    }

    public function test_domain_accept_locks_courier_before_order(): void
    {
        $orderModel = $this->normalizedFile('app/Models/Order.php');

        $courierLockPosition = strpos($orderModel, 'User::query() ->whereKey($courier->getKey()) ->lockForUpdate()');
        $orderLockPosition = strpos($orderModel, 'self::query() ->whereKey($this->getKey()) ->lockForUpdate()');

        $this->assertNotFalse($courierLockPosition);
        $this->assertNotFalse($orderLockPosition);
        $this->assertLessThan($orderLockPosition, $courierLockPosition);
    }

    public function test_legacy_start_and_complete_delegate_to_canonical_by_methods(): void
    {
        $orderModel = $this->normalizedFile('app/Models/Order.php');
        $this->assertStringContainsString('return $this->startBy($courier);', $orderModel);
        $this->assertStringContainsString('return $this->completeBy($courier);', $orderModel);
    }

    public function test_cancel_uses_canonical_runtime_transition_instead_of_scattered_flag_writes(): void
    {
        $orderModel = $this->normalizedFile('app/Models/Order.php');
        $this->assertStringContainsString('->canBeCancelled()', $orderModel);
        $this->assertStringContainsString('$courier->markFree();', $orderModel);
        $this->assertStringNotContainsString("'is_busy' => false", $orderModel);
        $this->assertStringNotContainsString("'is_online' => false", $orderModel);
        $this->assertStringNotContainsString("'session_state' =>", $orderModel);
    }

    public function test_manual_admin_order_flows_do_not_expose_direct_lifecycle_writes_in_forms(): void
    {
        $orderResource = $this->normalizedFile('app/Filament/Resources/OrderResource.php');
        $this->assertStringContainsString("Select::make('status')", $orderResource);
        $this->assertStringContainsString("->disabled()", $orderResource);
        $this->assertStringContainsString("->dehydrated(false)", $orderResource);
        $this->assertStringContainsString("Select::make('courier_id')", $orderResource);
        $this->assertStringContainsString("->relationship('courier', 'name')", $orderResource);
        $this->assertStringContainsString("->dehydrated(false)", $orderResource);
    }

    public function test_manual_admin_courier_flow_cannot_mutate_runtime_state_directly_on_edit(): void
    {
        $courierResource = $this->normalizedFile('app/Filament/Resources/CourierResource.php');
        $this->assertStringContainsString("Select::make('status')", $courierResource);
        $this->assertStringContainsString("Courier::STATUS_ASSIGNED", $courierResource);
        $this->assertStringContainsString("Courier::STATUS_DELIVERING", $courierResource);
        $this->assertStringContainsString("->disabled(fn (?Courier \$record) => \$record !== null)", $courierResource);
        $this->assertStringContainsString("->dehydrated(fn (?Courier \$record) => \$record === null)", $courierResource);
    }
}
