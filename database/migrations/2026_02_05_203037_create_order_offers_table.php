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
        Schema::create('order_offers', function (Blueprint $table) {
            $table->id();

            // Связи
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('courier_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Статус оффера
            $table->string('status')->index();
            // pending | accepted | rejected | expired

            // Время жизни оффера
            $table->timestamp('expires_at')->index();

            $table->timestamps();

            // Один оффер одному курьеру по одному заказу
            $table->unique(['order_id', 'courier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_offers');
    }
};

