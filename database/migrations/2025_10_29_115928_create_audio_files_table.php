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
        Schema::create('audio_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('filename')->unique();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->integer('duration_seconds')->nullable();
            $table->string('mime_type');
            $table->string('format')->default('mp3'); // mp3, wav, ogg
            $table->integer('used_in_campaigns_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_files');
    }
};
