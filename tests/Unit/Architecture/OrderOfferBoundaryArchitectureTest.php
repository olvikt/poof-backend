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

    public function test_hot_path_query_shapes_keep_intended_sql_boundaries(): void
    {
        $dispatcher = $this->normalizedFile('app/Services/Dispatch/OfferDispatcher.php');
        $availableOrders = $this->normalizedFile('app/Livewire/Courier/AvailableOrders.php');
        $myOrders = $this->normalizedFile('app/Livewire/Courier/MyOrders.php');

        $this->assertStringContainsString("->join('couriers', 'couriers.user_id', '=', 'users.id')", $dispatcher);
        $this->assertStringContainsString('->whereNotExists(function ($sub): void {', $dispatcher);
        $this->assertStringNotContainsString("->select('users.*')", $dispatcher);
        $this->assertStringNotContainsString("->select('orders.*', 'users.*')", $dispatcher);

        $this->assertStringContainsString('->alivePendingForCourierOrders((int) $courier->id)', $availableOrders);
        $this->assertStringContainsString("->where('courier_id', $courier->id)", $myOrders);
        $this->assertStringContainsString("->whereIn('status', [", $myOrders);
        $this->assertStringContainsString('Order::STATUS_ACCEPTED', $myOrders);
        $this->assertStringContainsString('Order::STATUS_IN_PROGRESS', $myOrders);
    }
}
