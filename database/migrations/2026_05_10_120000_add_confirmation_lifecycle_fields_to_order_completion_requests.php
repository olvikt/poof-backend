<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_completion_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_completion_requests', 'proof_submitted_at')) {
                $table->timestamp('proof_submitted_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('order_completion_requests', 'auto_completed_at')) {
                $table->timestamp('auto_completed_at')->nullable()->after('client_confirmed_at');
            }
            if (! Schema::hasColumn('order_completion_requests', 'disputed_at')) {
                $table->timestamp('disputed_at')->nullable()->after('auto_completed_at');
            }
            if (! Schema::hasColumn('order_completion_requests', 'completion_confirmation_deadline_at')) {
                $table->timestamp('completion_confirmation_deadline_at')->nullable()->after('auto_confirmation_due_at');
            }
            if (! Schema::hasColumn('order_completion_requests', 'completion_resolution')) {
                $table->string('completion_resolution', 32)->nullable()->after('completion_confirmation_deadline_at');
            }
            if (! Schema::hasColumn('order_completion_requests', 'completion_resolution_actor')) {
                $table->string('completion_resolution_actor', 16)->nullable()->after('completion_resolution');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_completion_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'proof_submitted_at',
                'auto_completed_at',
                'disputed_at',
                'completion_confirmation_deadline_at',
                'completion_resolution',
                'completion_resolution_actor',
            ]);
        });
    }
};
