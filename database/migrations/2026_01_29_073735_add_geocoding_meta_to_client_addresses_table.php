<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {

            if (! Schema::hasColumn('client_addresses', 'place_id')) {
                $table->string('place_id')->nullable()->index();
            }

            if (! Schema::hasColumn('client_addresses', 'geocode_source')) {
                $table->string('geocode_source')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'geocode_accuracy')) {
                $table->string('geocode_accuracy')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'geocoded_at')) {
                $table->timestamp('geocoded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // intentionally left blank
    }
};
