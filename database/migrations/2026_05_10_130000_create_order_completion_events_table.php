<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_completion_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 32);
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('completion_request_id')->constrained('order_completion_requests')->cascadeOnDelete();
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['completion_request_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_completion_events');
    }
};
