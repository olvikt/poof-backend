<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();

            // 🔥 ПРАВИЛЬНАЯ СВЯЗЬ — адрес принадлежит пользователю
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // UI / человекочитаемый адрес
            $table->string('title')->nullable();        // Дом / Работа
            $table->string('address_text')->nullable(); // строка для UI

            // Структурированный адрес (по желанию)
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('house')->nullable();
            $table->string('entrance')->nullable();
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->string('intercom')->nullable();

            // Координаты для карты
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Адрес по умолчанию
            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};

