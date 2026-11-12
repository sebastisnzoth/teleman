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
        Schema::create('sms_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider')->default('twilio'); // twilio, plivo, etc.
            $table->string('phone_number')->unique();
            $table->string('friendly_name')->nullable();
            $table->foreignId('ai_agent_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->json('capabilities')->nullable(); // {"sms": true, "mms": true}
            $table->json('settings')->nullable(); // Auto-respond, delay, etc.
            $table->string('provider_sid')->nullable(); // Twilio Phone Number SID
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index('ai_agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_phone_numbers');
    }
};
