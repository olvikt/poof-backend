<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('client_addresses', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('client_addresses', 'region')) {
                $table->dropColumn('region');
            }
        });
    }
};
