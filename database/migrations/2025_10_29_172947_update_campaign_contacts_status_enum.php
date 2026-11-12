<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change status column from enum to string to add more values
        // Using Schema Builder for cross-database compatibility
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->change();
        });
        
        // The status values now supported: pending, queued, in_progress, completed, failed, skipped
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to string (enum not supported in all databases)
        Schema::table('campaign_contacts', function (Blueprint $table) {
            $table->string('status', 50)->default('pending')->change();
        });
    }
};
