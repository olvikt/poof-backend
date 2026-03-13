<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->timestamp('last_location_at')->nullable()->after('status');
            $table->index('status');
            $table->index('last_location_at');
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['last_location_at']);
            $table->dropColumn('last_location_at');
        });
    }
};
