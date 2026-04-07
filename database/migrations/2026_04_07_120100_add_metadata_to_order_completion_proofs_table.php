<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_completion_proofs', function (Blueprint $table): void {
            $table->string('mime_type', 128)->nullable()->after('file_disk');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('mime_type');
            $table->string('file_extension', 16)->nullable()->after('file_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('order_completion_proofs', function (Blueprint $table): void {
            $table->dropColumn(['mime_type', 'file_size_bytes', 'file_extension']);
        });
    }
};
