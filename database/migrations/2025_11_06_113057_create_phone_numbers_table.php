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
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            
            // Number Details
            $table->string('number', 20)->unique()->comment('E.164 format: +1234567890');
            $table->string('formatted_number', 20)->nullable()->comment('Formatted display: (123) 456-7890');
            $table->string('friendly_name', 100)->nullable()->comment('Display name: (123) 456-7890');
            $table->char('country_code', 2)->nullable()->comment('ISO code: US, CA, GB');
            $table->string('area_code', 10)->nullable()->comment('Area/region code: 555');
            
            // Status Management
            $table->enum('status', ['available', 'requested', 'assigned', 'released'])->default('available');
            
            // Twilio Integration (HIDDEN FROM CUSTOMERS)
            $table->string('twilio_sid', 100)->unique()->nullable()->comment('Twilio phone number SID');
            
            // Ownership & Assignment
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('Current owner (customer_id when assigned)');
            $table->foreignId('requested_by')->nullable()->constrained('users')->onDelete('set null')->comment('Customer who requested');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->comment('Admin who approved');
            
            // Timestamps
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            
            // Capabilities & Pricing
            $table->json('capabilities')->nullable()->comment('{"voice": true, "sms": true, "mms": false}');
            $table->decimal('monthly_cost', 8, 2)->default(0.00)->comment('Monthly cost in USD');
            
            // Metadata
            $table->text('notes')->nullable()->comment('Admin notes');
            
            $table->timestamps();
            
            // Indexes
            $table->index('status', 'idx_phone_numbers_status');
            $table->index('user_id', 'idx_phone_numbers_user_id');
            $table->index(['country_code', 'area_code'], 'idx_phone_numbers_country_area');
            $table->index('requested_by', 'idx_phone_numbers_requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
