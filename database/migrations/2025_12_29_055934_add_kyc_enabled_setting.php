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
        // Add KYC enabled/disabled setting
        DB::table('kyc_settings')->insert([
            'key' => 'kyc_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'group' => 'general',
            'description' => 'Enable or disable KYC verification requirement for all users',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('kyc_settings')->where('key', 'kyc_enabled')->delete();
    }
};
