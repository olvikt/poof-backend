<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CourierResource;
use App\Models\Courier;
use App\Models\TelegramAdminNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

class CourierAdminTelegramNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_resource_contains_telegram_columns_filters_and_actions(): void
    {
        $content = preg_replace('/\s+/', ' ', (string) file_get_contents(base_path('app/Filament/Resources/CourierResource.php')));
        $this->assertStringContainsString("IconColumn::make('user.telegram_chat_id')", $content);
        $this->assertStringContainsString("Filter::make('telegram_linked')", $content);
        $this->assertStringContainsString("Filter::make('telegram_unlinked')", $content);
        $this->assertStringContainsString("Action::make('send_telegram_notification')", $content);
        $this->assertStringContainsString("BulkAction::make('send_telegram_notification_bulk')", $content);
    }

    public function test_non_admin_forbidden_from_admin_couriers_page(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);
        $this->actingAs($courier)->get('/admin/couriers')->assertForbidden();
    }

    public function test_dispatch_respects_preferences_and_writes_audit_logs(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $linked = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '11', 'telegram_notifications_orders_enabled' => true, 'telegram_notifications_marketing_enabled' => true]);
        $unlinked = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => null]);
        $marketingOff = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '12', 'telegram_notifications_marketing_enabled' => false]);

        $records = collect([
            Courier::factory()->create(['user_id' => $linked->id]),
            Courier::factory()->create(['user_id' => $unlinked->id]),
            Courier::factory()->create(['user_id' => $marketingOff->id]),
        ]);

        $method = new ReflectionMethod(CourierResource::class, 'dispatchAdminTelegramNotification');
        $method->setAccessible(true);
        $method->invoke(null, $admin->id, $records, ['title' => 'Тест', 'message' => 'Повідомлення', 'notification_type' => 'news_marketing', 'is_emergency' => false]);

        $this->assertDatabaseCount('telegram_admin_notifications', 3);
        $this->assertDatabaseHas('telegram_admin_notifications', ['courier_id' => $linked->id, 'status' => TelegramAdminNotification::STATUS_SENT]);
        $this->assertDatabaseHas('telegram_admin_notifications', ['courier_id' => $unlinked->id, 'status' => TelegramAdminNotification::STATUS_SKIPPED, 'telegram_error' => 'not_linked']);
        $this->assertDatabaseHas('telegram_admin_notifications', ['courier_id' => $marketingOff->id, 'status' => TelegramAdminNotification::STATUS_SKIPPED, 'telegram_error' => 'marketing_disabled']);
    }

    public function test_dispatch_uses_services_telegram_token_and_handles_missing_token(): void
    {
        config()->set('services.telegram.bot_token', 'test-token-123');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $linked = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '11', 'telegram_notifications_orders_enabled' => true]);
        $records = collect([Courier::factory()->create(['user_id' => $linked->id])]);

        $method = new ReflectionMethod(CourierResource::class, 'dispatchAdminTelegramNotification');
        $method->setAccessible(true);
        $method->invoke(null, $admin->id, $records, ['message' => 'msg', 'notification_type' => 'order_service', 'is_emergency' => false]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/bottest-token-123/sendMessage'));

        config()->set('services.telegram.bot_token', null);
        Http::fake();
        Log::spy();

        $method->invoke(null, $admin->id, $records, ['message' => 'msg2', 'notification_type' => 'order_service', 'is_emergency' => false]);

        Http::assertNothingSent();
        $this->assertDatabaseHas('telegram_admin_notifications', ['courier_id' => $linked->id, 'status' => TelegramAdminNotification::STATUS_FAILED, 'telegram_error' => 'token_missing']);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'telegram_error' && ($context['response_code'] ?? 'x') === null);
    }
}
