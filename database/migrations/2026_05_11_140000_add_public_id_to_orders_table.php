<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
        });

        DB::table('orders')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->whereNull('public_id')
                        ->update(['public_id' => (string) Str::uuid()]);
                }
            });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
