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
        Schema::table('phone_numbers', function (Blueprint $table) {
            // Track number source (Twilio direct or SIP trunk)
            $table->enum('source', ['twilio_direct', 'sip_trunk'])
                  ->default('twilio_direct')
                  ->after('status')
                  ->comment('Where the number comes from');
            
            // Reference to trunk number (if from trunk)
            $table->foreignId('trunk_phone_number_id')
                  ->nullable()
                  ->after('source')
                  ->constrained('trunk_phone_numbers')
                  ->onDelete('cascade')
                  ->comment('Link to trunk_phone_numbers if source is sip_trunk');
            
            // Reference to trunk (for admin dashboard filtering)
            $table->foreignId('trunk_id')
                  ->nullable()
                  ->after('trunk_phone_number_id')
                  ->constrained('user_sip_trunks')
                  ->onDelete('cascade')
                  ->comment('Which trunk this number belongs to');
            
            // Pricing enhancements for showing savings
            $table->decimal('original_monthly_cost', 8, 2)
                  ->nullable()
                  ->after('monthly_cost')
                  ->comment('Standard Twilio price before trunk discount');
            
            $table->decimal('discount_percentage', 5, 2)
                  ->nullable()
                  ->after('original_monthly_cost')
                  ->comment('Percentage saved via SIP trunk (e.g., 100.00 for FREE)');
            
            // Add indexes for performance
            $table->index('source', 'idx_phone_numbers_source');
            $table->index('trunk_id', 'idx_phone_numbers_trunk_id');
            $table->index(['source', 'status'], 'idx_phone_numbers_source_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_phone_numbers_source');
            $table->dropIndex('idx_phone_numbers_trunk_id');
            $table->dropIndex('idx_phone_numbers_source_status');
            
            // Drop foreign keys
            $table->dropForeign(['trunk_phone_number_id']);
            $table->dropForeign(['trunk_id']);
            
            // Drop columns
            $table->dropColumn([
                'source',
                'trunk_phone_number_id',
                'trunk_id',
                'original_monthly_cost',
                'discount_percentage',
            ]);
        });
    }
};
