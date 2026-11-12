<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('caller_number');
            $table->string('call_sid')->unique();
            $table->string('queue_name');
            $table->enum('status', ['waiting', 'connecting', 'connected', 'abandoned', 'timeout'])->default('waiting');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('wait_duration_seconds')->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['queue_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_queue_entries');
    }
};
