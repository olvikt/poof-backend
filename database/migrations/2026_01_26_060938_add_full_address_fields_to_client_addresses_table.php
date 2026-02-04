<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_addresses', function (Blueprint $table) {

            // =============================
            // UI / ТЕКСТОВЫЕ ПОЛЯ
            // =============================

            if (! Schema::hasColumn('client_addresses', 'address_text')) {
                $table->string('address_text')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'entrance')) {
                $table->string('entrance')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'intercom')) {
                $table->string('intercom')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'floor')) {
                $table->string('floor')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'apartment')) {
                $table->string('apartment')->nullable();
            }

            if (! Schema::hasColumn('client_addresses', 'label')) {
                $table->string('label')->default('home');
            }

            if (! Schema::hasColumn('client_addresses', 'title')) {
                $table->string('title')->default('Дім');
            }

            if (! Schema::hasColumn('client_addresses', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }

            // =============================
            // КООРДИНАТЫ — ИСТИНА
            // =============================

            if (! Schema::hasColumn('client_addresses', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->index();
            }

            if (! Schema::hasColumn('client_addresses', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->index();
            }

            // =============================
            // GEO META (ТОП-УРОВЕНЬ)
            // =============================

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
