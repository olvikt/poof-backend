<?php

namespace Tests\Feature\Courier;

use Tests\TestCase;

class AcceptFlowArchitectureRegressionTest extends TestCase
{
    public function test_web_and_api_accept_entry_points_delegate_to_domain_accept_by_method(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $apiController = file_get_contents(base_path('app/Http/Controllers/Api/CourierOrderController.php'));

        $this->assertIsString($webRoutes);
        $this->assertIsString($apiController);

        $this->assertStringContainsString('->acceptBy(auth()->user())', $webRoutes);
        $this->assertStringContainsString('->acceptBy($courier)', $apiController);
    }

    public function test_livewire_accept_entry_points_delegate_to_canonical_accept_methods_without_manual_locks(): void
    {
        $offerCard = file_get_contents(base_path('app/Livewire/Courier/OfferCard.php'));
        $stackOfferPopup = file_get_contents(base_path('app/Livewire/Courier/StackOfferPopup.php'));
        $orderOffer = file_get_contents(base_path('app/Models/OrderOffer.php'));

        $this->assertIsString($offerCard);
        $this->assertIsString($stackOfferPopup);
        $this->assertIsString($orderOffer);

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
        $orderModel = file_get_contents(base_path('app/Models/Order.php'));

        $this->assertIsString($orderModel);

        $courierLockPosition = strpos($orderModel, "User::query()\n            ->whereKey($courier->getKey())\n            ->lockForUpdate()");
        $orderLockPosition = strpos($orderModel, "self::query()\n            ->whereKey($this->getKey())\n            ->lockForUpdate()");

        $this->assertNotFalse($courierLockPosition);
        $this->assertNotFalse($orderLockPosition);
        $this->assertLessThan($orderLockPosition, $courierLockPosition);
    }
}
