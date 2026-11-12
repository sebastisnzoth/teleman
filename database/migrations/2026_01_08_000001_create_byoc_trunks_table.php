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
        Schema::create('byoc_trunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Twilio BYOC Trunk identifiers
            $table->string('trunk_sid')->unique()->nullable();
            $table->string('trunk_friendly_name');
            
            // Connection Policy (defines routing to external SIP providers)
            $table->string('connection_policy_sid')->nullable();
            $table->string('connection_policy_name')->nullable();
            
            // Trunk configuration
            $table->string('voice_url')->nullable();
            $table->string('voice_method')->default('POST');
            $table->boolean('cnam_lookup_enabled')->default(false);
            $table->string('from_domain_sid')->nullable();
            
            // Setup status
            $table->enum('status', ['pending', 'configuring', 'active', 'failed', 'suspended'])->default('pending');
            $table->integer('setup_progress')->default(0);
            $table->string('setup_step')->nullable();
            $table->text('setup_error')->nullable();
            $table->boolean('is_setup_complete')->default(false);
            
            // Statistics
            $table->integer('total_calls')->default(0);
            $table->integer('total_minutes')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->timestamp('last_call_at')->nullable();
            
            // Health monitoring
            $table->enum('health_status', ['healthy', 'degraded', 'down'])->default('healthy');
            $table->timestamp('last_health_check_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index('trunk_sid');
            $table->index('connection_policy_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('byoc_trunks');
    }
};
