<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // кто создал заказ
            if (!Schema::hasColumn('orders', 'client_id')) {
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete()->after('id');
            }

            // тип заказа: разовый / подписка
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->string('order_type', 32)->default('one_time')->after('client_id');
            }

            // адрес (структура)
            if (!Schema::hasColumn('orders', 'address_text')) {
                $table->string('address_text')->nullable()->after('order_type');
            }
            if (!Schema::hasColumn('orders', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('address_text');
            }
            if (!Schema::hasColumn('orders', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
            if (!Schema::hasColumn('orders', 'entrance')) {
                $table->string('entrance', 32)->nullable()->after('lng'); // подъезд
            }
            if (!Schema::hasColumn('orders', 'floor')) {
                $table->string('floor', 32)->nullable()->after('entrance'); // этаж
            }
            if (!Schema::hasColumn('orders', 'apartment')) {
                $table->string('apartment', 32)->nullable()->after('floor'); // кв/офис
            }
            if (!Schema::hasColumn('orders', 'intercom')) {
                $table->string('intercom', 32)->nullable()->after('apartment'); // домофон
            }

            // комментарий
            if (!Schema::hasColumn('orders', 'comment')) {
                $table->text('comment')->nullable()->after('intercom');
            }

            // расписание (дата + интервал)
            if (!Schema::hasColumn('orders', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable()->after('comment');
            }
            if (!Schema::hasColumn('orders', 'scheduled_time_from')) {
                $table->time('scheduled_time_from')->nullable()->after('scheduled_date');
            }
            if (!Schema::hasColumn('orders', 'scheduled_time_to')) {
                $table->time('scheduled_time_to')->nullable()->after('scheduled_time_from');
            }

            // как передать
            if (!Schema::hasColumn('orders', 'handover_type')) {
                $table->string('handover_type', 16)->default('door')->after('scheduled_time_to'); // door|hand
            }

            // мешки
            if (!Schema::hasColumn('orders', 'bags_count')) {
                $table->unsignedTinyInteger('bags_count')->default(1)->after('handover_type'); // 1..3
            }

            // цена
            if (!Schema::hasColumn('orders', 'price')) {
                $table->unsignedInteger('price')->default(0)->after('bags_count'); // в копейках/центах или грн — решим позже
            }

            // оплата
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 32)->default('pending')->after('price'); // pending|paid|failed|refunded
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            if (Schema::hasColumn('orders', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }

            $columns = [
                'order_type',
                'address_text','lat','lng','entrance','floor','apartment','intercom',
                'comment',
                'scheduled_date','scheduled_time_from','scheduled_time_to',
                'handover_type',
                'bags_count',
                'price',
                'payment_status',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
