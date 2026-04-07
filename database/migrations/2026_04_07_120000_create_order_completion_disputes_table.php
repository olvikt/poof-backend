<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_completion_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('completion_request_id')->constrained('order_completion_requests')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->string('reason_code', 64);
            $table->text('comment')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique('completion_request_id');
            $table->index(['status', 'opened_at']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_completion_disputes');
    }
};
