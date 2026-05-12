<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_order_interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('interested');
            $table->timestamp('expressed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->decimal('courier_lat', 10, 7)->nullable();
            $table->decimal('courier_lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->unsignedInteger('eta_seconds')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'courier_id']);
            $table->index(['order_id', 'status']);
            $table->index(['courier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_order_interests');
    }
};

