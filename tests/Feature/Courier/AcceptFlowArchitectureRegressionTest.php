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

    public function test_livewire_offer_card_does_not_use_manual_row_locks_before_domain_accept(): void
    {
        $offerCard = file_get_contents(base_path('app/Livewire/Courier/OfferCard.php'));

        $this->assertIsString($offerCard);
        $this->assertStringContainsString('->acceptBy($courier)', $offerCard);
        $this->assertStringNotContainsString('lockForUpdate', $offerCard);
    }
}
