<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->string('type')->default('primary')->after('courier_id');
            $table->unsignedTinyInteger('sequence')->default(1)->after('type');

            // для stack-offer
            $table->foreignId('parent_order_id')
                ->nullable()
                ->after('order_id')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->dropForeign(['parent_order_id']);
            $table->dropColumn([
                'type',
                'sequence',
                'parent_order_id',
            ]);
        });
    }
};
