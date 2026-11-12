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
        Schema::create('dtmf_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->string('digit', 1); // 0-9, *, #
            $table->timestamp('pressed_at');
            $table->timestamps();

            // Indexes
            $table->index('call_id');
            $table->index(['call_id', 'pressed_at']); // For chronological digit tracking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dtmf_responses');
    }
};
