<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\User;
use App\Models\UserKycVerification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KycSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates test users with various KYC scenarios for comprehensive testing.
     */
    public function run(): void
    {
        // Create test users with different KYC statuses
        
        // 1. Unverified User (New user within grace period - Day 3)
        $unverifiedUser = User::create([
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now()->subDays(3), // Day 3 of grace period
        ]);
        $unverifiedUser->roles()->attach(2); // Customer role
        
        // 2. Grace Period Expired User (Day 10 - Should be suspended)
        $expiredUser = User::create([
            'name' => 'Expired Grace User',
            'email' => 'expired@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now()->subDays(10), // Grace period expired
        ]);
        $expiredUser->roles()->attach(2); // Customer role
        
        // 3. Basic KYC - Pending Phone Verification
        $basicPendingUser = User::create([
            'name' => 'Basic Pending User',
            'email' => 'basic-pending@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $basicPendingUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $basicPendingUser->id,
            'kyc_tier' => KycTier::BASIC->value,
            'status' => KycStatus::PENDING->value,
            'phone_number' => '+15551234567',
            'phone_verified_at' => null,
            'phone_verification_code' => '123456',
            'phone_code_sent_at' => now(),
            'phone_verification_attempts' => 0,
        ]);
        
        // 4. Basic KYC - Approved (Phone Verified)
        $basicApprovedUser = User::create([
            'name' => 'Basic Approved User',
            'email' => 'basic-approved@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $basicApprovedUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $basicApprovedUser->id,
            'kyc_tier' => KycTier::BASIC->value,
            'status' => KycStatus::APPROVED->value,
            'phone_number' => '+15559876543',
            'phone_verified_at' => now()->subDays(5),
            'approved_at' => now()->subDays(5),
            'expires_at' => now()->addYears(2)->subDays(5),
        ]);
        
        // 5. Business KYC - Pending Admin Review (All docs submitted)
        $businessPendingUser = User::create([
            'name' => 'Business Pending User',
            'email' => 'business-pending@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $businessPendingUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $businessPendingUser->id,
            'kyc_tier' => KycTier::BUSINESS->value,
            'status' => KycStatus::PENDING->value,
            'phone_number' => '+15551112222',
            'phone_verified_at' => now()->subDays(2),
            'business_name' => 'Acme Corporation',
            'business_registration_number' => 'REG-123456789',
            'business_type' => 'corporation',
            'address_line1' => '123 Business St',
            'address_line2' => 'Suite 100',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'id_document_type' => DocumentType::PASSPORT->value,
            'id_document_path' => 'kyc/documents/test-passport.pdf',
            'id_document_status' => DocumentStatus::PENDING->value,
            'business_document_path' => 'kyc/documents/test-business-reg.pdf',
            'business_document_status' => DocumentStatus::PENDING->value,
            'selfie_with_id_path' => 'kyc/documents/test-selfie.jpg',
            'selfie_status' => DocumentStatus::PENDING->value,
            'submitted_at' => now()->subHours(5),
        ]);
        
        // 6. Business KYC - Approved
        $businessApprovedUser = User::create([
            'name' => 'Business Approved User',
            'email' => 'business-approved@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $businessApprovedUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $businessApprovedUser->id,
            'kyc_tier' => KycTier::BUSINESS->value,
            'status' => KycStatus::APPROVED->value,
            'phone_number' => '+15553334444',
            'phone_verified_at' => now()->subDays(30),
            'business_name' => 'Tech Innovations LLC',
            'business_registration_number' => 'REG-987654321',
            'business_type' => 'llc',
            'address_line1' => '456 Tech Ave',
            'city' => 'San Francisco',
            'state' => 'CA',
            'postal_code' => '94102',
            'country' => 'US',
            'id_document_type' => DocumentType::DRIVERS_LICENSE->value,
            'id_document_path' => 'kyc/documents/approved-license.pdf',
            'id_document_status' => DocumentStatus::APPROVED->value,
            'business_document_path' => 'kyc/documents/approved-business-reg.pdf',
            'business_document_status' => DocumentStatus::APPROVED->value,
            'selfie_with_id_path' => 'kyc/documents/approved-selfie.jpg',
            'selfie_status' => DocumentStatus::APPROVED->value,
            'submitted_at' => now()->subDays(30),
            'approved_at' => now()->subDays(28),
            'expires_at' => now()->addYears(2)->subDays(28),
            'reviewed_by' => 1, // Admin user
            'reviewed_at' => now()->subDays(28),
            'admin_notes' => 'All documents verified. Approved for business tier.',
        ]);
        
        // 7. Business KYC - Rejected (Need resubmission)
        $businessRejectedUser = User::create([
            'name' => 'Business Rejected User',
            'email' => 'business-rejected@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $businessRejectedUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $businessRejectedUser->id,
            'kyc_tier' => KycTier::BUSINESS->value,
            'status' => KycStatus::REJECTED->value,
            'phone_number' => '+15555556666',
            'phone_verified_at' => now()->subDays(3),
            'business_name' => 'Startup Inc',
            'business_registration_number' => 'REG-111222333',
            'business_type' => 'sole_proprietor',
            'address_line1' => '789 Startup Blvd',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
            'id_document_type' => DocumentType::NATIONAL_ID->value,
            'id_document_path' => 'kyc/documents/rejected-id.pdf',
            'id_document_status' => DocumentStatus::REJECTED->value,
            'business_document_path' => 'kyc/documents/rejected-business-reg.pdf',
            'business_document_status' => DocumentStatus::APPROVED->value,
            'selfie_with_id_path' => 'kyc/documents/rejected-selfie.jpg',
            'selfie_status' => DocumentStatus::REJECTED->value,
            'submitted_at' => now()->subDays(2),
            'reviewed_by' => 1,
            'reviewed_at' => now()->subDay(),
            'admin_notes' => 'ID document and selfie need resubmission. Business registration is acceptable.',
            'rejection_reason' => 'ID document is blurry and selfie is not clear enough for verification.',
        ]);
        
        // 8. Business KYC - Under Review (Partial document approval)
        $businessUnderReviewUser = User::create([
            'name' => 'Business Under Review User',
            'email' => 'business-review@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $businessUnderReviewUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $businessUnderReviewUser->id,
            'kyc_tier' => KycTier::BUSINESS->value,
            'status' => KycStatus::PENDING->value,
            'phone_number' => '+15557778888',
            'phone_verified_at' => now()->subHours(6),
            'business_name' => 'Global Enterprises',
            'business_registration_number' => 'REG-444555666',
            'business_type' => 'corporation',
            'address_line1' => '321 Enterprise Way',
            'city' => 'Chicago',
            'state' => 'IL',
            'postal_code' => '60601',
            'country' => 'US',
            'id_document_type' => DocumentType::PASSPORT->value,
            'id_document_path' => 'kyc/documents/review-passport.pdf',
            'id_document_status' => DocumentStatus::APPROVED->value,
            'business_document_path' => 'kyc/documents/review-business-reg.pdf',
            'business_document_status' => DocumentStatus::PENDING->value,
            'selfie_with_id_path' => 'kyc/documents/review-selfie.jpg',
            'selfie_status' => DocumentStatus::PENDING->value,
            'submitted_at' => now()->subHours(3),
            'reviewed_by' => 1,
            'reviewed_at' => now()->subHour(),
            'admin_notes' => 'ID verified. Checking business registration and selfie.',
        ]);
        
        // 9. Basic KYC - Expiring Soon (55 days until expiry - should show renewal reminder)
        $expiringUser = User::create([
            'name' => 'Expiring KYC User',
            'email' => 'expiring@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $expiringUser->roles()->attach(2);
        
        UserKycVerification::create([
            'user_id' => $expiringUser->id,
            'kyc_tier' => KycTier::BASIC->value,
            'status' => KycStatus::APPROVED->value,
            'phone_number' => '+15559990000',
            'phone_verified_at' => now()->subDays(675), // ~1.85 years ago
            'approved_at' => now()->subDays(675),
            'expires_at' => now()->addDays(55), // 55 days until expiry
        ]);
        
        $this->command->info('KYC seeder completed successfully!');
        $this->command->info('Created 9 test users with various KYC scenarios:');
        $this->command->table(
            ['Email', 'KYC Status', 'Scenario'],
            [
                ['unverified@example.com', 'Unverified', 'Day 3 of grace period'],
                ['expired@example.com', 'Unverified', 'Grace period expired (Day 10)'],
                ['basic-pending@example.com', 'Basic - Pending', 'Phone verification pending'],
                ['basic-approved@example.com', 'Basic - Approved', 'Phone verified, active'],
                ['business-pending@example.com', 'Business - Pending', 'Awaiting admin review'],
                ['business-approved@example.com', 'Business - Approved', 'Fully verified'],
                ['business-rejected@example.com', 'Business - Rejected', 'Docs need resubmission'],
                ['business-review@example.com', 'Business - Under Review', 'Partial approval'],
                ['expiring@example.com', 'Basic - Expiring', '55 days until expiry'],
            ]
        );
        $this->command->info('All passwords: password');
    }
}

