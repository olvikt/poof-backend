<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixOrdersAddressColumn extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'address')) {
                $table->dropColumn('address');
            }

            if (! Schema::hasColumn('orders', 'address_text')) {
                $table->string('address_text')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->dropColumn('address_text');
        });
    }
}

