<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'phone' => '+380501111111',
            'role' => 'client',
        ]);
    }

    public function test_courier_can_register_and_profile_is_created(): void
    {
        $response = $this->post('/register', [
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
    }

    public function test_login_works_with_phone_identifier(): void
    {
        $user = User::factory()->create([
            'phone' => '+380503333333',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => $user->phone,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client');
        $this->assertAuthenticatedAs($user);
    }
}
