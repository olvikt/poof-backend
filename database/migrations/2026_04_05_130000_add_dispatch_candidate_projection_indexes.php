<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table): void {
            $table->index(['status', 'last_location_at'], 'couriers_status_last_location_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'is_active'], 'users_role_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table): void {
            $table->dropIndex('couriers_status_last_location_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_active_idx');
        });
    }
};
