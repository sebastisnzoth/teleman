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
        Schema::create('cron_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name'); // 'schedule:run' or 'queue:work'
            $table->enum('status', ['started', 'completed', 'failed'])->default('started');
            $table->text('output')->nullable(); // Command output/errors
            $table->integer('jobs_processed')->default(0); // For queue:work
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('execution_time_ms')->nullable(); // Milliseconds
            $table->timestamps();
            
            $table->index(['job_name', 'started_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_job_logs');
    }
};
