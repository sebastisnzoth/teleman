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
        Schema::create('byoc_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('byoc_trunk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_policy_target_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Health check details
            $table->enum('status', ['success', 'failure', 'degraded'])->default('success');
            $table->string('check_type'); // 'connection', 'authentication', 'call_test', etc.
            $table->text('message')->nullable();
            
            // Response metrics
            $table->integer('response_time_ms')->nullable();
            $table->integer('status_code')->nullable();
            
            // Error details (if any)
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            
            $table->timestamp('checked_at');
            $table->timestamps();
            
            $table->index(['byoc_trunk_id', 'checked_at']);
            $table->index(['connection_policy_target_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('byoc_health_logs');
    }
};
