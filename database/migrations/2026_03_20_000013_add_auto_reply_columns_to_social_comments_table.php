<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_comments', function (Blueprint $table) {
            $table->foreignId('auto_reply_rule_id')
                ->nullable()
                ->constrained('social_auto_reply_rules')
                ->nullOnDelete()
                ->after('reply_platform_id');
            $table->timestamp('auto_replied_at')->nullable()->after('auto_reply_rule_id');
            $table->text('auto_reply_error')->nullable()->after('auto_replied_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_reply_rule_id');
            $table->dropColumn(['auto_replied_at', 'auto_reply_error']);
        });
    }
};

