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
            // Rename existing columns
            $table->renameColumn('provider', 'text_provider');
            $table->renameColumn('openai_api_key', 'text_api_key');
            
            // Add new TTS provider columns
            $table->string('tts_provider')->default('openai')->after('text_api_key');
            $table->text('tts_api_key')->nullable()->after('tts_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            // Remove TTS provider columns
            $table->dropColumn(['tts_provider', 'tts_api_key']);
            
            // Rename columns back
            $table->renameColumn('text_provider', 'provider');
            $table->renameColumn('text_api_key', 'openai_api_key');
        });
    }
};
