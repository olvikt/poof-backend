<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\TelegramBindToken;
use App\Models\User;
use App\Services\Notifications\TelegramBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertDatabaseCount('telegram_bind_tokens', 1);
    }

    public function test_webhook_binds_success_and_reuse_expired_invalid_rejected(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);
        $binding = app(TelegramBindingService::class)->generateForCourier($courier);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$binding['token'], 'chat' => ['id' => '777'], 'from' => ['id' => 45, 'username' => 'nick']]])->assertOk();
        $courier->refresh();
        $this->assertSame('777', $courier->telegram_chat_id);

        $this->postJson('/api/telegram/webhook', ['message' => ['text' => '/start '.$binding['token'], 'chat' => ['id' => '777'], 'from' => ['id' => 45]]])->assertStatus(422);

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
}
