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
        // 1. Follow-up sequences (templates)
        Schema::create('follow_up_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            
            // Trigger configuration
            $table->enum('trigger_type', [
                'no_answer',           // Contact didn't answer
                'interested',          // Positive sentiment/DTMF
                'callback_requested',  // Asked to call back
                'not_interested',      // Negative response
                'manual',              // Manually added
                'completed',           // After successful call
            ])->default('no_answer');
            
            $table->json('trigger_conditions')->nullable(); // Additional conditions (JSON)
            $table->json('stop_conditions')->nullable();    // When to stop sequence (JSON)
            
            // Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('use_smart_timing')->default(true); // Use CallSchedulingOptimizer
            $table->integer('max_enrollments')->nullable(); // Limit total enrollments
            $table->integer('priority')->default(0); // Higher priority sequences run first
            
            // Statistics
            $table->integer('total_enrolled')->default(0);
            $table->integer('total_completed')->default(0);
            $table->integer('total_converted')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index('trigger_type');
        });

        // 2. Sequence steps
        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('follow_up_sequences')->onDelete('cascade');
            $table->integer('step_number')->default(1); // Order in sequence
            $table->string('step_name')->nullable();
            
            // Delay before executing this step
            $table->integer('delay_amount')->default(0); // How long to wait
            $table->enum('delay_unit', ['minutes', 'hours', 'days'])->default('hours');
            
            // Action to perform
            $table->enum('action_type', [
                'call',        // Make phone call
                'sms',         // Send SMS
                'email',       // Send email
                'wait',        // Just wait (for manual intervention)
                'webhook',     // Trigger webhook
            ])->default('call');
            
            $table->json('action_config')->nullable(); // Action-specific configuration
            
            // Conditions for this step
            $table->json('conditions')->nullable(); // When to execute (time windows, etc.)
            $table->json('stop_if')->nullable();    // Stop conditions for this step
            
            // Statistics
            $table->integer('total_executed')->default(0);
            $table->integer('total_successful')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['sequence_id', 'step_number']);
        });

        // 3. Sequence enrollments (contacts in sequences)
        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('follow_up_sequences')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('campaign_contact_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('cascade');
            
            // Current state
            $table->enum('status', [
                'active',      // Currently in sequence
                'paused',      // Temporarily paused
                'completed',   // Finished all steps
                'stopped',     // Manually stopped
                'converted',   // Lead converted!
            ])->default('active');
            
            $table->integer('current_step')->default(1);
            $table->integer('steps_completed')->default(0);
            $table->timestamp('next_step_at')->nullable(); // When next step should execute
            
            // Tracking
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('last_step_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('stopped_reason')->nullable();
            
            // Data
            $table->json('metadata')->nullable(); // Custom data
            $table->json('execution_history')->nullable(); // Step execution log
            
            $table->timestamps();
            
            // Indexes
            $table->index(['sequence_id', 'status']);
            $table->index('next_step_at'); // For finding due steps
            $table->index(['contact_id', 'status']);
            $table->index(['campaign_contact_id', 'status']);
            $table->unique(['sequence_id', 'campaign_contact_id']); // Prevent duplicates
        });

        // 4. Sequence step executions (history)
        Schema::create('sequence_step_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('sequence_enrollments')->onDelete('cascade');
            $table->foreignId('step_id')->constrained('sequence_steps')->onDelete('cascade');
            $table->foreignId('call_id')->nullable()->constrained()->onDelete('set null');
            
            // Execution details
            $table->enum('status', [
                'pending',     // Scheduled but not yet executed
                'executing',   // Currently running
                'completed',   // Successfully completed
                'failed',      // Failed to execute
                'skipped',     // Skipped due to conditions
            ])->default('pending');
            
            $table->timestamp('scheduled_at');
            $table->timestamp('executed_at')->nullable();
            $table->text('result')->nullable(); // Execution result/outcome
            $table->text('error_message')->nullable();
            
            // Next action
            $table->timestamp('next_scheduled_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['enrollment_id', 'status']);
            $table->index('scheduled_at');
            $table->index(['step_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_step_executions');
        Schema::dropIfExists('sequence_enrollments');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('follow_up_sequences');
    }
};
