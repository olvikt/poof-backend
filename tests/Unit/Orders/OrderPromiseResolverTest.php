<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Models\Order;
use App\Support\Orders\OrderPromiseResolver;
use Carbon\Carbon;
use Tests\TestCase;

class OrderPromiseResolverTest extends TestCase
{
    public function test_asap_order_gets_valid_until_automatically(): void
    {
        config()->set('order_promise.asap_validity_hours', 4);

        $resolver = app(OrderPromiseResolver::class);
        $now = Carbon::parse('2026-04-05 10:00:00');

        $attributes = $resolver->resolveCreateAttributes([
            'service_mode' => Order::SERVICE_MODE_ASAP,
        ], $now);

        $this->assertSame(Order::SERVICE_MODE_ASAP, $attributes['service_mode']);
        $this->assertTrue($attributes['valid_until_at']->equalTo($now->copy()->addHours(4)));
    }

    public function test_preferred_window_gets_grace_and_allow_late_extension(): void
    {
        config()->set('order_promise.preferred_window_grace_hours', 2);
        config()->set('order_promise.allow_late_extra_hours', 6);

        $resolver = app(OrderPromiseResolver::class);

        $attributes = $resolver->resolveCreateAttributes([
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'scheduled_date' => '2026-04-07',
            'time_from' => '10:00',
            'time_to' => '12:00',
            'client_wait_preference' => Order::WAIT_ALLOW_LATE_FULFILLMENT,
        ], Carbon::parse('2026-04-05 09:00:00'));

        $this->assertSame('2026-04-07 10:00:00', $attributes['window_from_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-07 12:00:00', $attributes['window_to_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-07 20:00:00', $attributes['valid_until_at']->format('Y-m-d H:i:s'));
    }


    public function test_full_datetime_with_extra_time_still_computes_valid_until_correctly(): void
    {
        config()->set('order_promise.preferred_window_grace_hours', 2);

        $resolver = app(OrderPromiseResolver::class);

        $attributes = $resolver->resolveCreateAttributes([
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'scheduled_date' => '2026-03-08 00:00:00',
            'time_from' => '16:00',
            'time_to' => '18:00',
        ], Carbon::parse('2026-03-07 10:00:00'));

        $this->assertSame('2026-03-08 00:00:00', $attributes['window_from_at']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-08 02:00:00', $attributes['window_to_at']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-08 04:00:00', $attributes['valid_until_at']->format('Y-m-d H:i:s'));
    }

    public function test_malformed_legacy_schedule_does_not_crash_and_falls_back_to_asap_validity(): void
    {
        config()->set('order_promise.asap_validity_hours', 4);

        $resolver = app(OrderPromiseResolver::class);
        $now = Carbon::parse('2026-04-05 10:00:00');

        $attributes = $resolver->resolveCreateAttributes([
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'scheduled_date' => 'not-a-date',
            'time_from' => '16:00',
        ], $now);

        $this->assertNull($attributes['window_from_at']);
        $this->assertNull($attributes['window_to_at']);
        $this->assertSame('2026-04-05 14:00:00', $attributes['valid_until_at']->format('Y-m-d H:i:s'));
    }

}
