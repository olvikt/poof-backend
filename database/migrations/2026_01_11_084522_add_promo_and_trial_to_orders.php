<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPromoAndTrialToOrders extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('promo_code')->nullable()->after('price');
            $table->boolean('is_trial')->default(false)->after('promo_code');
            $table->unsignedTinyInteger('trial_days')->nullable()->after('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['promo_code', 'is_trial', 'trial_days']);
        });
    }
}

