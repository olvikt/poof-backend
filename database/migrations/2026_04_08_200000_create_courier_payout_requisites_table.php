<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_payout_requisites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('card_holder_name', 255);
            $table->text('card_number_encrypted');
            $table->string('masked_card_number', 32);
            $table->string('bank_name', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('courier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_payout_requisites');
    }
};
