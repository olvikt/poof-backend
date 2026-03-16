<?php

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderAcceptRaceConditionTest extends TestCase
{
    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlitePath = database_path('testing-race-condition.sqlite');

        if (file_exists($this->sqlitePath)) {
            unlink($this->sqlitePath);
        }

        touch($this->sqlitePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->sqlitePath);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (file_exists($this->sqlitePath)) {
            unlink($this->sqlitePath);
        }

        parent::tearDown();
    }

    public function test_accept_route_uses_domain_decision_and_rejects_second_courier(): void
    {
        [$order, $winner, $loser] = $this->seedOrderAndTwoOnlineCouriers();

        $first = $this->actingAs($winner, 'web')
            ->post(route('courier.orders.accept', $order));

        $first
            ->assertRedirect(route('courier.my-orders'))
            ->assertSessionHas('success', 'Замовлення прийнято.');

        $second = $this->actingAs($loser, 'web')
            ->from(route('courier.orders'))
            ->post(route('courier.orders.accept', $order));

        $second
            ->assertRedirect(route('courier.orders'))
            ->assertSessionHas('error', 'Не вдалося прийняти замовлення.');

        $order->refresh();
        $winner->refresh();
        $loser->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertSame($winner->id, $order->courier_id);
        $this->assertTrue((bool) $winner->is_busy);
        $this->assertFalse((bool) $loser->is_busy);
    }

    public function test_concurrent_accept_by_allows_exactly_one_winner(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension is required for concurrent accept race test.');
        }

        [$order, $courierA, $courierB] = $this->seedOrderAndTwoOnlineCouriers();

        if (! is_dir(storage_path('framework/testing'))) {
            mkdir(storage_path('framework/testing'), 0777, true);
        }

        $barrier = storage_path('framework/testing/accept-race-' . Str::uuid() . '.barrier');
        $resultA = storage_path('framework/testing/accept-race-' . Str::uuid() . '.json');
        $resultB = storage_path('framework/testing/accept-race-' . Str::uuid() . '.json');

        @unlink($barrier);
        @unlink($resultA);
        @unlink($resultB);

        $pidA = $this->spawnAcceptProcess($order->id, $courierA->id, $barrier, $resultA);
        $pidB = $this->spawnAcceptProcess($order->id, $courierB->id, $barrier, $resultB);

        file_put_contents($barrier, 'go');

        pcntl_waitpid($pidA, $statusA);
        pcntl_waitpid($pidB, $statusB);

        $this->assertSame(0, pcntl_wexitstatus($statusA));
        $this->assertSame(0, pcntl_wexitstatus($statusB));

        $outA = json_decode((string) file_get_contents($resultA), true, 512, JSON_THROW_ON_ERROR);
        $outB = json_decode((string) file_get_contents($resultB), true, 512, JSON_THROW_ON_ERROR);

        $successes = array_filter([$outA['ok'], $outB['ok']]);

        $this->assertCount(1, $successes, 'Exactly one courier must accept the order.');

        $order->refresh();
        $courierA->refresh();
        $courierB->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertContains($order->courier_id, [$courierA->id, $courierB->id]);

        $winnerId = (int) $order->courier_id;
        $loserId = $winnerId === $courierA->id ? $courierB->id : $courierA->id;

        $this->assertTrue((bool) User::query()->findOrFail($winnerId)->is_busy);
        $this->assertFalse((bool) User::query()->findOrFail($loserId)->is_busy);

        @unlink($barrier);
        @unlink($resultA);
        @unlink($resultB);
    }

    public function test_busy_courier_cannot_accept_second_order(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        $firstOrder = Order::query()->create([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Перша, 1',
            'address_text' => 'вул. Перша, 1',
            'price' => 100,
        ]);

        $secondOrder = Order::query()->create([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Друга, 2',
            'address_text' => 'вул. Друга, 2',
            'price' => 120,
        ]);

        $this->assertTrue($firstOrder->acceptBy($courier));

        $response = $this->actingAs($courier, 'web')
            ->from(route('courier.orders'))
            ->post(route('courier.orders.accept', $secondOrder));

        $response
            ->assertRedirect(route('courier.orders'))
            ->assertSessionHas('error', 'Не вдалося прийняти замовлення.');

        $firstOrder->refresh();
        $secondOrder->refresh();
        $courier->refresh();

        $this->assertSame($courier->id, $firstOrder->courier_id);
        $this->assertSame(Order::STATUS_ACCEPTED, $firstOrder->status);

        $this->assertNull($secondOrder->courier_id);
        $this->assertSame(Order::STATUS_SEARCHING, $secondOrder->status);

        $this->assertTrue($courier->isBusyForAccept());
        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
    }

    private function spawnAcceptProcess(int $orderId, int $courierId, string $barrierPath, string $resultPath): int
    {
        $pid = pcntl_fork();

        $this->assertNotSame(-1, $pid, 'Failed to fork process for concurrency test.');

        if ($pid === 0) {
            Config::set('database.default', 'sqlite');
            Config::set('database.connections.sqlite.database', $this->sqlitePath);

            DB::purge('sqlite');
            DB::reconnect('sqlite');

            while (! file_exists($barrierPath)) {
                usleep(5000);
            }

            $order = Order::query()->findOrFail($orderId);
            $courier = User::query()->findOrFail($courierId);

            $ok = $order->acceptBy($courier);

            file_put_contents($resultPath, json_encode([
                'ok' => $ok,
                'courier_id' => $courierId,
            ], JSON_THROW_ON_ERROR));

            exit(0);
        }

        return $pid;
    }

    private function seedOrderAndTwoOnlineCouriers(): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courierA = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
        ]);

        $courierB = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
        ]);

        Courier::query()->create([
            'user_id' => $courierA->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        Courier::query()->create([
            'user_id' => $courierB->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        $order = Order::query()->create([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Тестова, 1',
            'address_text' => 'вул. Тестова, 1',
            'price' => 100,
        ]);

        return [$order, $courierA, $courierB];
    }
}
