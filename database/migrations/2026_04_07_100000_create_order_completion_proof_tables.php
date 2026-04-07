<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_completion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('completion_policy', 32);
            $table->string('status', 32)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('client_confirmed_at')->nullable();
            $table->timestamp('auto_confirmation_due_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['status', 'auto_confirmation_due_at']);
            $table->index(['courier_id', 'status']);
        });

        Schema::create('order_completion_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('completion_request_id')->constrained('order_completion_requests')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('proof_type', 32);
            $table->string('file_path');
            $table->string('file_disk', 32)->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['completion_request_id', 'proof_type']);
            $table->index(['order_id', 'proof_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_completion_proofs');
        Schema::dropIfExists('order_completion_requests');
    }
};
