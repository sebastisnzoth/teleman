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
            // Add foreign key to global contacts (nullable for backwards compatibility)
            $table->foreignId('contact_id')->nullable()->after('id')->constrained('contacts')->onDelete('set null');
            
            // Add composite indexes for optimal queries
            $table->index('contact_id');
            $table->index(['campaign_id', 'contact_id']); // Composite for finding contact in campaign
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_contacts', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['campaign_id', 'contact_id']);
            $table->dropIndex(['contact_id']);
            
            // Drop foreign key and column
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });
    }
};
