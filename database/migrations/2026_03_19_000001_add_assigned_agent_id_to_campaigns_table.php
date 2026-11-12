<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('assigned_agent_id')
                ->nullable()
                ->after('ai_agent_id')
                ->constrained('users')
                ->onDelete('set null');

            $table->index('assigned_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['assigned_agent_id']);
            $table->dropIndex(['assigned_agent_id']);
            $table->dropColumn('assigned_agent_id');
        });
    }
};
