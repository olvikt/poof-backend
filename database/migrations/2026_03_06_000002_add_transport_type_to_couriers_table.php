<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            if (! Schema::hasColumn('couriers', 'transport_type')) {
                $table->string('transport_type')->default('walk')->after('transport');
            }
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            if (Schema::hasColumn('couriers', 'transport_type')) {
                $table->dropColumn('transport_type');
            }
        });
    }
};
