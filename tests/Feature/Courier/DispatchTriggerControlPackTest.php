<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\LocationTracker;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DispatchTriggerControlPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_location_triggers_inside_cooldown_are_skipped(): void
    {
        Log::spy();

        $courier = $this->createCourier(online: true);
        $this->actingAs($courier, 'web');

        $component = Livewire::test(LocationTracker::class);

        $component->call('updateLocation', 48.4647, 35.0462, 15);
        $component->call('updateLocation', 48.4660, 35.0475, 15);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'dispatch_trigger_skipped'
                && ($context['trigger_source'] ?? null) === DispatchTriggerPolicy::SOURCE_LOCATION_UPDATE
                && ($context['reason'] ?? null) === 'location_update_cooldown';
        })->once();
    }

    public function test_order_created_fast_path_is_allowed_immediately(): void
    {
        $policy = app(DispatchTriggerPolicy::class);
        $order = $this->createSearchingOrder();

        $decision = $policy->decide(DispatchTriggerPolicy::SOURCE_ORDER_CREATED, ['order' => $order]);

        $this->assertTrue($decision['allowed']);
        $this->assertSame('policy_allowed', $decision['reason']);
        $this->assertSame(DispatchTriggerPolicy::SCOPE_SINGLE_ORDER, $decision['dispatch_scope']);
    }

    public function test_scheduler_trigger_is_coalesced_when_recent_equivalent_already_ran(): void
    {
        $policy = app(DispatchTriggerPolicy::class);

        $first = $policy->decide(DispatchTriggerPolicy::SOURCE_SCHEDULER, ['limit' => 20]);
        $second = $policy->decide(DispatchTriggerPolicy::SOURCE_SCHEDULER, ['limit' => 20]);

        $this->assertTrue($first['allowed']);
        $this->assertFalse($second['allowed']);
        $this->assertSame('scheduler_queue_hot', $second['reason']);
    }

    public function test_live_pending_or_waiting_order_paths_do_not_trigger_redundant_dispatch(): void
    {
        $policy = app(DispatchTriggerPolicy::class);

        $orderWithLivePending = $this->createSearchingOrder();
        OrderOffer::createPrimaryPending(
            orderId: (int) $orderWithLivePending->id,
            courierId: (int) $this->createCourier()->id,
            ttlSeconds: 45,
        );

        $pendingDecision = $policy->decide(DispatchTriggerPolicy::SOURCE_ORDER_CREATED, ['order' => $orderWithLivePending->fresh()]);
        $this->assertFalse($pendingDecision['allowed']);
        $this->assertSame('live_pending_offer_exists', $pendingDecision['reason']);

        $waitingOrder = $this->createSearchingOrder([
            'next_dispatch_at' => now()->addSeconds(90),
        ]);

        $waitingDecision = $policy->decide(DispatchTriggerPolicy::SOURCE_ORDER_CREATED, ['order' => $waitingOrder->fresh()]);
        $this->assertFalse($waitingDecision['allowed']);
        $this->assertSame('order_waiting_next_dispatch', $waitingDecision['reason']);
    }

    public function test_trigger_observability_markers_include_source_and_reason_dimensions(): void
    {
        Log::spy();

        $dispatcher = Mockery::mock(OfferDispatcher::class);
        $dispatcher->shouldReceive('dispatchSearchingOrders')->once()->andReturn(0);
        $this->app->instance(OfferDispatcher::class, $dispatcher);

        $service = app(DispatchTriggerService::class);

        $service->triggerQueueBatch(DispatchTriggerPolicy::SOURCE_SCHEDULER, 20);
        $service->triggerQueueBatch(DispatchTriggerPolicy::SOURCE_SCHEDULER, 20);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'dispatch_trigger_allowed'
                && ($context['trigger_source'] ?? null) === DispatchTriggerPolicy::SOURCE_SCHEDULER
                && array_key_exists('reason', $context);
        })->once();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'dispatch_trigger_skipped'
                && ($context['trigger_source'] ?? null) === DispatchTriggerPolicy::SOURCE_SCHEDULER
                && ($context['reason'] ?? null) === 'scheduler_queue_hot';
        })->once();
    }

    public function test_dispatch_queue_batch_logs_noop_ratio_marker_for_observability(): void
    {
        Log::spy();

        app(OfferDispatcher::class)->dispatchSearchingOrders(5);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'dispatch_queue_batch_processed'
                && array_key_exists('selected_orders', $context)
                && array_key_exists('offers_created', $context)
                && array_key_exists('noop_attempts', $context)
                && array_key_exists('noop_ratio', $context)
                && ($context['counter'] ?? null) === 'dispatch_queue_batch_processed_total';
        })->once();
    }

    public function test_location_trigger_context_uses_previous_coordinates_for_large_movement(): void
    {
        $courier = $this->createCourier(online: true);
        $this->actingAs($courier, 'web');

        $service = Mockery::mock(DispatchTriggerService::class);
        $service->shouldReceive('triggerQueueBatch')
            ->once()
            ->withArgs(function (string $source, int $radiusKm, array $context): bool {
                return $source === DispatchTriggerPolicy::SOURCE_LOCATION_UPDATE
                    && $radiusKm === (int) config('dispatch.radius_km', 20)
                    && ($context['courier_id'] ?? null) !== null
                    && ($context['online'] ?? null) === true
                    && is_numeric($context['distance_moved'] ?? null)
                    && (float) $context['distance_moved'] > (float) config('dispatch.trigger.location_movement_threshold_meters', 50)
                    && ($context['has_moved_enough'] ?? null) === true;
            })
            ->andReturn(0);

        $this->app->instance(DispatchTriggerService::class, $service);

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4685, 35.0505, 15);
    }

    public function test_location_trigger_context_marks_tiny_movement_below_threshold(): void
    {
        $courier = $this->createCourier(online: true);
        $this->actingAs($courier, 'web');

        $service = Mockery::mock(DispatchTriggerService::class);
        $service->shouldReceive('triggerQueueBatch')
            ->once()
            ->withArgs(function (string $source, int $radiusKm, array $context): bool {
                return $source === DispatchTriggerPolicy::SOURCE_LOCATION_UPDATE
                    && $radiusKm === (int) config('dispatch.radius_km', 20)
                    && ($context['online'] ?? null) === true
                    && is_numeric($context['distance_moved'] ?? null)
                    && (float) $context['distance_moved'] < (float) config('dispatch.trigger.location_movement_threshold_meters', 50)
                    && ($context['has_moved_enough'] ?? null) === false;
            })
            ->andReturn(0);

        $this->app->instance(DispatchTriggerService::class, $service);

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.46405, 35.04605, 15);
    }

    private function createCourier(bool $online = false): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'is_online' => $online,
            'last_lat' => 48.4640,
            'last_lng' => 35.0460,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => $online ? Courier::STATUS_ONLINE : Courier::STATUS_OFFLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createSearchingOrder(array $overrides = []): Order
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        return Order::createForTesting(array_merge([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_ONE_TIME,
            'bags_count' => 1,
            'price' => 100,
            'address_text' => 'Dispatch trigger test',
            'lat' => 48.467,
            'lng' => 35.05,
        ], $overrides));
    }
}
