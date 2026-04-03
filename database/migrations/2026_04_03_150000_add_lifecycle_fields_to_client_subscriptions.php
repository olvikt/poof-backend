<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_subscriptions', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('last_run_at');
            }

            if (! Schema::hasColumn('client_subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('status');
            }

            if (! Schema::hasColumn('client_subscriptions', 'renewals_count')) {
                $table->unsignedInteger('renewals_count')->default(0)->after('auto_renew');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_subscriptions', function (Blueprint $table): void {
            foreach (['ends_at', 'auto_renew', 'renewals_count'] as $column) {
                if (Schema::hasColumn('client_subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
