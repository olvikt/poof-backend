<?php

namespace Tests\Feature\Courier;

use App\Actions\Orders\Lifecycle\AcceptOrderByCourierAction;
use Tests\TestCase;

class AcceptFlowArchitectureRegressionTest extends TestCase
{
    private function normalizedFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_web_and_api_accept_entry_points_delegate_to_lifecycle_action_boundary(): void
    {
        $webRoutes = $this->normalizedFile('routes/web.php');
        $webLifecycleController = $this->normalizedFile('app/Http/Controllers/Courier/CourierOrderLifecycleController.php');
        $apiController = $this->normalizedFile('app/Http/Controllers/Api/CourierOrderController.php');

        $this->assertStringContainsString("Route::post('/orders/{order}/accept', [CourierOrderLifecycleController::class, 'accept'])", $webRoutes);
        $this->assertStringContainsString('app(AcceptOrderByCourierAction::class)->handle($order, $courier)', $webLifecycleController);
        $this->assertStringContainsString('app(AcceptOrderByCourierAction::class)->handle($order, $courier)', $apiController);
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
        $acceptAction = $this->normalizedFile('app/Actions/Orders/Lifecycle/AcceptOrderByCourierAction.php');

        $courierLockPosition = strpos($acceptAction, 'User::query() ->whereKey($courier->getKey()) ->lockForUpdate()');
        $orderLockPosition = strpos($acceptAction, 'Order::query() ->whereKey($order->getKey()) ->lockForUpdate()');

        $this->assertNotFalse($courierLockPosition);
        $this->assertNotFalse($orderLockPosition);
        $this->assertLessThan($orderLockPosition, $courierLockPosition);
    }

    public function test_order_canonical_lifecycle_methods_are_thin_delegates_to_lifecycle_actions(): void
    {
        $orderModel = $this->normalizedFile('app/Models/Order.php');

        $this->assertStringContainsString('app(MarkOrderAsPaidAction::class)->handle($this);', $orderModel);
        $this->assertStringContainsString('app(AcceptOrderByCourierAction::class)->handle($this, $courier);', $orderModel);
        $this->assertStringContainsString('app(StartOrderByCourierAction::class)->handle($this, $courier);', $orderModel);
        $this->assertStringContainsString('app(CompleteOrderByCourierAction::class)->handle($this, $courier);', $orderModel);
        $this->assertStringContainsString('app(CancelOrderAction::class)->handle($this);', $orderModel);
    }

    public function test_legacy_start_and_complete_delegate_to_canonical_by_methods(): void
    {
        $orderModel = $this->normalizedFile('app/Models/Order.php');
        $this->assertStringContainsString('return $this->startBy($courier);', $orderModel);
        $this->assertStringContainsString('return $this->completeBy($courier);', $orderModel);
    }

    public function test_cancel_uses_canonical_runtime_transition_instead_of_scattered_flag_writes(): void
    {
        $cancelAction = $this->normalizedFile('app/Actions/Orders/Lifecycle/CancelOrderAction.php');
        $this->assertStringContainsString('->canBeCancelled()', $cancelAction);
        $this->assertStringContainsString('$courier->markFree();', $cancelAction);
        $this->assertStringNotContainsString("'is_busy' => false", $cancelAction);
        $this->assertStringNotContainsString("'is_online' => false", $cancelAction);
        $this->assertStringNotContainsString("'session_state' =>", $cancelAction);
    }

    public function test_manual_admin_order_flows_do_not_expose_direct_lifecycle_writes_in_forms(): void
    {
        $orderResource = $this->normalizedFile('app/Filament/Resources/OrderResource.php');
        $this->assertStringContainsString("Select::make('status')", $orderResource);
        $this->assertStringContainsString('->disabled()', $orderResource);
        $this->assertStringContainsString('->dehydrated(false)', $orderResource);
        $this->assertStringContainsString("Select::make('courier_id')", $orderResource);
        $this->assertStringContainsString("->relationship('courier', 'name')", $orderResource);
        $this->assertStringContainsString('->dehydrated(false)', $orderResource);
    }

    public function test_manual_admin_courier_flow_cannot_mutate_runtime_state_directly_on_edit(): void
    {
        $courierResource = $this->normalizedFile('app/Filament/Resources/CourierResource.php');
        $this->assertStringContainsString("Select::make('status')", $courierResource);
        $this->assertStringContainsString('Courier::STATUS_ASSIGNED', $courierResource);
        $this->assertStringContainsString('Courier::STATUS_DELIVERING', $courierResource);
        $this->assertStringContainsString("->disabled(fn (?Courier \$record) => \$record !== null)", $courierResource);
        $this->assertStringContainsString("->dehydrated(fn (?Courier \$record) => \$record === null)", $courierResource);
    }
}
