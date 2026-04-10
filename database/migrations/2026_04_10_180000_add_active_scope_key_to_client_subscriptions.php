<?php

declare(strict_types=1);

use App\Models\ClientSubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_subscriptions', 'active_scope_key')) {
                $table->string('active_scope_key', 64)->nullable()->after('status');
            }
        });

        $active = DB::table('client_subscriptions')
            ->select(['id', 'client_id', 'address_id'])
            ->where('status', ClientSubscription::STATUS_ACTIVE)
            ->orderBy('id')
            ->get();

        $seenScopes = [];

        foreach ($active as $row) {
            $scopeKey = ClientSubscription::buildActiveScopeKey((int) $row->client_id, $row->address_id !== null ? (int) $row->address_id : null);
            $isFirst = ! array_key_exists($scopeKey, $seenScopes);

            DB::table('client_subscriptions')
                ->where('id', (int) $row->id)
                ->update(['active_scope_key' => $isFirst ? $scopeKey : null]);

            if ($isFirst) {
                $seenScopes[$scopeKey] = true;
            }
        }

        DB::table('client_subscriptions')
            ->where('status', '!=', ClientSubscription::STATUS_ACTIVE)
            ->update(['active_scope_key' => null]);

        Schema::table('client_subscriptions', function (Blueprint $table): void {
            $table->unique('active_scope_key', 'client_subscriptions_active_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('client_subscriptions', function (Blueprint $table): void {
            $table->dropUnique('client_subscriptions_active_scope_unique');

            if (Schema::hasColumn('client_subscriptions', 'active_scope_key')) {
                $table->dropColumn('active_scope_key');
            }
        });
    }
};
