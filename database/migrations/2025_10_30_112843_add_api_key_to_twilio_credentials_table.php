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
            $table->string('api_key_sid')->nullable()->after('twiml_app_sid');
            $table->text('api_key_secret')->nullable()->after('api_key_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('twilio_credentials', function (Blueprint $table) {
            $table->dropColumn(['api_key_sid', 'api_key_secret']);
        });
    }
};
