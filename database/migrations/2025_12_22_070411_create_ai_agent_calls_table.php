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
        Schema::create('ai_agent_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('call_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null');
            $table->string('call_sid')->unique();
            $table->string('direction'); // inbound, outbound
            $table->string('from_number');
            $table->string('to_number');
            $table->string('status')->default('initiated'); // initiated, ringing, in-progress, completed, failed, no-answer
            $table->integer('duration')->nullable(); // seconds
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable(); // completed, hangup, error, timeout, transferred
            $table->text('recording_url')->nullable();
            $table->integer('turn_count')->default(0); // Number of conversation turns
            $table->boolean('transferred')->default(false);
            $table->string('transferred_to')->nullable();
            $table->decimal('cost_estimate', 8, 4)->default(0); // Estimated cost in USD
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agent_calls');
    }
};
