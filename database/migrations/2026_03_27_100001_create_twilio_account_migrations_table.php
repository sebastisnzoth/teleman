<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_account_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('old_account_sid', 34);
            $table->string('new_account_sid', 34);
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->integer('numbers_matched')->default(0);
            $table->integer('numbers_deactivated')->default(0);
            $table->integer('numbers_new')->default(0);
            $table->integer('campaigns_paused')->default(0);
            $table->integer('ai_agents_reset')->default(0);
            $table->integer('calls_marked_legacy')->default(0);
            $table->integer('sms_numbers_deactivated')->default(0);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('old_account_sid');
            $table->index('new_account_sid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_account_migrations');
    }
};
