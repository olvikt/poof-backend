<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\ScheduledCourierNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');
            return Str::contains($text, '🚚 Нове замовлення')
                && Str::contains($text, 'У вас є')
                && ! Str::contains($text, '[scheduled_final_offer]');
        });
    }

    public function test_expiring_and_lost_messages_use_human_ukrainian_templates(): void
    {
        Http::fake();
        $order = Order::factory()->create(['price' => 129, 'address_text' => 'Лесі Українки, 44', 'scheduled_time_from' => '12:00', 'scheduled_time_to' => '14:00']);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '1', 'telegram_notifications_orders_enabled' => true]);
        $svc = app(ScheduledCourierNotificationService::class);

        $svc->notifyOfferExpiringSoon($order, $courier);
        $svc->notifyReservationLost($order, $courier);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => Str::contains((string) ($request->data()['text'] ?? ''), '⚠️ Час майже вичерпано'));
        Http::assertSent(fn ($request) => Str::contains((string) ($request->data()['text'] ?? ''), 'іншому курʼєру'));
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

    public function test_no_request_is_sent_and_warning_logged_when_token_missing(): void
    {
        config()->set('services.telegram.bot_token', null);
        Http::fake();
        Log::spy();

        $order = Order::factory()->create();
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '1', 'telegram_notifications_orders_enabled' => true]);

        app(ScheduledCourierNotificationService::class)->notifyFinalOffer($order, $courier);

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'telegram_error'
            && ($context['endpoint'] ?? null) === 'https://api.telegram.org/bot<redacted>/sendMessage'
            && ($context['description'] ?? null) === 'telegram bot token is not configured');
    }

    public function test_deduplicates_same_event_order_and_courier(): void
    {
        Http::fake();
        $order = Order::factory()->create();
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '1', 'telegram_notifications_orders_enabled' => true]);
        $service = app(ScheduledCourierNotificationService::class);

        $service->notifyScheduledOrderVisible($order, $courier);
        $service->notifyScheduledOrderVisible($order, $courier);

        Http::assertSentCount(1);
    }
}
