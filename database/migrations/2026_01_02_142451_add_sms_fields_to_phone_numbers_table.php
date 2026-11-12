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
        Schema::table('phone_numbers', function (Blueprint $table) {
            // AI Agent for SMS automation
            $table->foreignId('ai_agent_id')->nullable()->after('capabilities')->constrained()->onDelete('set null');
            
            // SMS-specific settings
            $table->json('sms_settings')->nullable()->after('ai_agent_id')->comment('{"auto_respond": true, "business_hours_only": false, "response_delay": 2}');
            
            // Twilio provider SID (for SMS webhook configuration)
            $table->string('provider_sid')->nullable()->after('twilio_sid')->comment('Additional provider SID for SMS');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->dropForeign(['ai_agent_id']);
            $table->dropColumn(['ai_agent_id', 'sms_settings', 'provider_sid']);
        });
    }
};
