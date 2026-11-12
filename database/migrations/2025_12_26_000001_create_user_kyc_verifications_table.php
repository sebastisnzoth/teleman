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
        Schema::create('user_kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // KYC Tier and Status
            $table->enum('kyc_tier', ['unverified', 'basic', 'business'])->default('unverified');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            
            // Tier 1 - Basic Verification (Phone)
            $table->string('phone_number')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('phone_verification_code')->nullable();
            $table->timestamp('phone_code_sent_at')->nullable();
            $table->integer('phone_verification_attempts')->default(0);
            
            // Tier 2 - Business Information
            $table->string('business_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->enum('business_type', ['sole_proprietor', 'partnership', 'llc', 'corporation', 'other'])->nullable();
            $table->string('country', 2)->nullable(); // ISO 3166-1 alpha-2
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            
            // Identity Document
            $table->enum('id_document_type', ['passport', 'drivers_license', 'national_id', 'other'])->nullable();
            $table->string('id_document_path')->nullable(); // Encrypted storage path
            $table->string('id_document_number')->nullable(); // Encrypted
            $table->date('id_document_expiry')->nullable();
            $table->enum('id_document_status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Business Document
            $table->enum('business_document_type', ['registration_cert', 'tax_id', 'license', 'other'])->nullable();
            $table->string('business_document_path')->nullable(); // Encrypted storage path
            $table->enum('business_document_status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Selfie with ID (Anti-fraud)
            $table->string('selfie_with_id_path')->nullable(); // Encrypted storage path
            $table->enum('selfie_status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Admin Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            
            // Important Timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // KYC expires after 2 years
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'kyc_tier']);
            $table->index(['status', 'kyc_tier']);
            $table->index('expires_at');
            $table->index('submitted_at');
        });
        
        // Add KYC fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->enum('kyc_tier', ['unverified', 'basic', 'business'])->default('unverified')->after('status');
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_tier');
            $table->timestamp('kyc_expires_at')->nullable()->after('kyc_verified_at');
            
            $table->index('kyc_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kyc_tier']);
            $table->dropColumn(['kyc_tier', 'kyc_verified_at', 'kyc_expires_at']);
        });
        
        Schema::dropIfExists('user_kyc_verifications');
    }
};
