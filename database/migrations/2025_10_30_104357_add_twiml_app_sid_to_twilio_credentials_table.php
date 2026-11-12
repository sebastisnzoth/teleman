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
        Schema::table('twilio_credentials', function (Blueprint $table) {
            $table->string('twiml_app_sid')->nullable()->after('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('twilio_credentials', function (Blueprint $table) {
            $table->dropColumn('twiml_app_sid');
        });
    }
};
