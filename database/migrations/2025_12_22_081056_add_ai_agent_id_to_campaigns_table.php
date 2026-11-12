<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('ai_agent_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
            $table->index('ai_agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['ai_agent_id']);
            $table->dropIndex(['ai_agent_id']);
            $table->dropColumn('ai_agent_id');
        });
    }
};
