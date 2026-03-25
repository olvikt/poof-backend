<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordPoofNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
    }

    public function test_forgot_password_page_opens(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Відновлення пароля');
    }

    public function test_forgot_password_post_does_not_return_405_and_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect();
        $response->assertSessionHas('status', __((string) Password::RESET_LINK_SENT));

        Notification::assertSentTo($user, ResetPasswordPoofNotification::class);
    }

    public function test_forgot_password_post_does_not_fail_with_500_and_builds_existing_reset_route(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'route-check@example.com',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(302);
        $this->assertNotSame(500, $response->getStatusCode());

        Notification::assertSentTo($user, ResetPasswordPoofNotification::class, function (ResetPasswordPoofNotification $notification) use ($user): bool {
            $resetUrl = $notification->resetUrl($user);
            $path = parse_url($resetUrl, PHP_URL_PATH);
            $query = parse_url($resetUrl, PHP_URL_QUERY);

            parse_str($query ?? '', $parameters);

            return $resetUrl === route('password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ])
                && $path === '/reset-password/'.$notification->token
                && ($parameters['email'] ?? null) === $user->email;
        });
    }

    public function test_reset_password_notification_uses_ukrainian_subject_and_contains_reset_url(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'mail-check@example.com',
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ])->assertRedirect();

        Notification::assertSentTo($user, ResetPasswordPoofNotification::class, function (ResetPasswordPoofNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $resetUrl = $notification->resetUrl($user);

            return $notification->subjectLine() === 'Скидання пароля в POOF'
                && $mail->subject === 'Скидання пароля в POOF'
                && str_contains($resetUrl, '/reset-password/')
                && str_contains($resetUrl, 'email='.urlencode($user->email));
        });
    }


    public function test_forgot_password_throttle_redirects_back_with_localized_message_and_keeps_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'throttle@example.com',
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from('/forgot-password')->post('/forgot-password', [
                'email' => $user->email,
            ])->assertRedirect('/forgot-password');
        }

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasErrors([
            'email' => __('passwords.throttled'),
        ]);
        $response->assertSessionHasInput('email', $user->email);

        $this->followRedirects($response)
            ->assertSee(__('passwords.throttled'))
            ->assertSee('value="'.$user->email.'"', false);
    }

    public function test_reset_password_page_opens_with_token(): void
    {
        $this->get('/reset-password/test-token?email=user@example.com')
            ->assertOk()
            ->assertSee('Скинути пароль')
            ->assertSee('test-token', false)
            ->assertSee('user@example.com');
    }

    public function test_password_can_be_reset_and_user_can_login_with_new_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'role' => User::ROLE_CLIENT,
            'phone' => '+380501234567',
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $token = null;

        Notification::assertSentTo($user, ResetPasswordPoofNotification::class, function (ResetPasswordPoofNotification $notification) use ($user, &$token): bool {
            $token = $notification->token;

            return $notification->subjectLine() === 'Скидання пароля в POOF'
                && str_contains($notification->resetUrl($user), '/reset-password/')
                && str_contains($notification->resetUrl($user), 'email='.urlencode($user->email));
        });

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('status', __((string) Password::PASSWORD_RESET));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));

        $loginResponse = $this->post('/login', [
            'login' => $user->email,
            'password' => 'new-password',
        ]);

        $loginResponse->assertRedirect('/client');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_invalid_reset_token_is_handled_gracefully(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->from('/reset-password/bad-token?email='.$user->email)->post('/reset-password', [
            'token' => 'bad-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/reset-password/bad-token?email='.$user->email);
        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
