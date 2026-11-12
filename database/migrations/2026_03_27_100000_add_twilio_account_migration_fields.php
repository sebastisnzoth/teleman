<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('legacy_account_sid', 34)->nullable()->after('twilio_call_sid');
            $table->index('legacy_account_sid', 'idx_calls_legacy_account_sid');
        });

        Schema::table('ai_agent_calls', function (Blueprint $table) {
            $table->string('legacy_account_sid', 34)->nullable()->after('call_sid');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('idx_calls_legacy_account_sid');
            $table->dropColumn('legacy_account_sid');
        });

        Schema::table('ai_agent_calls', function (Blueprint $table) {
            $table->dropColumn('legacy_account_sid');
        });
    }
};
