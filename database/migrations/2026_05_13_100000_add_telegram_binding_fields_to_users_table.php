<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_chat_id')->nullable()->after('push_notifications_orders_enabled');
            $table->string('telegram_user_id')->nullable()->after('telegram_chat_id');
            $table->string('telegram_username')->nullable()->after('telegram_user_id');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_username');
            $table->boolean('telegram_notifications_orders_enabled')->default(true)->after('telegram_linked_at');
            $table->boolean('telegram_notifications_marketing_enabled')->default(false)->after('telegram_notifications_orders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_user_id',
                'telegram_username',
                'telegram_linked_at',
                'telegram_notifications_orders_enabled',
                'telegram_notifications_marketing_enabled',
            ]);
        });
    }
};
