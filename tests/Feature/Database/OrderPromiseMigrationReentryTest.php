<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPromiseMigrationReentryTest extends TestCase
{
    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sqlitePath = database_path('testing-order-promise-reentry-' . Str::uuid() . '.sqlite');

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

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_migration_is_safe_when_partially_applied_on_sqlite(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $preferredWindowOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address_text' => 'вул. Міграційна, 1',
            'scheduled_date' => '2026-04-05',
            'time_from' => '09:00',
            'time_to' => '10:00',
            'service_mode' => null,
        ]);

        $asapOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Міграційна, 2',
            'service_mode' => 'manual_override',
        ]);

        DB::table('migrations')
            ->where('migration', '2026_04_05_150000_add_order_promise_layer_fields_to_orders')
            ->delete();

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_dispatch_validity_idx');
            $table->dropColumn([
                'window_to_at',
                'valid_until_at',
                'expired_at',
                'expired_reason',
                'client_wait_preference',
                'promise_policy_version',
            ]);
        });

        $exitCode = Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations/2026_04_05_150000_add_order_promise_layer_fields_to_orders.php',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        foreach ([
            'service_mode',
            'window_from_at',
            'window_to_at',
            'valid_until_at',
            'expired_at',
            'expired_reason',
            'client_wait_preference',
            'promise_policy_version',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), "Missing expected column: {$column}");
        }

        $indexes = DB::select("PRAGMA index_list('orders')");
        $indexNames = array_map(static fn ($index): ?string => $index->name ?? null, $indexes);
        $this->assertContains('orders_dispatch_validity_idx', $indexNames);

        $preferredWindowOrder->refresh();
        $asapOrder->refresh();

        $this->assertSame('preferred_window', $preferredWindowOrder->service_mode);
        $this->assertNotNull($preferredWindowOrder->window_from_at);
        $this->assertNotNull($preferredWindowOrder->window_to_at);
        $this->assertNotNull($preferredWindowOrder->valid_until_at);

        $this->assertSame('manual_override', $asapOrder->service_mode);
        $this->assertNull($asapOrder->window_from_at);
        $this->assertNull($asapOrder->window_to_at);
        $this->assertNotNull($asapOrder->valid_until_at);
    }
}
