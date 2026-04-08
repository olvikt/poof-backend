<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_verification_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('status', 32);
            $table->string('document_file_path');
            $table->string('document_file_disk', 64)->nullable();
            $table->string('document_mime_type', 128)->nullable();
            $table->unsignedBigInteger('document_file_size_bytes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'created_at']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_verification_requests');
    }
};
