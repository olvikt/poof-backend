<?php

namespace Tests\Feature\Auth;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleEntrypointSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_landing_has_courier_cross_link_without_mixed_registration(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Відкрити courier.poof.com.ua')
            ->assertSee(route('login'));
    }

    public function test_courier_landing_is_served_on_courier_host_with_client_cross_link(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])->get('/');

        $response->assertOk()
            ->assertSee('POOF Courier')
            ->assertSee('https://app.poof.com.ua');
    }

    public function test_client_and_courier_registration_forms_are_role_specific_with_cross_links(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertDontSee('Тип транспорту')
            ->assertSee('Реєстрація курʼєра');

        $this->get('/courier/register')
            ->assertOk()
            ->assertSee('Тип транспорту')
            ->assertSee('Реєстрація клієнта');
    }

    public function test_login_forms_render_role_specific_logo_and_role_aware_registration_links(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('/assets/icons/courier-icon-192.png')
            ->assertSee('/images/logo-poof.png')
            ->assertSee('Ще немає акаунту?')
            ->assertSee('https://app.poof.com.ua/register')
            ->assertSee('Хочете стати курʼєром?')
            ->assertSee('Реєстрація курʼєра')
            ->assertSee('https://courier.poof.com.ua/register');

        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/courier/login')
            ->assertOk()
            ->assertSee('/assets/icons/courier-icon-192.png')
            ->assertSee('Ще немає акаунту?')
            ->assertSee('https://courier.poof.com.ua/register')
            ->assertSee('Хочете стати клієнтом?')
            ->assertSee('Реєстрація клієнта')
            ->assertSee('https://app.poof.com.ua/register');
    }

    public function test_login_on_courier_host_redirects_to_courier_login_and_keeps_next(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/login?next=%2Fcourier%2Forders')
            ->assertRedirect(route('login.courier', ['next' => '/courier/orders']));

        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/login')
            ->assertRedirect(route('login.courier'));

        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/courier/login')
            ->assertOk()
            ->assertSee('Увійти як курʼєр')
            ->assertDontSee('Увійти як клієнт');
    }

    public function test_register_on_courier_host_redirects_to_courier_register_and_renders_courier_flow(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/register')
            ->assertRedirect(route('courier.register'));

        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/courier/register')
            ->assertOk()
            ->assertSee('Реєстрація курʼєра')
            ->assertSee('Тип транспорту')
            ->assertDontSee('Реєстрація клієнта');
    }

    public function test_courier_registration_flow_is_separate_and_redirects_to_courier_space(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])->post('/register', [
            'name' => 'Courier User',
            'email' => 'courier-entry@example.com',
            'phone' => '+380509999999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'transport_type' => 'bike',
            'city' => 'Kyiv',
            'terms_agreed' => '1',
        ]);

        $response->assertRedirect('/courier');

        $user = User::query()->where('email', 'courier-entry@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_COURIER, $user->role);
        $this->assertDatabaseHas('couriers', ['user_id' => $user->id]);
    }

    public function test_session_expired_redirects_are_role_aware_and_next_stays_in_role_space(): void
    {
        $client = User::factory()->create([
            'email' => 'client-role@example.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CLIENT,
        ]);

        $courier = User::factory()->create([
            'email' => 'courier-role@example.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_COURIER,
        ]);
        Courier::factory()->create(['user_id' => $courier->id]);

        $this->get('/client/orders')->assertRedirect('/login?next=%2Fclient%2Forders');
        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get('/courier/orders')
            ->assertRedirect(route('login.courier', ['next' => '/courier/orders']));

        $this->post('/login', [
            'login' => $client->email,
            'password' => 'password123',
            'next' => '/courier/orders',
        ])->assertRedirect(route('client.home'));

        $this->post('/login', [
            'login' => $courier->email,
            'password' => 'password123',
            'next' => '/client/orders',
        ])->assertRedirect(route('courier.home'));

        $this->post('/login', [
            'login' => $courier->email,
            'password' => 'password123',
            'next' => '/courier/my-orders',
        ])->assertRedirect('/courier/my-orders');
    }

    public function test_role_manifests_are_split_by_entrypoint_configuration(): void
    {
        $this->get(route('manifest.client'))
            ->assertOk()
            ->assertJsonPath('start_url', '/client');

        $this->get(route('manifest.courier'))
            ->assertOk()
            ->assertJsonPath('start_url', '/courier');

        $this->withServerVariables(['HTTP_HOST' => 'courier.poof.com.ua'])
            ->get(route('manifest.default'))
            ->assertOk()
            ->assertJsonPath('start_url', '/courier');
    }
}
