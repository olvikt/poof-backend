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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // клиент = пользователь
            $table->foreignId('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // курьер = пользователь (ВАЖНО!)
            $table->foreignId('courier_id')
                ->nullable()
                ->constrained('users')   // ✅ ВОТ ЗДЕСЬ ИСПРАВЛЕНИЕ
                ->nullOnDelete();

            $table->string('status')->default('new');

            $table->decimal('price', 8, 2)->nullable();

            $table->string('address');
            $table->text('comment')->nullable();

            $table->timestamp('scheduled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
