<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->timestamp('last_offered_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->dropColumn('last_offered_at');
        });
    }
};

