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
        Schema::table('user_kyc_verifications', function (Blueprint $table) {
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_code_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_kyc_verifications', function (Blueprint $table) {
            $table->dropColumn('phone_verification_expires_at');
        });
    }
};
