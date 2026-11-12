<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->string('account_sid', 34)->nullable()->after('twilio_sid');
            $table->index('account_sid', 'idx_phone_numbers_account_sid');
        });

        Schema::table('sms_phone_numbers', function (Blueprint $table) {
            $table->string('account_sid', 34)->nullable()->after('provider_sid');
        });
    }

    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table) {
            $table->dropIndex('idx_phone_numbers_account_sid');
            $table->dropColumn('account_sid');
        });

        Schema::table('sms_phone_numbers', function (Blueprint $table) {
            $table->dropColumn('account_sid');
        });
    }
};
