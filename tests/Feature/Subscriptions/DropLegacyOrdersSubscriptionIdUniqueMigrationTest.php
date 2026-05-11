<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropLegacyOrdersSubscriptionIdUniqueMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Legacy subscription_id unique migration regression coverage is implemented for sqlite test database.');
        }

        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->dateTime('subscription_run_slot')->nullable();
            $table->string('origin', 32)->default('checkout');
            $table->string('payment_status', 32)->default('pending');
            $table->unique('subscription_id', 'orders_subscription_id_unique');
            $table->unique(['subscription_id', 'subscription_run_slot'], 'orders_subscription_slot_unique');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('orders');
        parent::tearDown();
    }

    public function test_migration_removes_unique_subscription_id_constraint_and_preserves_slot_unique_invariant(): void
    {
        $migration = require base_path('database/migrations/2026_05_11_145000_drop_legacy_unique_orders_subscription_id_constraint.php');
        $migration->up();

        DB::table('orders')->insert([
            'subscription_id' => 500,
            'subscription_run_slot' => '2026-05-11 10:00:00',
            'origin' => 'subscription',
            'payment_status' => 'paid',
        ]);

        DB::table('orders')->insert([
            'subscription_id' => 500,
            'subscription_run_slot' => '2026-05-11 11:00:00',
            'origin' => 'subscription',
            'payment_status' => 'paid',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        DB::table('orders')->insert([
            'subscription_id' => 500,
            'subscription_run_slot' => '2026-05-11 11:00:00',
            'origin' => 'subscription',
            'payment_status' => 'pending',
        ]);
    }
}
