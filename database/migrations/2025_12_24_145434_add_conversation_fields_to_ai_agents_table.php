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
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->text('first_message')->nullable()->after('system_prompt');
            $table->text('goodbye_message')->nullable()->after('first_message');
            $table->longText('knowledge_base')->nullable()->after('goodbye_message');
            $table->integer('response_timeout')->default(10)->after('silence_timeout')->comment('AI response timeout in seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn(['first_message', 'goodbye_message', 'knowledge_base', 'response_timeout']);
        });
    }
};
