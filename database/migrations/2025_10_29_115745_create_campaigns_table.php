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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['text_to_speech', 'voice_to_voice']);
            $table->enum('status', [
                'draft',
                'scheduled',
                'running',
                'paused',
                'completed',
                'failed'
            ])->default('draft');
            
            // TTS Settings
            $table->text('script_text')->nullable();
            $table->string('voice_gender')->default('female'); // male, female, neutral
            $table->string('voice_language')->default('en-US');
            
            // Voice Recording Settings
            $table->string('audio_file_path')->nullable();
            
            // Call Settings
            $table->string('caller_id')->nullable(); // Phone number to display
            $table->boolean('enable_recording')->default(false);
            $table->boolean('enable_dtmf')->default(false);
            $table->integer('max_concurrent_calls')->default(5);
            $table->integer('retry_attempts')->default(3);
            $table->integer('retry_delay_minutes')->default(5);
            
            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Statistics
            $table->integer('total_contacts')->default(0);
            $table->integer('total_called')->default(0);
            $table->integer('total_answered')->default(0);
            $table->integer('total_failed')->default(0);
            
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['user_id', 'status']); // Composite for user's campaigns
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
