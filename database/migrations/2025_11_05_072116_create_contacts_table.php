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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Owner
            
            // Basic Contact Information
            $table->string('phone_number')->unique(); // E.164 format
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            
            // Additional Fields
            $table->string('job_title')->nullable();
            $table->text('notes')->nullable();
            $table->string('timezone')->nullable();
            $table->string('language')->default('en');
            
            // Custom Fields (flexible JSON for any additional data)
            $table->json('custom_fields')->nullable();
            
            // Metadata
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->boolean('opted_out')->default(false);
            $table->timestamp('opted_out_at')->nullable();
            $table->string('source')->nullable(); // 'manual', 'csv_import', 'api', 'migration'
            
            // Engagement Metrics
            $table->integer('total_campaigns')->default(0);
            $table->integer('total_calls')->default(0);
            $table->integer('successful_calls')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->decimal('engagement_score', 5, 2)->default(0.00); // 0-100
            
            $table->timestamps();
            $table->softDeletes(); // Soft delete support
            
            // Indexes for Performance
            $table->index('user_id');
            $table->index('phone_number');
            $table->index('email');
            $table->index('company');
            $table->index('status');
            $table->index('opted_out');
            $table->index(['user_id', 'status']); // Composite index
            $table->index('last_contacted_at');
            $table->index('engagement_score');
            
            // Full-text search support (MySQL only)
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['first_name', 'last_name', 'company', 'notes']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
