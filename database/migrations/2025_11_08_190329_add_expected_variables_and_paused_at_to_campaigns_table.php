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
            // Store expected/defined variables for the campaign
            // Example: ["first_name", "company", "product_name"]
            $table->json('expected_variables')->nullable()->after('script_text');
            
            // Track when campaign was paused
            $table->timestamp('paused_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['expected_variables', 'paused_at']);
        });
    }
};
