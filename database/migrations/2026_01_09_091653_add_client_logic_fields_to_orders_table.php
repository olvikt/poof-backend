<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // тип заказа: разовый или подписка
            $table->string('type')->default('one_time')->after('status');

            // тип услуги
            $table->string('service')->default('trash_removal')->after('type');

            // мешки и вес
            $table->unsignedTinyInteger('bags_count')->default(1)->after('service');
            $table->decimal('total_weight_kg', 5, 2)->nullable()->after('bags_count');

            // валюта
            $table->string('currency', 8)->default('UAH')->after('price');

            // дата + интервал времени
            $table->date('scheduled_date')->nullable()->after('scheduled_at');
            $table->time('time_from')->nullable()->after('scheduled_date');
            $table->time('time_to')->nullable()->after('time_from');

            // адрес как сущность (как Uber)
            $table->foreignId('address_id')
                ->nullable()
                ->after('client_id')
                ->constrained('client_addresses')
                ->nullOnDelete();

            // координаты для карты
            $table->decimal('lat', 10, 7)->nullable()->after('address_id');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'service',
                'bags_count',
                'total_weight_kg',
                'currency',
                'scheduled_date',
                'time_from',
                'time_to',
                'address_id',
                'lat',
                'lng',
            ]);
        });
    }
};

