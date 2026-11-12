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
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            // Composite index for faster context retrieval
            // Speeds up queries that filter by call_id and order by turn_number
            $table->index(['ai_agent_call_id', 'turn_number', 'role'], 'idx_call_turn_role');
            
            // Index for role filtering (used in context building)
            $table->index('role', 'idx_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_call_turn_role');
            $table->dropIndex('idx_role');
        });
    }
};
