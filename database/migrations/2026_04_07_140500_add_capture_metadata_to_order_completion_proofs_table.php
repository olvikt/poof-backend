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
            $table->string('captured_via', 32)->nullable()->after('file_extension');
            $table->timestamp('client_device_clock_at')->nullable()->after('captured_via');
            $table->string('checksum_sha256', 64)->nullable()->after('client_device_clock_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_completion_proofs', function (Blueprint $table): void {
            $table->dropColumn(['captured_via', 'client_device_clock_at', 'checksum_sha256']);
        });
    }
};
