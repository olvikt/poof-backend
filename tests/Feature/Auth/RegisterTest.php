<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\RegisterController;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Client User',
            'email' => 'client@example.com',
            'phone' => '+380501111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'client',
            'terms_agreed' => '1',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'phone' => '380501111111',
            'role' => 'client',
        ]);
    }

    public function test_courier_can_register_and_profile_is_created(): void
    {
        Mail::fake();

        $response = $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])->post('/register', [
            'name' => 'Courier User',
            'email' => 'courier@example.com',
            'phone' => '+380502222222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'courier',
            'transport_type' => 'bike',
            'city' => 'Kyiv',
            'terms_agreed' => '1',
        ]);

        $response->assertRedirect('/courier');

        $this->assertAuthenticated();

        $user = User::where('email', 'courier@example.com')->firstOrFail();

        $this->assertDatabaseHas('couriers', [
            'user_id' => $user->id,
            'transport' => 'bike',
            'transport_type' => 'bike',
            'city' => 'Kyiv',
            'status' => 'offline',
        ]);
        Mail::assertSent(WelcomeMail::class, 1);
    }


    public function test_courier_registration_is_atomic_when_profile_creation_fails(): void
    {
        Mail::fake();

        $this->partialMock(RegisterController::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('createCourierProfile')->once()->andThrow(new \RuntimeException('Courier create failed'));
        });

        $response = $this->post('/register', [
            'name' => 'Broken Courier',
            'email' => 'broken-courier@example.com',
            'phone' => '+380504444444',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'courier',
            'transport_type' => 'bike',
            'city' => 'Kyiv',
            'terms_agreed' => '1',
        ]);

        $response->assertStatus(500);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'broken-courier@example.com',
        ]);
        $this->assertDatabaseCount('couriers', 0);
        Mail::assertNothingSent();
    }

    public function test_login_works_with_phone_identifier(): void
    {
        $user = User::factory()->create([
            'phone' => '380503333333',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => '+380 50 333 33 33',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client');
        $this->assertAuthenticatedAs($user);
    }
}
