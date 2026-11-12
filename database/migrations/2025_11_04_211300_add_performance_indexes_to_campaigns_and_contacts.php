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
        // Add indexes to campaign_contacts for faster queries
        Schema::table('campaign_contacts', function (Blueprint $table) {
            // Index for finding pending contacts by campaign
            $table->index(['campaign_id', 'status'], 'idx_campaign_status');
            
            // Index for retry logic
            $table->index(['status', 'call_attempts'], 'idx_status_attempts');
            
            // Index for last call time (for scheduling)
            $table->index('last_call_at', 'idx_last_call');
            
            // Index for phone number lookups (duplicate check)
            $table->index(['campaign_id', 'phone_number'], 'idx_campaign_phone');
        });

        // Add indexes to calls for faster campaign queries
        Schema::table('calls', function (Blueprint $table) {
            // Index for campaign call status queries
            if (!Schema::hasIndex('calls', 'idx_calls_campaign_status')) {
                $table->index(['campaign_id', 'status'], 'idx_calls_campaign_status');
            }
            
            // Index for campaign contact calls
            if (!Schema::hasIndex('calls', 'idx_campaign_contact')) {
                $table->index('campaign_contact_id', 'idx_campaign_contact');
            }
            
            // Index for call type filtering
            if (!Schema::hasIndex('calls', 'idx_call_type_status')) {
                $table->index(['call_type', 'status'], 'idx_call_type_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->dropIndex('idx_campaign_status');
            $table->dropIndex('idx_status_attempts');
            $table->dropIndex('idx_last_call');
            $table->dropIndex('idx_campaign_phone');
        });

        Schema::table('calls', function (Blueprint $table) {
            if (Schema::hasIndex('calls', 'idx_calls_campaign_status')) {
                $table->dropIndex('idx_calls_campaign_status');
            }
            if (Schema::hasIndex('calls', 'idx_campaign_contact')) {
                $table->dropIndex('idx_campaign_contact');
            }
            if (Schema::hasIndex('calls', 'idx_call_type_status')) {
                $table->dropIndex('idx_call_type_status');
            }
        });
    }
};
