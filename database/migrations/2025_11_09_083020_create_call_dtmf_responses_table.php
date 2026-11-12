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
        Schema::create('call_dtmf_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_contact_id')->nullable()->constrained()->onDelete('set null');
            $table->string('digits_pressed', 20)->comment('The digits user pressed (e.g., "1", "23", "9")');
            $table->string('action_taken')->nullable()->comment('Action executed: transfer, hangup, opt_out, etc.');
            $table->string('action_result')->nullable()->comment('Result of action: success, failed, etc.');
            $table->text('action_details')->nullable()->comment('Additional details about action');
            $table->timestamp('pressed_at')->useCurrent()->comment('When digits were pressed');
            $table->json('metadata')->nullable()->comment('Additional metadata from Twilio');
            $table->timestamps();
            
            // Indexes for reporting
            $table->index(['campaign_id', 'digits_pressed']);
            $table->index(['campaign_id', 'action_taken']);
            $table->index('pressed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_dtmf_responses');
    }
};
