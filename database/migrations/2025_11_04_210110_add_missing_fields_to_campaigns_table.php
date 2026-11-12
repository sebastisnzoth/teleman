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
            // Add audio_file_id foreign key if it doesn't exist
            if (!Schema::hasColumn('campaigns', 'audio_file_id')) {
                $table->foreignId('audio_file_id')->nullable()->after('voice_language')->constrained('audio_files')->onDelete('set null');
            }
            
            // Add message field (rename script_text to message or add both)
            if (!Schema::hasColumn('campaigns', 'message')) {
                $table->text('message')->nullable()->after('status');
            }
            
            // Add voice field (for TTS voice selection like Polly.Joanna)
            if (!Schema::hasColumn('campaigns', 'voice')) {
                $table->string('voice')->nullable()->after('voice_language');
            }
            
            // Add from_number field
            if (!Schema::hasColumn('campaigns', 'from_number')) {
                $table->string('from_number')->nullable()->after('caller_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'audio_file_id')) {
                $table->dropForeign(['audio_file_id']);
                $table->dropColumn('audio_file_id');
            }
            
            if (Schema::hasColumn('campaigns', 'message')) {
                $table->dropColumn('message');
            }
            
            if (Schema::hasColumn('campaigns', 'voice')) {
                $table->dropColumn('voice');
            }
            
            if (Schema::hasColumn('campaigns', 'from_number')) {
                $table->dropColumn('from_number');
            }
        });
    }
};
