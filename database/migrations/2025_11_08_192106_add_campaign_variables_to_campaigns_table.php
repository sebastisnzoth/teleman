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
        Schema::table('campaigns', function (Blueprint $table) {
            // Store campaign-level variable values (same for all contacts)
            // Example: {"store_name": "Downtown Store", "discount": "25", "brand_name": "Acme"}
            $table->json('campaign_variables')->nullable()->after('expected_variables');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('campaign_variables');
        });
    }
};
