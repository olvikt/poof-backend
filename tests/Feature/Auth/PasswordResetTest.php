<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;


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

        $response->assertRedirect();
        $response->assertSessionHas('status', __((string) Password::RESET_LINK_SENT));

        Notification::assertSentTo($user, ResetPassword::class);
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

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
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
