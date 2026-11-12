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
        Schema::create('kyc_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->string('group')->default('general'); // general, limits, documents, etc.
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default KYC settings
        DB::table('kyc_settings')->insert([
            // Grace Period Settings
            [
                'key' => 'kyc_grace_period_days',
                'value' => '7',
                'type' => 'integer',
                'group' => 'general',
                'description' => 'Number of days new users have to complete KYC verification before account suspension',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'kyc_verification_expiry_years',
                'value' => '2',
                'type' => 'integer',
                'group' => 'general',
                'description' => 'Number of years before KYC verification expires and needs renewal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'kyc_renewal_reminder_days',
                'value' => '30',
                'type' => 'integer',
                'group' => 'general',
                'description' => 'Number of days before expiry to send renewal reminder',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Document Settings
            
            // Document Settings
            [
                'key' => 'kyc_allowed_id_types',
                'value' => '["passport","drivers_license","national_id","state_id"]',
                'type' => 'json',
                'group' => 'documents',
                'description' => 'Allowed ID document types for verification',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'kyc_max_file_size_mb',
                'value' => '10',
                'type' => 'integer',
                'group' => 'documents',
                'description' => 'Maximum file size (MB) for document uploads',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'kyc_allowed_file_types',
                'value' => '["pdf","jpg","jpeg","png"]',
                'type' => 'json',
                'group' => 'documents',
                'description' => 'Allowed file types for document uploads',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Notification Settings
            [
                'key' => 'kyc_send_grace_period_reminder',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Send reminder emails during grace period',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'kyc_send_expiry_reminder',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'description' => 'Send reminder emails before KYC expiry',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_settings');
    }
};
