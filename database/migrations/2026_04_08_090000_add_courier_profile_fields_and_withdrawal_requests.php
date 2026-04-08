<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'residence_address')) {
                $table->string('residence_address', 500)->nullable()->after('avatar');
            }

            if (! Schema::hasColumn('users', 'courier_verification_status')) {
                $table->string('courier_verification_status', 40)
                    ->default('profile_incomplete')
                    ->after('residence_address')
                    ->index();
            }
        });

        Schema::create('courier_withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('requested')->index();
            $table->text('notes')->nullable();
            $table->text('admin_comment')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
            $table->index(['courier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_withdrawal_requests');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'courier_verification_status')) {
                $table->dropIndex(['courier_verification_status']);
                $table->dropColumn('courier_verification_status');
            }

            if (Schema::hasColumn('users', 'residence_address')) {
                $table->dropColumn('residence_address');
            }
        });
    }
};
