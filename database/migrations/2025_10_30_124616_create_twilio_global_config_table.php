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
        Schema::create('twilio_global_config', function (Blueprint $table) {
            $table->id();
            $table->string('account_sid', 34)->unique();
            $table->text('auth_token'); // Encrypted
            $table->string('api_key_sid', 34)->nullable();
            $table->text('api_key_secret')->nullable(); // Encrypted
            $table->string('twiml_app_sid', 34)->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twilio_global_config');
    }
};
