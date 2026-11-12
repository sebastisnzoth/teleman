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
        Schema::create('message_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('variant_label', 50); // A, B, C, D, E
            $table->string('variant_name', 100); // "Professional", "Casual", "Urgent", etc.
            $table->text('message_text');
            $table->text('tone_description')->nullable(); // AI's explanation of the tone
            
            // Performance Metrics
            $table->integer('sent_count')->default(0);
            $table->integer('answered_count')->default(0);
            $table->integer('completed_count')->default(0);
            $table->integer('positive_response_count')->default(0);
            $table->decimal('answer_rate', 5, 2)->default(0); // Percentage
            $table->decimal('completion_rate', 5, 2)->default(0); // Percentage
            $table->decimal('effectiveness_score', 5, 2)->default(0); // Composite score
            
            // Metadata
            $table->boolean('is_active')->default(true);
            $table->boolean('is_winner')->default(false); // Best performing variant
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('campaign_id');
            $table->index(['campaign_id', 'is_active']);
            $table->index(['campaign_id', 'effectiveness_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_variants');
    }
};
