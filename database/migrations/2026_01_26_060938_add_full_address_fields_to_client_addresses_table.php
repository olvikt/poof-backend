<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   public function up(): void
	{
		Schema::table('client_addresses', function (Blueprint $table) {
			// добавляем только то, чего точно нет
			if (! Schema::hasColumn('client_addresses', 'address')) {
				$table->string('address')->nullable();
			}

			if (! Schema::hasColumn('client_addresses', 'entrance')) {
				$table->string('entrance')->nullable();
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
				$table->boolean('is_default')->default(true);
			}

			if (! Schema::hasColumn('client_addresses', 'lat')) {
				$table->decimal('lat', 10, 7)->nullable();
			}

			if (! Schema::hasColumn('client_addresses', 'lng')) {
				$table->decimal('lng', 10, 7)->nullable();
			}
		});
	}

	public function down(): void
	{
		// intentionally left blank
	}
};
