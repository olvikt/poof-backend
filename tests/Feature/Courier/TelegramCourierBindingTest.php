<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\TelegramBindToken;
use App\Models\User;
use App\Services\Notifications\TelegramBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TelegramCourierBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_bind_token_deep_link(): void
    {
        config()->set('services.telegram.bot_username', 'poof_bot');
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $payload = app(TelegramBindingService::class)->generateForCourier($courier);

        $this->assertStringContainsString('https://t.me/poof_bot?start=', $payload['deep_link']);
        $token = substr($payload['start_command'], 7);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
        $this->assertStringStartsWith('/start ', $payload['start_command']);
        $this->assertDatabaseCount('telegram_bind_tokens', 1);
        $this->assertSame(1, TelegramBindToken::query()->where('user_id', $courier->id)->whereNull('used_at')->where('expires_at', '>', now())->count());
    }

    public function test_webhook_binds_success_and_reuse_expired_invalid_rejected(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);
        $binding = app(TelegramBindingService::class)->generateForCourier($courier);
        $token = substr($binding['start_command'], 7);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$token, 'chat' => ['id' => '777'], 'from' => ['id' => 45, 'username' => 'nick']]])->assertOk();
        $courier->refresh();
        $this->assertSame('777', $courier->telegram_chat_id);

        $next = app(TelegramBindingService::class)->generateForCourier($courier);
        $nextToken = substr($next['start_command'], 7);
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => "/start\n".$nextToken, 'chat' => ['id' => '778'], 'from' => ['id' => 46]]])->assertOk();

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$token, 'chat' => ['id' => '777'], 'from' => ['id' => 45]]])->assertStatus(422);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start invalid', 'chat' => ['id' => '777']]])->assertStatus(422);

        $expired = TelegramBindToken::query()->create(['user_id' => $courier->id, 'token_hash' => hash('sha256', 'expired-token'), 'expires_at' => now()->subMinute()]);
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start expired-token', 'chat' => ['id' => '777']]])->assertStatus(422);
    }

    public function test_unlink_clears_binding(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '1', 'telegram_linked_at' => now()]);

        $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.unlink'))->assertRedirect();

        $courier->refresh();
        $this->assertNull($courier->telegram_chat_id);
        $this->assertNull($courier->telegram_linked_at);
    }

    public function test_courier_profile_renders_telegram_block_and_unlinked_state(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $response = $this->actingAs($courier, 'web')->get(route('courier.profile'));

        $response->assertOk();
        $response->assertSee('Telegram-сповіщення');
        $response->assertSee("Не під'єднано");
        $response->assertSee("Під'єднати Telegram");
    }

    public function test_link_action_shows_deep_link_in_profile(): void
    {
        config()->set('services.telegram.bot_username', 'poof_bot');
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $response = $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.link'));

        $response->assertRedirect();
        $response->assertSessionHas('telegram_deep_link');
        $this->assertStringContainsString('https://t.me/poof_bot?start=', (string) session('telegram_deep_link'));
    }

    public function test_linked_courier_sees_username_and_preferences_persist(): void
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'telegram_chat_id' => '777',
            'telegram_username' => 'nick',
            'telegram_notifications_orders_enabled' => true,
            'telegram_notifications_marketing_enabled' => false,
        ]);

        $page = $this->actingAs($courier, 'web')->get(route('courier.profile'));
        $page->assertOk()->assertSee("Під'єднано: @nick")->assertSee('Сповіщення про замовлення')->assertSee('Новини та акції');

        $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.preferences'), [
            'telegram_notifications_orders_enabled' => 0,
            'telegram_notifications_marketing_enabled' => 1,
        ])->assertRedirect();

        $courier->refresh();
        $this->assertFalse((bool) $courier->telegram_notifications_orders_enabled);
        $this->assertTrue((bool) $courier->telegram_notifications_marketing_enabled);
    }


    public function test_reconnect_flow_keeps_telegram_anchor_and_generates_new_link_after_unlink(): void
    {
        config()->set('services.telegram.bot_username', 'poof_bot');
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'telegram_chat_id' => '100', 'telegram_username' => 'old', 'telegram_linked_at' => now()]);

        $linkResponse = $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.link'));
        $linkResponse->assertRedirect(route('courier.profile').'#courier-telegram-block');
        $firstDeepLink = (string) session('telegram_deep_link');
        $this->assertStringContainsString('https://t.me/poof_bot?start=', $firstDeepLink);

        $unlinkResponse = $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.unlink'));
        $unlinkResponse->assertRedirect(route('courier.profile').'#courier-telegram-block');
        $courier->refresh();
        $this->assertNull($courier->telegram_chat_id);
        $this->assertNull($courier->telegram_user_id);
        $this->assertNull($courier->telegram_username);
        $this->assertNull($courier->telegram_linked_at);

        $relinkResponse = $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.link'));
        $relinkResponse->assertRedirect(route('courier.profile').'#courier-telegram-block');
        $secondDeepLink = (string) session('telegram_deep_link');
        $this->assertStringContainsString('https://t.me/poof_bot?start=', $secondDeepLink);
        $this->assertNotSame($firstDeepLink, $secondDeepLink);
    }


    public function test_repeated_connect_invalidates_previous_active_tokens(): void
    {
        config()->set('services.telegram.bot_username', 'poof_bot');
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        app(TelegramBindingService::class)->generateForCourier($courier);
        app(TelegramBindingService::class)->generateForCourier($courier);

        $this->assertSame(1, TelegramBindToken::query()->where('user_id', $courier->id)->whereNull('used_at')->where('expires_at', '>', now())->count());
    }

    public function test_unlink_invalidates_active_tokens(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);
        app(TelegramBindingService::class)->generateForCourier($courier);

        app(TelegramBindingService::class)->unlink($courier);

        $this->assertSame(0, TelegramBindToken::query()->where('user_id', $courier->id)->whereNull('used_at')->where('expires_at', '>', now())->count());
    }

    public function test_rebind_after_unlink_with_fresh_token_succeeds_and_old_token_fails(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);
        $service = app(TelegramBindingService::class);

        $first = $service->generateForCourier($courier);
        $firstToken = substr($first['start_command'], 7);
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$firstToken, 'chat' => ['id' => '777'], 'from' => ['id' => 45]]])->assertOk();

        $service->unlink($courier);
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$firstToken, 'chat' => ['id' => '777'], 'from' => ['id' => 45]]])->assertStatus(422);

        $second = $service->generateForCourier($courier);
        $secondToken = substr($second['start_command'], 7);
        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$secondToken, 'chat' => ['id' => '777'], 'from' => ['id' => 45, 'username' => 'nick2']]])->assertOk();

        $courier->refresh();
        $this->assertSame('777', $courier->telegram_chat_id);
        $this->assertSame('45', $courier->telegram_user_id);
    }

    public function test_link_action_flashes_manual_start_command_and_profile_renders_it(): void
    {
        config()->set('services.telegram.bot_username', 'poof_bot');
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $response = $this->actingAs($courier, 'web')->post(route('courier.profile.telegram.link'));
        $response->assertSessionHas('telegram_start_command');

        $command = (string) session('telegram_start_command');
        $this->assertStringStartsWith('/start ', $command);

        $page = $this->actingAs($courier, 'web')->get(route('courier.profile'));
        $page->assertSee('Якщо Telegram відкрився без токена, скопіюйте та надішліть цю команду одним повідомленням.');
        $page->assertSee($command);
    }

    public function test_plain_start_without_payload_is_logged_and_ignored(): void
    {
        Log::spy();

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start', 'chat' => ['id' => '777'], 'from' => ['id' => 45]]])->assertOk();

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'plain_start_missing_payload'
            && ($context['chat_id'] ?? null) === '777'
            && ($context['from_id'] ?? null) === '45');
    }

    public function test_unauthorized_users_cannot_call_courier_telegram_endpoints(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $this->post(route('courier.profile.telegram.link'))->assertRedirect('/login');
        $this->post(route('courier.profile.telegram.preferences'), [
            'telegram_notifications_orders_enabled' => 1,
            'telegram_notifications_marketing_enabled' => 1,
        ])->assertRedirect('/login');
        $this->post(route('courier.profile.telegram.unlink'))->assertRedirect('/login');

        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $this->actingAs($client, 'web')->post(route('courier.profile.telegram.link'))->assertForbidden();
        $this->actingAs($client, 'web')->post(route('courier.profile.telegram.preferences'), [
            'telegram_notifications_orders_enabled' => 1,
            'telegram_notifications_marketing_enabled' => 1,
        ])->assertForbidden();
        $this->actingAs($client, 'web')->post(route('courier.profile.telegram.unlink'))->assertForbidden();
    }
}
