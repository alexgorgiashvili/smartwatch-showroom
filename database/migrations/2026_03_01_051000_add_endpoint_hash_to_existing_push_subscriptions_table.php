<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return;
        }

        if (! Schema::hasColumn('push_subscriptions', 'endpoint_hash')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->char('endpoint_hash', 64)->nullable()->after('endpoint');
            });
        }

        DB::statement("UPDATE push_subscriptions SET endpoint_hash = SHA2(endpoint, 256) WHERE endpoint_hash IS NULL OR endpoint_hash = ''");

        DB::statement(
            "DELETE p1 FROM push_subscriptions p1
             INNER JOIN push_subscriptions p2
               ON p1.endpoint_hash = p2.endpoint_hash
              AND p1.id > p2.id"
        );

        if (! $this->indexExists('push_subscriptions', 'push_subscriptions_endpoint_hash_unique')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->unique('endpoint_hash', 'push_subscriptions_endpoint_hash_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return;
        }

        if ($this->indexExists('push_subscriptions', 'push_subscriptions_endpoint_hash_unique')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->dropUnique('push_subscriptions_endpoint_hash_unique');
            });
        }

        if (Schema::hasColumn('push_subscriptions', 'endpoint_hash')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->dropColumn('endpoint_hash');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        );

        return ((int) ($result->aggregate ?? 0)) > 0;
    }
};
