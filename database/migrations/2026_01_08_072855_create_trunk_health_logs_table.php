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
        Schema::create('trunk_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trunk_id')->constrained('user_sip_trunks')->onDelete('cascade');
            
            // Health Check Info
            $table->enum('check_type', ['api_test', 'outbound_test', 'inbound_test', 'connection_test']);
            $table->enum('status', ['success', 'warning', 'failed']);
            $table->integer('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable(); // Store additional check details
            
            $table->timestamp('checked_at')->useCurrent();
            
            // Indexes
            $table->index(['trunk_id', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trunk_health_logs');
    }
};
