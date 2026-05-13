<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\ScheduledCourierNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduledCourierNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_notification_skipped_when_disabled_and_sent_when_enabled(): void
    {
        Http::fake();
        $order = Order::factory()->create();
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '1', 'telegram_notifications_orders_enabled' => false]);
        $svc = app(ScheduledCourierNotificationService::class);

        $svc->notifyScheduledOrderVisible($order, $courier);
        Http::assertNothingSent();

        $courier->update(['telegram_notifications_orders_enabled' => true]);
        $svc->notifyFinalOffer($order, $courier->fresh());
        Http::assertSentCount(1);
    }

    public function test_marketing_skipped_unless_enabled(): void
    {
        Http::fake();
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '1', 'telegram_notifications_marketing_enabled' => false]);
        $svc = app(ScheduledCourierNotificationService::class);

        $svc->notifyMarketingNews($courier, 'Promo');
        Http::assertNothingSent();

        $courier->update(['telegram_notifications_marketing_enabled' => true]);
        $svc->notifyMarketingNews($courier->fresh(), 'Promo');
        Http::assertSentCount(1);
    }
}
