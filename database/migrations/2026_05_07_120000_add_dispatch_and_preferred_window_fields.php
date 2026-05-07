<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'dispatch_available_at')) {
                $table->dateTime('dispatch_available_at')->nullable()->after('next_dispatch_at');
                $table->index('dispatch_available_at');
            }
        });

        Schema::table('client_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_subscriptions', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('client_subscriptions', 'preferred_time_window')) {
                $table->string('preferred_time_window', 32)->nullable()->after('next_run_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'dispatch_available_at')) {
                $table->dropIndex(['dispatch_available_at']);
                $table->dropColumn('dispatch_available_at');
            }
        });

        Schema::table('client_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('client_subscriptions', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
            if (Schema::hasColumn('client_subscriptions', 'preferred_time_window')) {
                $table->dropColumn('preferred_time_window');
            }
        });
    }
};
