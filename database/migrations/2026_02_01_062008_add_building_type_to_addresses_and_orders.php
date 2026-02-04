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
        // --------------------------------------------------
        // Client Addresses
        // --------------------------------------------------
        Schema::table('client_addresses', function (Blueprint $table) {
            $table
                ->enum('building_type', ['apartment', 'house'])
                ->default('apartment')
                ->after('house');
        });

        // --------------------------------------------------
        // Orders
        // --------------------------------------------------
        Schema::table('orders', function (Blueprint $table) {
            $table
                ->enum('building_type', ['apartment', 'house'])
                ->default('apartment')
                ->after('house');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // --------------------------------------------------
        // Client Addresses
        // --------------------------------------------------
        Schema::table('client_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('client_addresses', 'building_type')) {
                $table->dropColumn('building_type');
            }
        });

        // --------------------------------------------------
        // Orders
        // --------------------------------------------------
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'building_type')) {
                $table->dropColumn('building_type');
            }
        });
    }
};