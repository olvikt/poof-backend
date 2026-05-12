<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_chat_id')->nullable()->after('last_login_at');
            $table->boolean('telegram_notifications_orders_enabled')->default(false)->after('telegram_chat_id');
            $table->boolean('telegram_notifications_marketing_enabled')->default(false)->after('telegram_notifications_orders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_notifications_orders_enabled',
                'telegram_notifications_marketing_enabled',
            ]);
        });
    }
};

