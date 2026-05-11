<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullCount = DB::table('orders')->whereNull('public_id')->count();

        if ($nullCount > 0) {
            $exampleIds = DB::table('orders')
                ->whereNull('public_id')
                ->orderBy('id')
                ->limit(10)
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->implode(', ');

            throw new \RuntimeException(
                'Cannot enforce orders.public_id NOT NULL: found '.$nullCount.' order(s) with NULL public_id. '
                .'Example order IDs: '.$exampleIds.'. '
                .'Backfill missing public_id values before re-running this migration.'
            );
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->change();
        });
    }
};
