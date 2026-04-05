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
}
