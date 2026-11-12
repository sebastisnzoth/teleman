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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->enum('event_type', [
                'initiated',
                'ringing',
                'answered',
                'recording_started',
                'dtmf_received',
                'completed',
                'failed'
            ]);
            $table->json('event_data')->nullable();
            $table->timestamp('event_timestamp');
            $table->timestamps();

            // Indexes
            $table->index('call_id');
            $table->index(['call_id', 'event_timestamp']); // For chronological event queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
