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
        Schema::create('trunk_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trunk_id')->constrained('user_sip_trunks')->onDelete('cascade');
            
            // Twilio Phone Number Info
            $table->string('phone_number_sid', 34);
            $table->string('phone_number', 20)->index();
            $table->string('friendly_name')->nullable();
            $table->string('country_code', 2)->nullable();
            
            // Capabilities (stored as JSON)
            $table->json('capabilities'); // {voice: true, sms: true, mms: false, fax: false}
            
            // Assignment (what's using this number)
            $table->enum('assigned_to', ['softphone', 'campaign', 'ai_agent', 'unassigned'])->default('unassigned');
            $table->unsignedBigInteger('assigned_id')->nullable(); // campaign_id, ai_agent_id, etc
            
            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');
            
            $table->timestamps();
            
            // Unique constraint: one number per trunk
            $table->unique(['trunk_id', 'phone_number']);
            
            // Indexes
            $table->index(['assigned_to', 'assigned_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trunk_phone_numbers');
    }
};
