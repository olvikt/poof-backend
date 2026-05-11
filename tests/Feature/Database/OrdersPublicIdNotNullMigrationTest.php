<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OrdersPublicIdNotNullMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('orders');
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('orders');
        parent::tearDown();
    }

    public function test_migration_fails_with_actionable_message_when_null_public_id_exists(): void
    {
        DB::table('orders')->insert([
            ['public_id' => null],
            ['public_id' => '11111111-1111-1111-1111-111111111111'],
            ['public_id' => null],
        ]);

        $migration = require base_path('database/migrations/2026_05_11_150000_harden_orders_public_id_not_nullable.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot enforce orders.public_id NOT NULL');
        $this->expectExceptionMessage('found 2 order(s) with NULL public_id');
        $this->expectExceptionMessage('Example order IDs: 1, 3');

        $migration->up();
    }

    public function test_migration_succeeds_when_all_rows_have_public_id(): void
    {
        DB::table('orders')->insert([
            ['public_id' => '11111111-1111-1111-1111-111111111111'],
            ['public_id' => '22222222-2222-2222-2222-222222222222'],
        ]);

        $migration = require base_path('database/migrations/2026_05_11_150000_harden_orders_public_id_not_nullable.php');
        $migration->up();

        $columns = DB::select("PRAGMA table_info('orders')");
        $publicId = collect($columns)->firstWhere('name', 'public_id');

        $this->assertNotNull($publicId);
        $this->assertSame(1, (int) $publicId->notnull);

        $migration->down();
    }
}
