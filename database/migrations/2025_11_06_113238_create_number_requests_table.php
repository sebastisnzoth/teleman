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
        Schema::create('number_requests', function (Blueprint $table) {
            $table->id();
            
            // Request Details
            $table->foreignId('phone_number_id')->constrained('phone_numbers')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade')->comment('Customer making request');
            
            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            
            // Processing
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who processed');
            $table->timestamp('processed_at')->nullable();
            
            // Communication
            $table->text('customer_notes')->nullable()->comment('Why customer wants this number');
            $table->text('admin_notes')->nullable()->comment('Admin approval/rejection reason');
            
            // Timestamps
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();
            
            // Indexes
            $table->index('status', 'idx_number_requests_status');
            $table->index('customer_id', 'idx_number_requests_customer');
            $table->index('phone_number_id', 'idx_number_requests_phone');
            $table->index('admin_id', 'idx_number_requests_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_requests');
    }
};
