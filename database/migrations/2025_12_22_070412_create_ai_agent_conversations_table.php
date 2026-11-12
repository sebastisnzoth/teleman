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
        Schema::create('ai_agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_call_id')->constrained()->onDelete('cascade');
            $table->integer('turn_number'); // Sequence number in conversation
            $table->string('role'); // user, assistant, system
            $table->text('content'); // The text content
            $table->text('audio_url')->nullable(); // URL to audio recording of this turn
            $table->integer('duration_ms')->nullable(); // Duration in milliseconds
            $table->decimal('confidence', 5, 4)->nullable(); // STT confidence score
            $table->json('metadata')->nullable(); // Additional data (latency, tokens, etc.)
            $table->timestamps();
            
            $table->index(['ai_agent_call_id', 'turn_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_conversations');
    }
};
