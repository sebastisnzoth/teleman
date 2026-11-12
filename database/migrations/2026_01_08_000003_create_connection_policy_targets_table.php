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
        Schema::create('connection_policy_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_policy_id')->constrained()->cascadeOnDelete();
            
            // Twilio Target identifiers
            $table->string('target_sid')->unique();
            $table->string('friendly_name');
            
            // External SIP provider configuration
            $table->enum('provider_type', ['zadarma', 'voipms', 'custom'])->default('custom');
            $table->string('sip_uri'); // e.g., sip:username@sip.zadarma.com
            $table->string('sip_username')->nullable();
            $table->text('sip_password')->nullable(); // Encrypted
            
            // Routing configuration
            $table->integer('priority')->default(10); // 0-65535, lower = higher priority
            $table->integer('weight')->default(10);   // 1-65535, higher = more load
            $table->boolean('enabled')->default(true);
            
            // Statistics
            $table->integer('total_calls')->default(0);
            $table->integer('successful_calls')->default(0);
            $table->integer('failed_calls')->default(0);
            $table->timestamp('last_call_at')->nullable();
            
            // Cost tracking (user-entered rates)
            $table->decimal('cost_per_minute', 10, 6)->nullable();
            $table->string('currency')->default('USD');
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['connection_policy_id', 'enabled']);
            $table->index('target_sid');
            $table->index('provider_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_policy_targets');
    }
};
