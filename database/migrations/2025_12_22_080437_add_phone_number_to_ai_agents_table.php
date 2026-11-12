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
            // Add phone number field for agent assignment  
            $table->string('phone_number')->nullable()->after('name');
            $table->unique('phone_number');
            
            // Add automatic Twilio configuration flags
            $table->boolean('twilio_auto_configured')->default(false)->after('phone_number');
            $table->string('twilio_webhook_sid')->nullable()->after('twilio_auto_configured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);
            $table->dropColumn(['phone_number', 'twilio_auto_configured', 'twilio_webhook_sid']);
        });
    }
};
