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
        Schema::create('user_sip_trunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Twilio Account Credentials (User's account)
            $table->string('twilio_account_sid', 34)->index();
            $table->text('twilio_auth_token'); // Encrypted
            
            // Trunk Information (Auto-created by app)
            $table->string('trunk_sid', 34)->unique()->nullable();
            $table->string('trunk_friendly_name');
            $table->string('trunk_domain_name')->nullable(); // user123.pstn.twilio.com
            
            // Origination Configuration (Inbound calls)
            $table->string('origination_sip_url', 500)->nullable();
            $table->string('origination_url_sid', 34)->nullable();
            
            // Termination Configuration (Outbound calls)
            $table->enum('termination_method', ['ip_acl', 'credentials'])->default('ip_acl');
            $table->string('ip_acl_sid', 34)->nullable();
            $table->string('credential_list_sid', 34)->nullable();
            $table->string('termination_username', 100)->nullable();
            $table->text('termination_password')->nullable(); // Encrypted
            
            // Configuration Options
            $table->integer('concurrent_calls_limit')->default(10);
            $table->boolean('disaster_recovery_enabled')->default(true);
            $table->boolean('recording_enabled')->default(false);
            $table->boolean('cnam_lookup_enabled')->default(false);
            $table->boolean('secure_trunking')->default(true);
            
            // Setup Progress (For wizard)
            $table->enum('setup_step', [
                'credentials', 
                'creating_trunk', 
                'configuring_origination',
                'configuring_termination', 
                'importing_numbers', 
                'testing', 
                'completed'
            ])->default('credentials');
            $table->integer('setup_progress')->default(0); // 0-100%
            
            // Status
            $table->enum('status', ['setting_up', 'active', 'error', 'suspended'])->default('setting_up');
            $table->enum('health_status', ['healthy', 'degraded', 'down'])->default('healthy');
            $table->timestamp('last_health_check_at')->nullable();
            $table->text('last_error')->nullable();
            
            // Statistics
            $table->integer('total_calls_count')->default(0);
            $table->decimal('total_minutes_used', 10, 2)->default(0);
            $table->timestamp('last_call_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sip_trunks');
    }
};
