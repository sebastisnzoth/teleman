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
        Schema::create('ai_agent_sms_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained('sms_conversations')->onDelete('cascade');
            $table->integer('message_count')->default(0);
            $table->integer('avg_response_time_ms')->nullable(); // Milliseconds
            $table->decimal('sentiment_score', 3, 2)->nullable(); // -1.00 to 1.00
            $table->json('outcomes')->nullable(); // {"qualified": true, "interested": true}
            $table->integer('total_tokens_used')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            
            $table->index(['ai_agent_id', 'started_at']);
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_sms_sessions');
    }
};
