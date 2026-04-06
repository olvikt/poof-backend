<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class OrderOfferBoundaryArchitectureTest extends TestCase
{
    private function normalizedFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertIsString($contents);

        return preg_replace('/\s+/', ' ', $contents) ?? '';
    }

    public function test_dispatch_and_livewire_use_canonical_order_offer_entry_points(): void
    {
        $dispatcher = $this->normalizedFile('app/Services/Dispatch/OfferDispatcher.php');
        $offerCard = $this->normalizedFile('app/Livewire/Courier/OfferCard.php');
        $stackOffer = $this->normalizedFile('app/Livewire/Courier/StackOfferPopup.php');

        $this->assertStringContainsString('OrderOffer::createPrimaryPending(', $dispatcher);
        $this->assertStringNotContainsString('OrderOffer::create([', $dispatcher);

        $this->assertStringContainsString('markDeclined()', $offerCard);
        $this->assertStringNotContainsString("'status' => OrderOffer::STATUS_DECLINED", $offerCard);

        $this->assertStringContainsString('markDeclined()', $stackOffer);
        $this->assertStringNotContainsString('STATUS_REJECTED', $stackOffer);
    }

    public function test_dispatch_queue_selection_does_not_use_coalesce_ordering_anymore(): void
    {
        $dispatcher = $this->normalizedFile('app/Services/Dispatch/OfferDispatcher.php');

        $this->assertStringNotContainsString('COALESCE(next_dispatch_at, created_at)', $dispatcher);
        $this->assertStringContainsString('dispatchQueueSelection', $dispatcher);
        $this->assertStringContainsString("->whereNotNull('next_dispatch_at')", $dispatcher);
        $this->assertStringContainsString("->whereNull('next_dispatch_at')", $dispatcher);
    }
}
