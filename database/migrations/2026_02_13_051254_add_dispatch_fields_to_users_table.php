<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Когда курьер последний раз завершил заказ (Idle Priority)
            if (! Schema::hasColumn('users', 'last_completed_at')) {
                $table->timestamp('last_completed_at')
                    ->nullable()
                    ->after('last_seen_at');
            }

            // Когда курьеру последний раз показывали оффер (Rotation)
            if (! Schema::hasColumn('users', 'last_offer_at')) {
                $table->timestamp('last_offer_at')
                    ->nullable()
                    ->after('last_completed_at');
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'last_offer_at')) {
                $table->dropColumn('last_offer_at');
            }

            if (Schema::hasColumn('users', 'last_completed_at')) {
                $table->dropColumn('last_completed_at');
            }

        });
    }
};
