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
        Schema::create('campaign_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('phone_number'); // E.164 format
            $table->string('contact_name')->nullable();
            $table->json('custom_data')->nullable(); // For merge fields in TTS
            $table->enum('status', [
                'pending',
                'calling',
                'completed',
                'failed',
                'skipped'
            ])->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedBigInteger('call_id')->nullable(); // We'll add FK later
            $table->timestamps();

            // Indexes
            $table->index('campaign_id');
            $table->index('status');
            $table->index(['campaign_id', 'status']); // Composite for queue processing
            $table->index('phone_number'); // For duplicate checking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_contacts');
    }
};
