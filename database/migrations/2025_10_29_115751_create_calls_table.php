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
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('campaign_contact_id')->nullable()->constrained()->onDelete('set null');
            
            $table->enum('call_type', ['manual', 'campaign'])->default('manual');
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            
            $table->string('from_number');
            $table->string('to_number');
            $table->string('twilio_call_sid')->unique()->nullable();
            
            $table->enum('status', [
                'initiated',
                'ringing',
                'in-progress',
                'completed',
                'busy',
                'no-answer',
                'failed',
                'canceled'
            ])->default('initiated');
            
            $table->integer('duration_seconds')->nullable();
            
            // Recording
            $table->string('recording_url')->nullable();
            $table->integer('recording_duration')->nullable();
            $table->string('recording_sid')->nullable();
            
            // Call Details
            $table->string('answered_by')->nullable(); // human, machine, unknown
            $table->string('dtmf_digits')->nullable();
            $table->text('error_message')->nullable();
            
            // Pricing
            $table->decimal('price', 10, 4)->nullable();
            $table->string('price_unit', 3)->nullable(); // USD, EUR, etc
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('campaign_contact_id');
            $table->index('status');
            $table->index(['user_id', 'created_at']); // For user history
            $table->index(['campaign_id', 'status']); // For campaign tracking
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
