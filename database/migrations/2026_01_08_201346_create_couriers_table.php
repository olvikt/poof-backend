<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('offline'); // online, offline, busy
            $table->float('rating')->default(5);
            $table->unsignedInteger('completed_orders')->default(0);

            $table->string('city')->nullable();
            $table->string('transport')->default('walk'); // walk, bike, car

            $table->boolean('is_verified')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};

