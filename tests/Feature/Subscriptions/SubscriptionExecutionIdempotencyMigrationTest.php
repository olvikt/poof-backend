<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SubscriptionExecutionIdempotencyMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Migration guard index behavior test is implemented for sqlite test database.');
        }

        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('origin', 32)->default('checkout');
            $table->string('payment_status', 32)->default('pending');
            $table->dateTime('scheduled_date')->nullable();
            $table->time('scheduled_time_from')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('orders');

        parent::tearDown();
    }

    public function test_migration_succeeds_when_pending_subscription_duplicates_do_not_exist(): void
    {
        DB::table('orders')->insert([
            ['subscription_id' => 10, 'origin' => 'subscription', 'payment_status' => 'pending'],
            ['subscription_id' => 11, 'origin' => 'subscription', 'payment_status' => 'pending'],
        ]);

        $migration = require base_path('database/migrations/2026_05_11_120000_add_subscription_execution_idempotency_to_orders.php');
        $migration->up();

        $indexes = DB::select("PRAGMA index_list('orders')");
        $names = array_map(static fn ($row): string => (string) $row->name, $indexes);

        $this->assertContains('orders_one_pending_subscription_execution_idx', $names);

        $migration->down();
    }

    public function test_migration_fails_with_actionable_message_when_pending_subscription_duplicates_exist(): void
    {
        DB::table('orders')->insert([
            ['subscription_id' => 42, 'origin' => 'subscription', 'payment_status' => 'pending'],
            ['subscription_id' => 42, 'origin' => 'subscription', 'payment_status' => 'pending'],
        ]);

        $migration = require base_path('database/migrations/2026_05_11_120000_add_subscription_execution_idempotency_to_orders.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate pending subscription execution orders exist');
        $this->expectExceptionMessage('42(x2)');

        $migration->up();
    }
}
