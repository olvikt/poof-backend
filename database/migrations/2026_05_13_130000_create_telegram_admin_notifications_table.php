<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_admin_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_type', 32);
            $table->string('status', 16);
            $table->text('title')->nullable();
            $table->text('message');
            $table->boolean('is_emergency')->default(false);
            $table->text('telegram_error')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'created_at']);
            $table->index(['admin_id', 'created_at']);
            $table->index(['notification_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_admin_notifications');
    }
};
