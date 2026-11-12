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
        Schema::table('campaign_contacts', function (Blueprint $table) {
            // Rename contact_name to first_name and add last_name
            $table->renameColumn('contact_name', 'first_name');
            $table->string('last_name')->nullable()->after('first_name');
            
            // Rename attempts to call_attempts
            $table->renameColumn('attempts', 'call_attempts');
            
            // Rename last_attempt_at to last_call_at
            $table->renameColumn('last_attempt_at', 'last_call_at');
            
            // Rename custom_data to variables
            $table->renameColumn('custom_data', 'variables');
            
            // Add DTMF response field
            $table->string('dtmf_response')->nullable()->after('call_id');
            
            // Add opted_out flag
            $table->boolean('opted_out')->default(false)->after('dtmf_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->renameColumn('first_name', 'contact_name');
            $table->dropColumn('last_name');
            $table->renameColumn('call_attempts', 'attempts');
            $table->renameColumn('last_call_at', 'last_attempt_at');
            $table->renameColumn('variables', 'custom_data');
            $table->dropColumn(['dtmf_response', 'opted_out']);
        });
    }
};
